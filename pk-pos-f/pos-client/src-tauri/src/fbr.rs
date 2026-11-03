use crate::db::{self, LocalSale};
use chrono::Utc;
use rusqlite::Connection;
use serde::{Deserialize, Serialize};
use std::sync::{Arc, Mutex};
use std::time::Duration;
use thiserror::Error;

#[derive(Error, Debug)]
pub enum FbrError {
    #[error("Transient failure (timeout or FBR 5xx): {0}")]
    Transient(String),
    #[error("FBR Rejected ({code}): {message}")]
    Rejected { code: u16, message: String },
    #[error("Other error: {0}")]
    Other(String),
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct FbrResponse {
    pub usin: String,
    pub qr_payload: String,
}

#[derive(Clone, Debug)]
pub struct FbrClient {
    pub bearer_token: String,
    pub pos_id: String,
    pub ntn: String,
    pub base_url: String,
    pub timeout: Duration,
    client: reqwest::Client,
}

impl FbrClient {
    pub fn new(
        bearer_token: impl Into<String>,
        pos_id: impl Into<String>,
        base_url: impl Into<String>,
    ) -> Self {
        let timeout = Duration::from_secs(5);
        let client = reqwest::Client::builder()
            .timeout(timeout)
            .build()
            .unwrap_or_default();

        Self {
            bearer_token: bearer_token.into(),
            pos_id: pos_id.into(),
            ntn: "1234567-8".to_string(),
            base_url: base_url.into(),
            timeout,
            client,
        }
    }

    pub fn with_ntn(mut self, ntn: impl Into<String>) -> Self {
        self.ntn = ntn.into();
        self
    }

    pub fn with_timeout(mut self, timeout: Duration) -> Self {
        self.timeout = timeout;
        self.client = reqwest::Client::builder()
            .timeout(timeout)
            .build()
            .unwrap_or_default();
        self
    }

