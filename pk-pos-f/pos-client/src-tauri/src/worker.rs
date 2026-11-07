use crate::db::{self, DbError, OutboxRecord};
use chrono::Utc;
use rand::Rng;
use rusqlite::Connection;
use std::collections::HashMap;
use std::sync::{Arc, Mutex};
use std::time::Duration;

/// Computes the deterministic base backoff seconds: min(2^attempt * 2, 300).
pub fn calculate_backoff_base_seconds(attempt: u32) -> u64 {
    let factor = 2u64.saturating_pow(attempt);
    let doubled = factor.saturating_mul(2);
    doubled.min(300)
}

/// Computes the total backoff with jitter: min(2^attempt * 2, 300) + random(0, 1).
pub fn calculate_backoff_seconds(attempt: u32, jitter: f64) -> f64 {
    let base = calculate_backoff_base_seconds(attempt) as f64;
    base + jitter
}

#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
pub struct OutboxSyncResult {
    pub success: bool,
    pub synced_count: usize,
    pub failed_count: usize,
    pub remaining_count: i64,
    pub message: String,
}

/// Handles the outcome of syncing an outbox record based on HTTP status code or network error.
pub fn handle_sync_result(
    conn: &Connection,
    record_id: &str,
    current_attempts: i32,
    status_code: Option<u16>,
    error_msg: Option<&str>,
    jitter: f64,
) -> Result<String, DbError> {
    match status_code {
        Some(200..=299) => {
            // Check if backend returned synced: 0 with errors in JSON
            if let Some(body) = error_msg {
                if let Ok(val) = serde_json::from_str::<serde_json::Value>(body) {
                    if let Some(0) = val.get("synced").and_then(|v| v.as_i64()) {
                        if let Some(errs) = val.get("errors").and_then(|v| v.as_array()) {
                            if !errs.is_empty() {
                                let err_str = serde_json::to_string(errs).unwrap_or_default();
                                db::mark_outbox_permanently_failed(
                                    conn,
                                    record_id,
                                    &format!("Backend rejected: {}", err_str),
                                    None,
                                )?;
                                return Ok("rejected".to_string());
                            }
                        }
                    }
                }
            }
            db::mark_outbox_completed(conn, record_id)?;
            Ok("completed".to_string())
        }
        Some(409) => {
            db::mark_outbox_conflict(conn, record_id)?;
            Ok("retrying_conflict".to_string())
        }
        Some(422) | Some(403) => {
            let msg = error_msg.unwrap_or("Client error (422/403) - permanently failed");
            db::mark_outbox_permanently_failed(conn, record_id, msg, None)?;
            Ok("permanently_failed".to_string())
        }
        _ => {
            // HTTP 5xx or network/transport error
            let new_attempts = current_attempts + 1;
            let msg = error_msg.unwrap_or("Server or network error");
            if new_attempts >= 8 {
                db::mark_outbox_permanently_failed(conn, record_id, msg, Some(new_attempts))?;
                Ok("permanently_failed".to_string())
            } else {
                let backoff_secs = calculate_backoff_seconds(new_attempts as u32, jitter);
                let next_retry = Utc::now()
                    + chrono::Duration::milliseconds((backoff_secs * 1000.0) as i64);
                let next_retry_str = next_retry.format("%Y-%m-%d %H:%M:%S").to_string();
                db::mark_outbox_retry(conn, record_id, new_attempts, &next_retry_str, msg)?;
                Ok("retrying".to_string())
            }
        }
    }
}

