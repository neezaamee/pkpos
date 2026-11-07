use chrono::Utc;
use rusqlite::{params, Connection};
use serde::{Deserialize, Serialize};
use thiserror::Error;
use uuid::Uuid;

#[derive(Error, Debug)]
pub enum DbError {
    #[error("Database error: {0}")]
    Sqlite(#[from] rusqlite::Error),
    #[error("JSON error: {0}")]
    Json(#[from] serde_json::Error),
    #[error("Domain error: {0}")]
    Domain(String),
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct OutboxRecord {
    pub id: String,
    pub operation_type: String,
    pub payload: String,
    pub status: String,
    pub attempt_count: i32,
    pub next_retry_at: Option<String>,
    pub last_error: Option<String>,
    pub created_at: String,
    pub completed_at: Option<String>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct LocalSale {
    pub id: String,
    pub sale_number: String,
    pub payment_method: String,
    pub subtotal_minor: i64,
    pub tax_minor: i64,
    pub total_minor: i64,
    pub fbr_status: String,
    pub fbr_usin: Option<String>,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct CachedProduct {
    pub id: String,
    pub name: String,
    pub hs_code: String,
    pub price_minor: i64,
    pub tax_category: Option<String>,
    pub unit: String,
    pub barcode: Option<String>,
    pub tax_rate_basis_points: i64,
    pub last_synced_at: Option<String>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct LocalSaleItem {
    pub id: String,
    pub sale_id: String,
    pub product_id: Option<String>,
    pub product_name: String,
    pub hs_code: String,
    pub quantity: i64,
    pub unit_price_minor: i64,
    pub tax_rate_basis_points: i64,
    pub tax_minor: i64,
    pub total_minor: i64,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct LocalSaleWithItems {
    pub sale: LocalSale,
    pub items: Vec<LocalSaleItem>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct LocalSaleSummary {
    pub id: String,
    pub sale_number: String,
    pub payment_method: String,
    pub subtotal_minor: i64,
    pub tax_minor: i64,
    pub total_minor: i64,
    pub fbr_status: String,
    pub fbr_usin: Option<String>,
    pub created_at: String,
    pub items_count: i64,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct PosSettings {
    pub api_base_url: String,
    pub api_token: Option<String>,
    pub terminal_code: Option<String>,
    pub terminal_id: Option<String>,
    pub fbr_pos_id: String,
    pub fbr_bearer_token: String,
    pub fbr_ntn: String,
    pub fbr_strn: String,
    pub seller_name: String,
    pub seller_address: String,
    pub seller_province: String,
    pub printer_type: String,
    pub printer_ip: String,
    pub printer_port: u16,
    pub server_updated_at: Option<String>,
}

impl Default for PosSettings {
    fn default() -> Self {
        Self {
            api_base_url: "https://01pos.01output.com/api/v1".to_string(),
            api_token: None,
            terminal_code: None,
            terminal_id: None,
            fbr_pos_id: "POS-001".to_string(),
            fbr_bearer_token: "".to_string(),
            fbr_ntn: "1234567-8".to_string(),
            fbr_strn: "12-34-5678-001-99".to_string(),
            seller_name: "Pakistan POS Store".to_string(),
            seller_address: "Main Boulevard, Lahore".to_string(),
            seller_province: "Punjab".to_string(),
            printer_type: "mock".to_string(),
            printer_ip: "127.0.0.1".to_string(),
            printer_port: 9100,
            server_updated_at: None,
        }
    }
}

/// Runs all SQLite migrations to create necessary tables and indexes.
pub fn run_migrations(conn: &Connection) -> Result<(), DbError> {
    conn.execute_batch(
        r#"
        PRAGMA journal_mode = WAL;
        PRAGMA foreign_keys = ON;

        CREATE TABLE IF NOT EXISTS local_sales (
            id TEXT PRIMARY KEY,
            sale_number TEXT NOT NULL,
            payment_method TEXT NOT NULL,
            subtotal_minor INTEGER NOT NULL,
            tax_minor INTEGER NOT NULL,
            total_minor INTEGER NOT NULL,
            fbr_status TEXT NOT NULL,
            fbr_usin TEXT,
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS local_sale_items (
            id TEXT PRIMARY KEY,
            sale_id TEXT NOT NULL REFERENCES local_sales(id) ON DELETE CASCADE,
            product_id TEXT,
            product_name TEXT NOT NULL,
            hs_code TEXT NOT NULL,
            quantity INTEGER NOT NULL,
            unit_price_minor INTEGER NOT NULL,
            tax_rate_basis_points INTEGER NOT NULL,
            tax_minor INTEGER NOT NULL,
            total_minor INTEGER NOT NULL
        );

        CREATE INDEX IF NOT EXISTS idx_local_sale_items_sale_id
        ON local_sale_items (sale_id);

        CREATE TABLE IF NOT EXISTS product_cache (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            hs_code TEXT NOT NULL,
            price_minor INTEGER NOT NULL,
            tax_category TEXT,
            unit TEXT DEFAULT 'piece',
            barcode TEXT,
            tax_rate_basis_points INTEGER NOT NULL DEFAULT 1800,
            last_synced_at TEXT
        );

        CREATE INDEX IF NOT EXISTS idx_product_cache_barcode ON product_cache(barcode);
        CREATE INDEX IF NOT EXISTS idx_product_cache_name ON product_cache(name);

        CREATE TABLE IF NOT EXISTS local_settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS sync_outbox (
            id TEXT PRIMARY KEY,
            operation_type TEXT NOT NULL,
            payload TEXT NOT NULL,
            status TEXT NOT NULL,
            attempt_count INTEGER NOT NULL DEFAULT 0,
            next_retry_at TEXT,
            last_error TEXT,
            created_at TEXT NOT NULL,
            completed_at TEXT
        );

        CREATE INDEX IF NOT EXISTS idx_sync_outbox_pending
        ON sync_outbox (created_at)
        WHERE status IN ('pending', 'retrying');
        "#,
    )?;

    Ok(())
}

/// On app startup, reset all `processing` records to `pending` (crash recovery).
pub fn recover_in_flight_records(conn: &Connection) -> Result<usize, DbError> {
    let count = conn.execute(
        "UPDATE sync_outbox SET status = 'pending' WHERE status = 'processing'",
        [],
    )?;
    Ok(count)
}

/// Queries the count of pending and retrying outbox items.
pub fn get_pending_outbox_count(conn: &Connection) -> Result<i64, DbError> {
    let mut stmt = conn.prepare(
        "SELECT COUNT(*) FROM sync_outbox WHERE status IN ('pending', 'retrying')",
    )?;
    let count: i64 = stmt.query_row([], |row| row.get(0))?;
    Ok(count)
}

/// Helper to insert a row into the sync_outbox table.
pub fn insert_outbox_item(
    conn: &Connection,
    id: &str,
    operation_type: &str,
    payload: &str,
    status: &str,
) -> Result<(), DbError> {
    conn.execute(
        r#"
        INSERT INTO sync_outbox (
            id, operation_type, payload, status, attempt_count, created_at
        ) VALUES (?1, ?2, ?3, ?4, 0, datetime('now'))
        "#,
        params![id, operation_type, payload, status],
    )?;
    Ok(())
}

/// Atomically inserts a sale into `local_sales` and queues a record into `sync_outbox`.
pub fn enqueue_sale_tx(conn: &mut Connection, sale_json: &str) -> Result<String, DbError> {
    let parsed: serde_json::Value = serde_json::from_str(sale_json)?;

    let id = parsed
        .get("id")
        .and_then(|v| v.as_str())
        .filter(|s| !s.is_empty())
        .map(|s| s.to_string())
        .unwrap_or_else(|| Uuid::new_v4().to_string());

    let sale_number = parsed
        .get("sale_number")
        .and_then(|v| v.as_str())
        .unwrap_or("INV-LOCAL")
        .to_string();

    let payment_method = parsed
        .get("payment_method")
        .and_then(|v| v.as_str())
        .unwrap_or("cash")
        .to_string();

    let subtotal_minor = parsed
        .get("subtotal_minor")
        .and_then(|v| v.as_i64())
        .unwrap_or(0);

    let tax_minor = parsed
        .get("tax_minor")
        .and_then(|v| v.as_i64())
        .unwrap_or(0);

    let total_minor = parsed
        .get("total_minor")
        .and_then(|v| v.as_i64())
        .unwrap_or(subtotal_minor + tax_minor);

    let fbr_status = parsed
        .get("fbr_status")
        .and_then(|v| v.as_str())
        .unwrap_or("pending")
        .to_string();

    let fbr_usin = parsed
        .get("fbr_usin")
        .and_then(|v| v.as_str())
        .map(|s| s.to_string());

    let now_str = Utc::now().to_rfc3339();

    // Ensure the payload has the final id
    let mut payload_val = parsed.clone();
    if let serde_json::Value::Object(ref mut map) = payload_val {
        map.insert("id".to_string(), serde_json::Value::String(id.clone()));
    }
    let final_payload_str = serde_json::to_string(&payload_val)?;

    let tx = conn.transaction()?;

    tx.execute(
        r#"
        INSERT INTO local_sales (
            id, sale_number, payment_method, subtotal_minor, tax_minor,
            total_minor, fbr_status, fbr_usin, created_at
        ) VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9)
        "#,
        params![
            id,
            sale_number,
            payment_method,
            subtotal_minor,
            tax_minor,
            total_minor,
            fbr_status,
            fbr_usin,
            now_str
        ],
    )?;

    if let Some(items_arr) = parsed.get("items").and_then(|v| v.as_array()) {
        for item in items_arr {
            let item_id = item
                .get("id")
                .and_then(|v| v.as_str())
                .map(|s| s.to_string())
                .unwrap_or_else(|| Uuid::new_v4().to_string());
            let product_id = item.get("product_id").and_then(|v| v.as_str());
            let product_name = item
                .get("product_name")
                .or_else(|| item.get("name"))
                .and_then(|v| v.as_str())
                .unwrap_or("Item");
            let hs_code = item
                .get("hs_code")
                .and_then(|v| v.as_str())
                .unwrap_or("00000000");
            let quantity = item
                .get("quantity")
                .and_then(|v| v.as_i64())
                .unwrap_or(1);
            let unit_price_minor = item
                .get("unit_price_minor")
                .or_else(|| item.get("price_minor"))
                .and_then(|v| v.as_i64())
                .unwrap_or(0);
            let tax_rate_basis_points = item
                .get("tax_rate_basis_points")
                .and_then(|v| v.as_i64())
                .unwrap_or(1800);
            let tax_minor = item
                .get("tax_minor")
                .and_then(|v| v.as_i64())
                .unwrap_or(0);
            let line_total_minor = item
                .get("total_minor")
                .and_then(|v| v.as_i64())
                .unwrap_or(quantity * unit_price_minor + tax_minor);

            tx.execute(
                r#"
                INSERT INTO local_sale_items (
                    id, sale_id, product_id, product_name, hs_code,
                    quantity, unit_price_minor, tax_rate_basis_points,
                    tax_minor, total_minor
                ) VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9, ?10)
                "#,
                params![
                    item_id,
                    id,
                    product_id,
                    product_name,
                    hs_code,
                    quantity,
                    unit_price_minor,
                    tax_rate_basis_points,
                    tax_minor,
                    line_total_minor
                ],
            )?;
        }
    }

    tx.execute(
        r#"
        INSERT INTO sync_outbox (
            id, operation_type, payload, status, attempt_count, created_at
        ) VALUES (?1, ?2, ?3, 'pending', 0, ?4)
        "#,
        params![id, "sale", final_payload_str, now_str],
    )?;

    tx.commit()?;

    Ok(id)
}

/// Fetches up to `limit` records ready for syncing (status in ('pending', 'retrying') and next_retry_at <= now).
pub fn fetch_ready_outbox_records(
    conn: &Connection,
    limit: usize,
) -> Result<Vec<OutboxRecord>, DbError> {
    let mut stmt = conn.prepare(
        r#"
        SELECT id, operation_type, payload, status, attempt_count,
               next_retry_at, last_error, created_at, completed_at
        FROM sync_outbox
        WHERE status IN ('pending', 'retrying')
          AND (next_retry_at IS NULL OR next_retry_at <= datetime('now'))
        ORDER BY created_at ASC
        LIMIT ?1
        "#,
    )?;

    let rows = stmt.query_map([limit as i64], |row| {
        Ok(OutboxRecord {
            id: row.get(0)?,
            operation_type: row.get(1)?,
            payload: row.get(2)?,
            status: row.get(3)?,
            attempt_count: row.get(4)?,
            next_retry_at: row.get(5)?,
            last_error: row.get(6)?,
            created_at: row.get(7)?,
            completed_at: row.get(8)?,
        })
    })?;

    let mut records = Vec::new();
    for record in rows {
        records.push(record?);
    }
    Ok(records)
}

pub fn mark_outbox_processing(conn: &Connection, id: &str) -> Result<(), DbError> {
    conn.execute(
        "UPDATE sync_outbox SET status = 'processing' WHERE id = ?1",
        params![id],
    )?;
    Ok(())
}

pub fn mark_outbox_completed(conn: &Connection, id: &str) -> Result<(), DbError> {
    conn.execute(
        "UPDATE sync_outbox SET status = 'completed', completed_at = datetime('now') WHERE id = ?1",
        params![id],
    )?;
    Ok(())
}

pub fn mark_outbox_conflict(conn: &Connection, id: &str) -> Result<(), DbError> {
    conn.execute(
        r#"
        UPDATE sync_outbox
        SET status = 'retrying',
            next_retry_at = datetime('now', '+1 second')
        WHERE id = ?1
        "#,
        params![id],
    )?;
    Ok(())
}

pub fn get_local_sale_by_id(conn: &Connection, id: &str) -> Result<LocalSale, DbError> {
    let mut stmt = conn.prepare(
        r#"
        SELECT id, sale_number, payment_method, subtotal_minor, tax_minor,
               total_minor, fbr_status, fbr_usin, created_at
        FROM local_sales
        WHERE id = ?1
        "#,
    )?;

    let sale = stmt.query_row(params![id], |row| {
        Ok(LocalSale {
            id: row.get(0)?,
            sale_number: row.get(1)?,
            payment_method: row.get(2)?,
            subtotal_minor: row.get(3)?,
            tax_minor: row.get(4)?,
            total_minor: row.get(5)?,
            fbr_status: row.get(6)?,
            fbr_usin: row.get(7)?,
            created_at: row.get(8)?,
        })
    })?;

    Ok(sale)
}

pub fn update_sale_fbr_success(conn: &Connection, id: &str, usin: &str) -> Result<(), DbError> {
    conn.execute(
        r#"
        UPDATE local_sales
        SET fbr_usin = ?2, fbr_status = 'submitted'
        WHERE id = ?1
        "#,
        params![id, usin],
    )?;

    // Update the outbox payload with the confirmed usin and submitted status
    let payload_str: Option<String> = conn
        .query_row(
            "SELECT payload FROM sync_outbox WHERE id = ?1",
            params![id],
            |r| r.get(0),
        )
        .ok();

    if let Some(raw) = payload_str {
        if let Ok(mut json) = serde_json::from_str::<serde_json::Value>(&raw) {
            if let serde_json::Value::Object(ref mut map) = json {
                map.insert("fbr_usin".to_string(), serde_json::Value::String(usin.to_string()));
                map.insert("fbr_status".to_string(), serde_json::Value::String("submitted".to_string()));
            }
            if let Ok(new_payload) = serde_json::to_string(&json) {
                let _ = conn.execute(
                    "UPDATE sync_outbox SET payload = ?2 WHERE id = ?1",
                    params![id, new_payload],
                );
            }
        }
    }

    Ok(())
}

pub fn update_sale_fbr_status(conn: &Connection, id: &str, status: &str) -> Result<(), DbError> {
    conn.execute(
        "UPDATE local_sales SET fbr_status = ?2 WHERE id = ?1",
        params![id, status],
    )?;

    let payload_str: Option<String> = conn
        .query_row(
            "SELECT payload FROM sync_outbox WHERE id = ?1",
            params![id],
            |r| r.get(0),
        )
        .ok();

    if let Some(raw) = payload_str {
        if let Ok(mut json) = serde_json::from_str::<serde_json::Value>(&raw) {
            if let serde_json::Value::Object(ref mut map) = json {
                map.insert("fbr_status".to_string(), serde_json::Value::String(status.to_string()));
            }
            if let Ok(new_payload) = serde_json::to_string(&json) {
                let _ = conn.execute(
                    "UPDATE sync_outbox SET payload = ?2 WHERE id = ?1",
                    params![id, new_payload],
                );
            }
        }
    }

    Ok(())
}

pub fn update_sale_fbr_rejected(conn: &Connection, id: &str, reason: &str) -> Result<(), DbError> {
    conn.execute(
        "UPDATE local_sales SET fbr_status = 'failed' WHERE id = ?1",
        params![id],
    )?;

    conn.execute(
        r#"
        UPDATE sync_outbox
        SET status = 'permanently_failed',
            last_error = ?2,
            completed_at = datetime('now')
        WHERE id = ?1
        "#,
        params![id, reason],
    )?;

    Ok(())
}

pub fn mark_outbox_permanently_failed(
    conn: &Connection,
    id: &str,
    error: &str,
    attempt_count: Option<i32>,
) -> Result<(), DbError> {
    if let Some(attempts) = attempt_count {
        conn.execute(
            r#"
            UPDATE sync_outbox
            SET status = 'permanently_failed',
                attempt_count = ?2,
                last_error = ?3,
                completed_at = datetime('now')
            WHERE id = ?1
            "#,
            params![id, attempts, error],
        )?;
    } else {
        conn.execute(
            r#"
            UPDATE sync_outbox
            SET status = 'permanently_failed',
                last_error = ?2,
                completed_at = datetime('now')
            WHERE id = ?1
            "#,
            params![id, error],
        )?;
    }
    Ok(())
}

pub fn mark_outbox_retry(
    conn: &Connection,
    id: &str,
    attempt_count: i32,
    next_retry_at: &str,
    error: &str,
) -> Result<(), DbError> {
    conn.execute(
        r#"
        UPDATE sync_outbox
        SET status = 'retrying',
            attempt_count = ?2,
            next_retry_at = ?3,
            last_error = ?4
        WHERE id = ?1
        "#,
        params![id, attempt_count, next_retry_at, error],
    )?;
    Ok(())
}

/// Clears all products from `product_cache` (e.g. when pairing with a different tenant).
pub fn clear_product_cache(conn: &Connection) -> Result<(), DbError> {
    conn.execute("DELETE FROM product_cache", [])?;
    Ok(())
}

/// Clears all local sales and outbox records (e.g. when pairing with a different tenant).
pub fn clear_local_sales(conn: &Connection) -> Result<(), DbError> {
    conn.execute("DELETE FROM local_sale_items", [])?;
    conn.execute("DELETE FROM local_sales", [])?;
    conn.execute("DELETE FROM outbox", [])?;
    Ok(())
}

/// Upserts products into `product_cache`.
pub fn upsert_product_cache(
    conn: &Connection,
    products: &[CachedProduct],
) -> Result<usize, DbError> {
    let mut count = 0;
    for p in products {
        conn.execute(
            r#"
            INSERT INTO product_cache (
                id, name, hs_code, price_minor, tax_category, unit,
                barcode, tax_rate_basis_points, last_synced_at
            ) VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9)
            ON CONFLICT(id) DO UPDATE SET
                name = excluded.name,
                hs_code = excluded.hs_code,
                price_minor = excluded.price_minor,
                tax_category = excluded.tax_category,
                unit = excluded.unit,
                barcode = excluded.barcode,
                tax_rate_basis_points = excluded.tax_rate_basis_points,
                last_synced_at = excluded.last_synced_at
            "#,
            params![
                p.id,
                p.name,
                p.hs_code,
                p.price_minor,
                p.tax_category,
                p.unit,
                p.barcode,
                p.tax_rate_basis_points,
                p.last_synced_at
            ],
        )?;
        count += 1;
    }
    Ok(count)
}

/// Fetches cached products matching an optional query string (searches name, barcode, or hs_code).
pub fn get_cached_products(
    conn: &Connection,
    query: Option<&str>,
) -> Result<Vec<CachedProduct>, DbError> {
    match query {
        Some(q) if !q.trim().is_empty() => {
            let pattern = format!("%{}%", q.trim());
            let mut stmt = conn.prepare(
                r#"
                SELECT id, name, hs_code, price_minor, tax_category, unit,
                       barcode, tax_rate_basis_points, last_synced_at
                FROM product_cache
                WHERE name LIKE ?1 OR barcode LIKE ?1 OR hs_code LIKE ?1
                ORDER BY name ASC
                LIMIT 50
                "#,
            )?;
            let rows = stmt.query_map(params![pattern], |row| {
                Ok(CachedProduct {
                    id: row.get(0)?,
                    name: row.get(1)?,
                    hs_code: row.get(2)?,
                    price_minor: row.get(3)?,
                    tax_category: row.get(4)?,
                    unit: row.get(5)?,
                    barcode: row.get(6)?,
                    tax_rate_basis_points: row.get(7)?,
                    last_synced_at: row.get(8)?,
                })
            })?;
            let mut list = Vec::new();
            for r in rows {
                list.push(r?);
            }
            return Ok(list);
        }
        _ => {
            let mut stmt = conn.prepare(
                r#"
                SELECT id, name, hs_code, price_minor, tax_category, unit,
                       barcode, tax_rate_basis_points, last_synced_at
                FROM product_cache
                ORDER BY name ASC
                LIMIT 100
                "#,
            )?;
            let rows = stmt.query_map([], |row| {
                Ok(CachedProduct {
                    id: row.get(0)?,
                    name: row.get(1)?,
                    hs_code: row.get(2)?,
                    price_minor: row.get(3)?,
                    tax_category: row.get(4)?,
                    unit: row.get(5)?,
                    barcode: row.get(6)?,
                    tax_rate_basis_points: row.get(7)?,
                    last_synced_at: row.get(8)?,
                })
            })?;
            let mut list = Vec::new();
            for r in rows {
                list.push(r?);
            }
            return Ok(list);
        }
    };
}

/// Finds a cached product by exact barcode match.
pub fn get_product_by_barcode(
    conn: &Connection,
    barcode: &str,
) -> Result<Option<CachedProduct>, DbError> {
    let mut stmt = conn.prepare(
        r#"
        SELECT id, name, hs_code, price_minor, tax_category, unit,
               barcode, tax_rate_basis_points, last_synced_at
        FROM product_cache
        WHERE barcode = ?1
        LIMIT 1
        "#,
    )?;

    let mut rows = stmt.query_map(params![barcode], |row| {
        Ok(CachedProduct {
            id: row.get(0)?,
            name: row.get(1)?,
            hs_code: row.get(2)?,
            price_minor: row.get(3)?,
            tax_category: row.get(4)?,
            unit: row.get(5)?,
            barcode: row.get(6)?,
            tax_rate_basis_points: row.get(7)?,
            last_synced_at: row.get(8)?,
        })
    })?;

    if let Some(row) = rows.next() {
        Ok(Some(row?))
    } else {
        Ok(None)
    }
}

/// Gets a single setting value from `local_settings`.
pub fn get_setting(conn: &Connection, key: &str) -> Result<Option<String>, DbError> {
    let mut stmt = conn.prepare("SELECT value FROM local_settings WHERE key = ?1")?;
    let mut rows = stmt.query_map(params![key], |r| r.get(0))?;
    if let Some(val) = rows.next() {
        Ok(Some(val?))
    } else {
        Ok(None)
    }
}

/// Sets a single setting value in `local_settings`.
pub fn set_setting(conn: &Connection, key: &str, value: &str) -> Result<(), DbError> {
    conn.execute(
        r#"
        INSERT INTO local_settings (key, value)
        VALUES (?1, ?2)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value
        "#,
        params![key, value],
    )?;
    Ok(())
}

/// Loads all POS settings with defaults.
pub fn get_all_settings(conn: &Connection) -> Result<PosSettings, DbError> {
    let mut s = PosSettings::default();

    if let Some(val) = get_setting(conn, "api_base_url")? {
        s.api_base_url = val;
    }
    if let Some(val) = get_setting(conn, "api_token")? {
        s.api_token = Some(val);
    }
    if let Some(val) = get_setting(conn, "terminal_code")? {
        s.terminal_code = Some(val);
    }
    if let Some(val) = get_setting(conn, "terminal_id")? {
        s.terminal_id = Some(val);
    }
    if let Some(val) = get_setting(conn, "fbr_pos_id")? {
        s.fbr_pos_id = val;
    }
    if let Some(val) = get_setting(conn, "fbr_bearer_token")? {
        s.fbr_bearer_token = val;
    }
    if let Some(val) = get_setting(conn, "fbr_ntn")? {
        s.fbr_ntn = val;
    }
    if let Some(val) = get_setting(conn, "fbr_strn")? {
        s.fbr_strn = val;
    }
    if let Some(val) = get_setting(conn, "seller_name")? {
        s.seller_name = val;
    }
    if let Some(val) = get_setting(conn, "seller_address")? {
        s.seller_address = val;
    }
    if let Some(val) = get_setting(conn, "seller_province")? {
        s.seller_province = val;
    }
    if let Some(val) = get_setting(conn, "printer_type")? {
        s.printer_type = val;
    }
    if let Some(val) = get_setting(conn, "printer_ip")? {
        s.printer_ip = val;
    }
    if let Some(val) = get_setting(conn, "printer_port")? {
        if let Ok(port) = val.parse::<u16>() {
            s.printer_port = port;
        }
    }
    if let Some(val) = get_setting(conn, "server_updated_at")? {
        s.server_updated_at = Some(val);
    }

    Ok(s)
}

/// Saves all POS settings.
pub fn save_all_settings(conn: &Connection, s: &PosSettings) -> Result<(), DbError> {
    set_setting(conn, "api_base_url", &s.api_base_url)?;
    if let Some(ref tok) = s.api_token {
        set_setting(conn, "api_token", tok)?;
    }
    if let Some(ref code) = s.terminal_code {
        set_setting(conn, "terminal_code", code)?;
    }
    if let Some(ref id) = s.terminal_id {
        set_setting(conn, "terminal_id", id)?;
    }
    set_setting(conn, "fbr_pos_id", &s.fbr_pos_id)?;
    set_setting(conn, "fbr_bearer_token", &s.fbr_bearer_token)?;
    set_setting(conn, "fbr_ntn", &s.fbr_ntn)?;
    set_setting(conn, "fbr_strn", &s.fbr_strn)?;
    set_setting(conn, "seller_name", &s.seller_name)?;
    set_setting(conn, "seller_address", &s.seller_address)?;
    set_setting(conn, "seller_province", &s.seller_province)?;
    set_setting(conn, "printer_type", &s.printer_type)?;
    set_setting(conn, "printer_ip", &s.printer_ip)?;
    set_setting(conn, "printer_port", &s.printer_port.to_string())?;
    if let Some(ref dt) = s.server_updated_at {
        set_setting(conn, "server_updated_at", dt)?;
    }
    Ok(())
}

/// Queries recent sales summaries with items count.
pub fn get_local_sales_summary(
    conn: &Connection,
    limit: usize,
) -> Result<Vec<LocalSaleSummary>, DbError> {
    let mut stmt = conn.prepare(
        r#"
        SELECT s.id, s.sale_number, s.payment_method, s.subtotal_minor,
               s.tax_minor, s.total_minor, s.fbr_status, s.fbr_usin, s.created_at,
               (SELECT COUNT(*) FROM local_sale_items i WHERE i.sale_id = s.id) AS items_count
        FROM local_sales s
        ORDER BY s.created_at DESC
        LIMIT ?1
        "#,
    )?;

    let rows = stmt.query_map([limit as i64], |row| {
        Ok(LocalSaleSummary {
            id: row.get(0)?,
            sale_number: row.get(1)?,
            payment_method: row.get(2)?,
            subtotal_minor: row.get(3)?,
            tax_minor: row.get(4)?,
            total_minor: row.get(5)?,
            fbr_status: row.get(6)?,
            fbr_usin: row.get(7)?,
            created_at: row.get(8)?,
            items_count: row.get(9)?,
        })
    })?;

    let mut list = Vec::new();
    for r in rows {
        list.push(r?);
    }
    Ok(list)
}

/// Queries line items for a specific sale.
pub fn get_local_sale_items(
    conn: &Connection,
    sale_id: &str,
) -> Result<Vec<LocalSaleItem>, DbError> {
    let mut stmt = conn.prepare(
        r#"
        SELECT id, sale_id, product_id, product_name, hs_code,
               quantity, unit_price_minor, tax_rate_basis_points,
               tax_minor, total_minor
        FROM local_sale_items
        WHERE sale_id = ?1
        ORDER BY id ASC
        "#,
    )?;

    let rows = stmt.query_map([sale_id], |row| {
        Ok(LocalSaleItem {
            id: row.get(0)?,
            sale_id: row.get(1)?,
            product_id: row.get(2)?,
            product_name: row.get(3)?,
            hs_code: row.get(4)?,
            quantity: row.get(5)?,
            unit_price_minor: row.get(6)?,
            tax_rate_basis_points: row.get(7)?,
            tax_minor: row.get(8)?,
            total_minor: row.get(9)?,
        })
    })?;

    let mut list = Vec::new();
    for r in rows {
        list.push(r?);
    }
    Ok(list)
}

/// Queries a complete local sale with all its line items.
pub fn get_local_sale_with_items(
    conn: &Connection,
    sale_id: &str,
) -> Result<LocalSaleWithItems, DbError> {
    let sale = get_local_sale_by_id(conn, sale_id)?;
    let items = get_local_sale_items(conn, sale_id)?;
    Ok(LocalSaleWithItems { sale, items })
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_sync_outbox_pending_count() {
        let conn = Connection::open_in_memory().expect("failed to open in-memory db");
        run_migrations(&conn).expect("failed to run migrations");

        let outbox_id = Uuid::new_v4().to_string();
        insert_outbox_item(
            &conn,
            &outbox_id,
            "CREATE_SALE",
            r#"{"sale_number":"INV-001","total_minor":100000}"#,
            "pending",
        )
        .expect("failed to insert outbox item");

        let pending_count = get_pending_outbox_count(&conn).expect("failed to query count");
        assert_eq!(pending_count, 1, "Expected pending count to be 1");
    }

    #[test]
    fn test_enqueue_sale_transaction() {
        let mut conn = Connection::open_in_memory().expect("failed to open in-memory db");
        run_migrations(&conn).expect("failed to run migrations");

        let sale_id = Uuid::new_v4().to_string();
        let sale_json = format!(
            r#"{{
                "id": "{}",
                "sale_number": "INV-TX-001",
                "payment_method": "cash",
                "subtotal_minor": 10000,
                "tax_minor": 1800,
                "total_minor": 11800
            }}"#,
            sale_id
        );

        let returned_id =
            enqueue_sale_tx(&mut conn, &sale_json).expect("enqueue sale transaction succeeds");
        assert_eq!(returned_id, sale_id);

        let local_sale_count: i64 = conn
            .query_row(
                "SELECT COUNT(*) FROM local_sales WHERE id = ?1",
                params![sale_id],
                |r| r.get(0),
            )
            .expect("local_sales row exists");
        assert_eq!(local_sale_count, 1);

        let outbox_count: i64 = conn
            .query_row(
                "SELECT COUNT(*) FROM sync_outbox WHERE id = ?1 AND status = 'pending'",
                params![sale_id],
                |r| r.get(0),
            )
            .expect("sync_outbox row exists");
        assert_eq!(outbox_count, 1);
    }

    #[test]
    fn test_product_cache_and_settings() {
        let conn = Connection::open_in_memory().expect("failed to open db");
        run_migrations(&conn).expect("failed to run migrations");

        let p1 = CachedProduct {
            id: "prod-1".to_string(),
            name: "Basmati Rice 1kg".to_string(),
            hs_code: "10063010".to_string(),
            price_minor: 35000,
            tax_category: Some("general_goods".to_string()),
            unit: "kg".to_string(),
            barcode: Some("89640001001".to_string()),
            tax_rate_basis_points: 1800,
            last_synced_at: Some("2026-10-22T08:00:00Z".to_string()),
        };

        let p2 = CachedProduct {
            id: "prod-2".to_string(),
            name: "Sunflower Cooking Oil 1L".to_string(),
            hs_code: "15121900".to_string(),
            price_minor: 55000,
            tax_category: Some("general_goods".to_string()),
            unit: "bottle".to_string(),
            barcode: Some("89640001002".to_string()),
            tax_rate_basis_points: 1800,
            last_synced_at: Some("2026-10-22T08:00:00Z".to_string()),
        };

        let count = upsert_product_cache(&conn, &[p1, p2]).expect("upsert succeeds");
        assert_eq!(count, 2);

        // Search by name
        let results = get_cached_products(&conn, Some("Rice")).expect("search succeeds");
        assert_eq!(results.len(), 1);
        assert_eq!(results[0].name, "Basmati Rice 1kg");

        // Lookup by barcode
        let found = get_product_by_barcode(&conn, "89640001002").expect("barcode lookup succeeds");
        assert!(found.is_some());
        assert_eq!(found.unwrap().name, "Sunflower Cooking Oil 1L");

        // Settings test
        let mut settings = PosSettings::default();
        settings.seller_name = "Lahore Super Store".to_string();
        settings.fbr_pos_id = "POS-999".to_string();
        save_all_settings(&conn, &settings).expect("save settings succeeds");

        let loaded = get_all_settings(&conn).expect("load settings succeeds");
        assert_eq!(loaded.seller_name, "Lahore Super Store");
        assert_eq!(loaded.fbr_pos_id, "POS-999");
    }

    #[test]
    fn test_sale_completion_with_items_and_cash() {
        let mut conn = Connection::open_in_memory().expect("failed to open db");
        run_migrations(&conn).expect("failed to run migrations");

        let sale_id = Uuid::new_v4().to_string();
        let sale_json = format!(
            r#"{{
                "id": "{}",
                "sale_number": "INV-2026-00100",
                "payment_method": "cash",
                "subtotal_minor": 90000,
                "tax_minor": 16200,
                "total_minor": 106200,
                "items": [
                    {{
                        "id": "item-1",
                        "product_id": "prod-1",
                        "product_name": "Basmati Rice 1kg",
                        "hs_code": "10063010",
                        "quantity": 1,
                        "unit_price_minor": 35000,
                        "tax_rate_basis_points": 1800,
                        "tax_minor": 6300,
                        "total_minor": 41300
                    }},
                    {{
                        "id": "item-2",
                        "product_id": "prod-2",
                        "product_name": "Sunflower Oil 1L",
                        "hs_code": "15121900",
                        "quantity": 1,
                        "unit_price_minor": 55000,
                        "tax_rate_basis_points": 1800,
                        "tax_minor": 9900,
                        "total_minor": 64900
                    }}
                ]
            }}"#,
            sale_id
        );

        let returned_id = enqueue_sale_tx(&mut conn, &sale_json).expect("enqueue sale succeeds");
        assert_eq!(returned_id, sale_id);

        let sale_with_items =
            get_local_sale_with_items(&conn, &sale_id).expect("fetch sale with items succeeds");
        assert_eq!(sale_with_items.sale.sale_number, "INV-2026-00100");
        assert_eq!(sale_with_items.sale.payment_method, "cash");
        assert_eq!(sale_with_items.items.len(), 2);
        assert_eq!(sale_with_items.items[0].product_name, "Basmati Rice 1kg");
        assert_eq!(sale_with_items.items[1].product_name, "Sunflower Oil 1L");

        // Verify outbox record created with operation_type = 'sale'
        let (outbox_op, outbox_status): (String, String) = conn
            .query_row(
                "SELECT operation_type, status FROM sync_outbox WHERE id = ?1",
                params![sale_id],
                |r| Ok((r.get(0)?, r.get(1)?)),
            )
            .expect("outbox row found");
        assert_eq!(outbox_op, "sale");
        assert_eq!(outbox_status, "pending");

        // Verify summary query
        let summaries = get_local_sales_summary(&conn, 10).expect("get summary succeeds");
        assert_eq!(summaries.len(), 1);
        assert_eq!(summaries[0].items_count, 2);
    }
}