    /// Submits invoice to FBR IRIS.
    pub async fn submit_invoice(&self, sale: &LocalSale) -> Result<FbrResponse, FbrError> {
        let url = if self.base_url.ends_with("/invoice") {
            self.base_url.clone()
        } else {
            format!("{}/invoice", self.base_url.trim_end_matches('/'))
        };

        let payload = serde_json::json!({
            "pos_id": self.pos_id,
            "invoice_number": sale.sale_number,
            "payment_method": sale.payment_method,
            "subtotal_minor": sale.subtotal_minor,
            "tax_minor": sale.tax_minor,
            "total_minor": sale.total_minor,
            "created_at": sale.created_at,
        });

        let res = self
            .client
            .post(&url)
            .header("Authorization", format!("Bearer {}", self.bearer_token))
            .header("Content-Type", "application/json")
            .json(&payload)
            .send()
            .await;

        match res {
            Ok(resp) => {
                let status = resp.status();
                if status.is_success() {
                    let json: serde_json::Value = resp
                        .json()
                        .await
                        .map_err(|e| FbrError::Transient(e.to_string()))?;

                    let usin = json
                        .get("usin")
                        .and_then(|v| v.as_str())
                        .unwrap_or("")
                        .to_string();

                    let qr_payload = json
                        .get("qr_payload")
                        .and_then(|v| v.as_str())
                        .unwrap_or("")
                        .to_string();

                    Ok(FbrResponse { usin, qr_payload })
                } else if status.is_server_error() {
                    let msg = resp.text().await.unwrap_or_default();
                    Err(FbrError::Transient(format!("FBR 5xx Server Error: {}", msg)))
                } else if status.is_client_error() {
                    let code = status.as_u16();
                    let msg = resp.text().await.unwrap_or_default();
                    Err(FbrError::Rejected { code, message: msg })
                } else {
                    Err(FbrError::Transient(format!(
                        "Unexpected HTTP status: {}",
                        status
                    )))
                }
            }
            Err(err) => {
                // Timeout or network connection error
                Err(FbrError::Transient(err.to_string()))
            }
        }
    }
}

/// Generates a deterministic provisional reference: PROV-{ntn}-{pos_id}-{date}-{counter:06}.
pub fn generate_provisional_ref(
    ntn: &str,
    pos_id: &str,
    date: &str,
    monotonic_counter: u64,
) -> String {
    format!("PROV-{}-{}-{}-{:06}", ntn, pos_id, date, monotonic_counter)
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
#[serde(tag = "status", content = "data")]
pub enum ProcessResult {
    Submitted { usin: String },
    Offline { provisional_ref: String },
    Rejected { reason: String },
}

/// Core implementation for processing a sale and handling FBR submission outcomes.
/// Note: Mutex lock is NOT held across the network .await point.
pub async fn process_sale_impl(
    db: &Arc<Mutex<Connection>>,
    fbr_client: &FbrClient,
    monotonic_counter: u64,
    sale_json: &str,
) -> Result<ProcessResult, String> {
    // 1. Insert locally and load LocalSale while holding lock
    let (sale_id, local_sale) = {
        let mut conn = db.lock().map_err(|e| e.to_string())?;
        let sale_id = db::enqueue_sale_tx(&mut conn, sale_json).map_err(|e| e.to_string())?;
        let local_sale = db::get_local_sale_by_id(&conn, &sale_id).map_err(|e| e.to_string())?;
        (sale_id, local_sale)
    };

    // 2. Attempt FBR submission without holding database lock
    let fbr_result = fbr_client.submit_invoice(&local_sale).await;

    // 3. Re-acquire lock to update local sale and outbox state
    let conn = db.lock().map_err(|e| e.to_string())?;

    match fbr_result {
        Ok(fbr_resp) => {
            db::update_sale_fbr_success(&conn, &sale_id, &fbr_resp.usin)
                .map_err(|e| e.to_string())?;

            Ok(ProcessResult::Submitted {
                usin: fbr_resp.usin,
            })
        }
        Err(FbrError::Transient(_err_msg)) => {
            db::update_sale_fbr_status(&conn, &sale_id, "offline")
                .map_err(|e| e.to_string())?;

            let date = Utc::now().format("%Y%m%d").to_string();
            let provisional_ref = generate_provisional_ref(
                &fbr_client.ntn,
                &fbr_client.pos_id,
                &date,
                monotonic_counter,
            );

            Ok(ProcessResult::Offline { provisional_ref })
        }
        Err(FbrError::Rejected { code, message }) => {
            let reason = format!("Rejected with code {}: {}", code, message);
            db::update_sale_fbr_rejected(&conn, &sale_id, &reason)
                .map_err(|e| e.to_string())?;

            Ok(ProcessResult::Rejected { reason })
        }
        Err(FbrError::Other(msg)) => {
            let reason = format!("Submission error: {}", msg);
            db::update_sale_fbr_rejected(&conn, &sale_id, &reason)
                .map_err(|e| e.to_string())?;

            Ok(ProcessResult::Rejected { reason })
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use wiremock::matchers::{method, path};
    use wiremock::{Mock, MockServer, ResponseTemplate};

    fn create_test_db() -> Arc<Mutex<Connection>> {
        let conn = Connection::open_in_memory().expect("open in-memory db");
        db::run_migrations(&conn).expect("migrations succeed");
        Arc::new(Mutex::new(conn))
    }

    #[tokio::test]
    async fn test_fbr_success_response_returns_submitted() {
        let mock_server = MockServer::start().await;

        Mock::given(method("POST"))
            .and(path("/invoice"))
            .respond_with(ResponseTemplate::new(200).set_body_json(serde_json::json!({
                "usin": "USIN-LIVE-12345678",
                "qr_payload": "FBR-QR-CODE-STRING"
            })))
            .mount(&mock_server)
            .await;

        let db = create_test_db();
        let fbr_client = FbrClient::new("test_token", "POS-001", mock_server.uri());

        let sale_json = r#"{
            "id": "11111111-2222-3333-4444-555555555555",
            "sale_number": "INV-MOCK-001",
            "payment_method": "card",
            "subtotal_minor": 100000,
            "tax_minor": 18000,
            "total_minor": 118000
        }"#;

        let result = process_sale_impl(&db, &fbr_client, 1, sale_json)
            .await
            .expect("process_sale succeeds");

        assert_eq!(
            result,
            ProcessResult::Submitted {
                usin: "USIN-LIVE-12345678".to_string()
            }
        );

        let conn = db.lock().unwrap();
        let (fbr_usin, fbr_status): (Option<String>, String) = conn
            .query_row(
                "SELECT fbr_usin, fbr_status FROM local_sales WHERE id = '11111111-2222-3333-4444-555555555555'",
                [],
                |r| Ok((r.get(0)?, r.get(1)?)),
            )
            .expect("query local_sales");

        assert_eq!(fbr_usin, Some("USIN-LIVE-12345678".to_string()));
        assert_eq!(fbr_status, "submitted");
    }

    #[tokio::test]
    async fn test_fbr_timeout_returns_offline() {
        let mock_server = MockServer::start().await;

        Mock::given(method("POST"))
            .and(path("/invoice"))
            .respond_with(
                ResponseTemplate::new(200)
                    .set_delay(Duration::from_millis(300))
                    .set_body_json(serde_json::json!({
                        "usin": "LATE-USIN",
                        "qr_payload": "LATE-QR"
                    })),
            )
            .mount(&mock_server)
            .await;

        let db = create_test_db();
        let fbr_client = FbrClient::new("test_token", "POS-001", mock_server.uri())
            .with_ntn("9876543-2")
            .with_timeout(Duration::from_millis(50));

        let sale_json = r#"{
            "id": "22222222-3333-4444-5555-666666666666",
            "sale_number": "INV-TIMEOUT-001",
            "payment_method": "cash",
            "subtotal_minor": 50000,
            "tax_minor": 9000,
            "total_minor": 59000
        }"#;

        let result = process_sale_impl(&db, &fbr_client, 42, sale_json)
            .await
            .expect("process_sale succeeds");

        match result {
            ProcessResult::Offline { provisional_ref } => {
                assert!(provisional_ref.starts_with("PROV-9876543-2-POS-001-"));
                assert!(provisional_ref.ends_with("-000042"));
            }
            other => panic!("Expected ProcessResult::Offline, got {:?}", other),
        }

        let conn = db.lock().unwrap();
        let fbr_status: String = conn
            .query_row(
                "SELECT fbr_status FROM local_sales WHERE id = '22222222-3333-4444-5555-666666666666'",
                [],
                |r| r.get(0),
            )
            .expect("query local_sales");

        assert_eq!(fbr_status, "offline");
    }

    #[tokio::test]
    async fn test_fbr_400_response_returns_rejected() {
        let mock_server = MockServer::start().await;

        Mock::given(method("POST"))
            .and(path("/invoice"))
            .respond_with(ResponseTemplate::new(400).set_body_string("Invalid HS code specified."))
            .mount(&mock_server)
            .await;

        let db = create_test_db();
        let fbr_client = FbrClient::new("test_token", "POS-001", mock_server.uri());

        let sale_json = r#"{
            "id": "33333333-4444-5555-6666-777777777777",
            "sale_number": "INV-REJECT-001",
            "payment_method": "cash",
            "subtotal_minor": 20000,
            "tax_minor": 3600,
            "total_minor": 23600
        }"#;

        let result = process_sale_impl(&db, &fbr_client, 1, sale_json)
            .await
            .expect("process_sale succeeds");

        match result {
            ProcessResult::Rejected { reason } => {
                assert!(reason.contains("Rejected with code 400"));
                assert!(reason.contains("Invalid HS code specified."));
            }
            other => panic!("Expected ProcessResult::Rejected, got {:?}", other),
        }

        let conn = db.lock().unwrap();
        let (status, last_error): (String, Option<String>) = conn
            .query_row(
                "SELECT status, last_error FROM sync_outbox WHERE id = '33333333-4444-5555-6666-777777777777'",
                [],
                |r| Ok((r.get(0)?, r.get(1)?)),
            )
            .expect("query sync_outbox");

        assert_eq!(status, "permanently_failed");
        assert!(last_error.unwrap().contains("Invalid HS code specified."));
    }
}