/// Executes a synchronization round for pending outbox items.
pub async fn sync_ready_outbox_records(
    client: &reqwest::Client,
    db: &Arc<Mutex<Connection>>,
    default_base_url: &str,
) -> (usize, usize, String) {
    let (records, api_token, term_code, base_url) = {
        let guard = match db.lock() {
            Ok(g) => g,
            Err(_) => return (0, 0, "Database lock failed".to_string()),
        };
        let s = db::get_all_settings(&guard).unwrap_or_default();
        let tok = s.api_token;
        let code = s.terminal_code;
        let url = if !s.api_base_url.trim().is_empty() {
            s.api_base_url
        } else {
            default_base_url.to_string()
        };
        let recs = match db::fetch_ready_outbox_records(&guard, 50) {
            Ok(r) => r,
            Err(_) => return (0, 0, "Fetch ready records failed".to_string()),
        };
        (recs, tok, code, url)
    };

    if records.is_empty() {
        return (0, 0, String::new());
    }

    let mut synced_count = 0;
    let mut failed_count = 0;
    let mut last_err = String::new();

    // Group records by operation_type
    let mut groups: HashMap<String, Vec<OutboxRecord>> = HashMap::new();
    for record in records {
        groups
            .entry(record.operation_type.clone())
            .or_default()
            .push(record);
    }

    for (op_type, group_records) in groups {
        for record in group_records {
            // Mark record as processing
            {
                if let Ok(guard) = db.lock() {
                    let _ = db::mark_outbox_processing(&guard, &record.id);
                }
            }

            let endpoint = match op_type.as_str() {
                "sale" | "CREATE_SALE" => format!("{}/sales/sync", base_url.trim_end_matches('/')),
                _ => format!("{}/stock-movements/sync", base_url.trim_end_matches('/')),
            };

            let mut req = client
                .post(&endpoint)
                .header("Idempotency-Key", &record.id)
                .header("Content-Type", "application/json")
                .header("Accept", "application/json");

            if let Some(ref tok) = api_token {
                req = req.header("Authorization", format!("Bearer {}", tok));
            }
            if let Some(ref code) = term_code {
                req = req.header("X-Terminal-Code", code);
            }

            let body_str = if op_type == "sale" || op_type == "CREATE_SALE" {
                if !record.payload.starts_with("{\"sales\"") && !record.payload.starts_with('[') {
                    format!("{{\"sales\":[{}]}}", record.payload)
                } else {
                    record.payload.clone()
                }
            } else {
                record.payload.clone()
            };

            let resp_result = req.body(body_str).send().await;

            let (status, text) = match resp_result {
                Ok(resp) => {
                    let status = resp.status().as_u16();
                    let text = resp.text().await.unwrap_or_default();
                    (Some(status), Some(text))
                }
                Err(err) => (None, Some(err.to_string())),
            };

            let jitter: f64 = rand::thread_rng().gen_range(0.0..1.0);

            if let Ok(guard) = db.lock() {
                let res = handle_sync_result(
                    &guard,
                    &record.id,
                    record.attempt_count,
                    status,
                    text.as_deref(),
                    jitter,
                );
                match res {
                    Ok(ref s) if s == "completed" => synced_count += 1,
                    _ => {
                        failed_count += 1;
                        if let Some(t) = text {
                            last_err = t;
                        }
                    }
                }
            }
        }
    }

    (synced_count, failed_count, last_err)
}

/// Forces an immediate synchronization of all pending outbox records and resets retry timers.
pub async fn flush_outbox_now(
    db: Arc<Mutex<Connection>>,
    default_base_url: String,
) -> OutboxSyncResult {
    // 1. Reset in-flight / retrying items so they can sync right now
    {
        if let Ok(guard) = db.lock() {
            let _ = db::recover_in_flight_records(&guard);
        }
    }

    let client = reqwest::Client::builder()
        .timeout(Duration::from_secs(15))
        .build()
        .unwrap_or_default();

    let (synced, failed, err) = sync_ready_outbox_records(&client, &db, &default_base_url).await;
    let remaining = db
        .lock()
        .ok()
        .and_then(|g| db::get_pending_outbox_count(&g).ok())
        .unwrap_or(0);

    let message = if remaining == 0 && synced > 0 {
        format!("All {} local sales synced to central server successfully!", synced)
    } else if synced > 0 && failed > 0 {
        format!("Synced {} sales. {} sales failed to sync: {}", synced, failed, err)
    } else if failed > 0 {
        format!("Sync failed ({} pending): {}", remaining, err)
    } else {
        "Outbox is empty. All local records are synced.".to_string()
    };

    OutboxSyncResult {
        success: failed == 0,
        synced_count: synced,
        failed_count: failed,
        remaining_count: remaining,
        message,
    }
}

/// Starts the background outbox worker running every 10 seconds.
pub async fn start_outbox_worker(db: Arc<Mutex<Connection>>, default_base_url: String) {
    let client = reqwest::Client::builder()
        .timeout(Duration::from_secs(15))
        .build()
        .unwrap_or_default();

    let mut interval = tokio::time::interval(Duration::from_secs(10));

    loop {
        interval.tick().await;
        let _ = sync_ready_outbox_records(&client, &db, &default_base_url).await;
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use rusqlite::params;
    use uuid::Uuid;

    #[test]
    fn test_backoff_calculation() {
        // Backoff calculation for attempt 0, 1, 5, 8
        assert_eq!(calculate_backoff_base_seconds(0), 2);
        assert_eq!(calculate_backoff_base_seconds(1), 4);
        assert_eq!(calculate_backoff_base_seconds(5), 64);
        assert_eq!(calculate_backoff_base_seconds(8), 300);

        // Assert jitter is additive
        let val_with_jitter = calculate_backoff_seconds(1, 0.5);
        assert!((val_with_jitter - 4.5).abs() < f64::EPSILON);
    }

    #[test]
    fn test_crash_recovery() {
        let conn = Connection::open_in_memory().expect("open in-memory db");
        db::run_migrations(&conn).expect("migrations succeed");

        let id = Uuid::new_v4().to_string();
        conn.execute(
            r#"
            INSERT INTO sync_outbox (id, operation_type, payload, status, attempt_count, created_at)
            VALUES (?1, 'CREATE_SALE', '{}', 'processing', 0, datetime('now'))
            "#,
            params![id],
        )
        .expect("insert processing item");

        // Verify it is processing
        let status_before: String = conn
            .query_row(
                "SELECT status FROM sync_outbox WHERE id = ?1",
                params![id],
                |r| r.get(0),
            )
            .expect("query status");
        assert_eq!(status_before, "processing");

        // Simulate app restart / crash recovery
        let recovered = db::recover_in_flight_records(&conn).expect("recover records");
        assert_eq!(recovered, 1);

        let status_after: String = conn
            .query_row(
                "SELECT status FROM sync_outbox WHERE id = ?1",
                params![id],
                |r| r.get(0),
            )
            .expect("query status");
        assert_eq!(status_after, "pending");
    }

    #[test]
    fn test_max_attempts_transition_to_permanently_failed() {
        let conn = Connection::open_in_memory().expect("open in-memory db");
        db::run_migrations(&conn).expect("migrations succeed");

        let id = Uuid::new_v4().to_string();
        conn.execute(
            r#"
            INSERT INTO sync_outbox (id, operation_type, payload, status, attempt_count, created_at)
            VALUES (?1, 'CREATE_SALE', '{}', 'processing', 8, datetime('now'))
            "#,
            params![id],
        )
        .expect("insert record with attempt_count = 8");

        // Simulate 5xx response
        let outcome = handle_sync_result(&conn, &id, 8, Some(500), Some("Internal Server Error"), 0.0)
            .expect("handle 5xx");
        assert_eq!(outcome, "permanently_failed");

        let (status, attempt_count): (String, i32) = conn
            .query_row(
                "SELECT status, attempt_count FROM sync_outbox WHERE id = ?1",
                params![id],
                |r| Ok((r.get(0)?, r.get(1)?)),
            )
            .expect("query outbox");

        assert_eq!(status, "permanently_failed");
        assert_eq!(attempt_count, 9);
    }
}
