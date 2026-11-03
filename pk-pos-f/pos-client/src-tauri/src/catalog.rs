use crate::db::{self, CachedProduct};
use rusqlite::Connection;
use serde::{Deserialize, Serialize};
use std::sync::{Arc, Mutex};
use std::time::Duration;

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct CatalogSyncSummary {
    pub synced_count: usize,
    pub server_updated_at: String,
    pub offline: bool,
    pub message: String,
}

#[derive(Debug, Deserialize)]
struct CatalogSyncApiResponse {
    pub products: Vec<ApiProduct>,
    pub server_updated_at: Option<String>,
    pub tenant: Option<ApiTenant>,
}

#[derive(Debug, Deserialize)]
struct ApiProduct {
    pub id: String,
    pub name: String,
    pub hs_code: String,
    pub price_minor: i64,
    pub tax_category: Option<String>,
    pub unit: Option<String>,
    pub barcode: Option<String>,
    pub tax_rate_basis_points: Option<i64>,
    #[allow(dead_code)]
    pub updated_at: Option<String>,
}

#[derive(Debug, Deserialize)]
struct ApiTenant {
    pub name: Option<String>,
    pub ntn: Option<String>,
    pub strn: Option<String>,
    pub fbr_pos_id: Option<String>,
    pub fbr_bearer_token: Option<String>,
    pub address: Option<String>,
    pub province: Option<String>,
}

/// Performs an incremental pull sync from the Laravel catalog sync endpoint.
/// If network is unreachable, gracefully falls back to existing local product cache.
pub async fn sync_catalog_from_api(
    db: &Arc<Mutex<Connection>>,
    configured_base_url: Option<String>,
) -> Result<CatalogSyncSummary, String> {
    // 1. Read settings
    let (base_url, cursor, bearer_token) = {
        let conn = db.lock().map_err(|e| e.to_string())?;
        let settings = db::get_all_settings(&conn).map_err(|e| e.to_string())?;
        let url = configured_base_url
            .filter(|u| !u.trim().is_empty())
            .unwrap_or(settings.api_base_url);
        (url, settings.server_updated_at, settings.fbr_bearer_token)
    };

    let client = match reqwest::Client::builder()
        .timeout(Duration::from_secs(5))
        .build()
    {
        Ok(c) => c,
        Err(e) => return Err(format!("HTTP client build error: {}", e)),
    };

    let mut url = format!("{}/catalog/sync", base_url.trim_end_matches('/'));
    if let Some(ref c) = cursor {
        if !c.is_empty() {
            url.push_str(&format!("?updated_after={}", urlencoding::encode(c)));
        }
    }

    let mut req = client.get(&url);
    if !bearer_token.is_empty() {
        req = req.header("Authorization", format!("Bearer {}", bearer_token));
    }

    match req.send().await {
        Ok(resp) if resp.status().is_success() => {
            let api_data = match resp.json::<CatalogSyncApiResponse>().await {
                Ok(data) => data,
                Err(err) => {
                    return Err(format!("Failed to parse catalog response: {}", err));
                }
            };

            let now_str = chrono::Utc::now().to_rfc3339();
            let new_cursor = api_data
                .server_updated_at
                .clone()
                .unwrap_or_else(|| now_str.clone());

            let cached_products: Vec<CachedProduct> = api_data
                .products
                .into_iter()
                .map(|p| CachedProduct {
                    id: p.id,
                    name: p.name,
                    hs_code: p.hs_code,
                    price_minor: p.price_minor,
                    tax_category: p.tax_category,
                    unit: p.unit.unwrap_or_else(|| "piece".to_string()),
                    barcode: p.barcode,
                    tax_rate_basis_points: p.tax_rate_basis_points.unwrap_or(1800),
                    last_synced_at: Some(now_str.clone()),
                })
                .collect();

            let count = {
                let conn = db.lock().map_err(|e| e.to_string())?;
                db::upsert_product_cache(&conn, &cached_products).map_err(|e| e.to_string())?;
                db::set_setting(&conn, "server_updated_at", &new_cursor)
                    .map_err(|e| e.to_string())?;

                if let Some(t) = api_data.tenant {
                    if let Some(n) = t.name {
                        let _ = db::set_setting(&conn, "seller_name", &n);
                    }
                    if let Some(ntn) = t.ntn {
                        let _ = db::set_setting(&conn, "fbr_ntn", &ntn);
                    }
                    if let Some(strn) = t.strn {
                        let _ = db::set_setting(&conn, "fbr_strn", &strn);
                    }
                    if let Some(pos_id) = t.fbr_pos_id {
                        let _ = db::set_setting(&conn, "fbr_pos_id", &pos_id);
                    }
                    if let Some(token) = t.fbr_bearer_token {
                        if !token.is_empty() {
                            let _ = db::set_setting(&conn, "fbr_bearer_token", &token);
                        }
                    }
                    if let Some(addr) = t.address {
                        let _ = db::set_setting(&conn, "seller_address", &addr);
                    }
                    if let Some(prov) = t.province {
                        let _ = db::set_setting(&conn, "seller_province", &prov);
                    }
                }

                cached_products.len()
            };

            Ok(CatalogSyncSummary {
                synced_count: count,
                server_updated_at: new_cursor,
                offline: false,
                message: format!("Successfully synced {} products from catalog", count),
            })
        }
        Ok(resp) => {
            // Non-200 response (e.g. 401 or 500)
            let existing_count = {
                let conn = db.lock().map_err(|e| e.to_string())?;
                db::get_cached_products(&conn, None)
                    .map(|v| v.len())
                    .unwrap_or(0)
            };
            Ok(CatalogSyncSummary {
                synced_count: existing_count,
                server_updated_at: cursor.unwrap_or_default(),
                offline: true,
                message: format!(
                    "Server returned status {}. Running offline with {} cached products.",
                    resp.status(),
                    existing_count
                ),
            })
        }
        Err(err) => {
            // Network failure / timeout - running offline
            let existing_count = {
                let conn = db.lock().map_err(|e| e.to_string())?;
                db::get_cached_products(&conn, None)
                    .map(|v| v.len())
                    .unwrap_or(0)
            };
            Ok(CatalogSyncSummary {
                synced_count: existing_count,
                server_updated_at: cursor.unwrap_or_default(),
                offline: true,
                message: format!(
                    "Network unreachable ({}). Running offline with {} cached products.",
                    err, existing_count
                ),
            })
        }
    }
}

// Simple urlencoding helper to avoid additional heavy dependency
mod urlencoding {
    pub fn encode(data: &str) -> String {
        let mut encoded = String::new();
        for byte in data.bytes() {
            match byte {
                b'a'..=b'z' | b'A'..=b'Z' | b'0'..=b'9' | b'-' | b'_' | b'.' | b'~' => {
                    encoded.push(byte as char);
                }
                _ => {
                    encoded.push_str(&format!("%{:02X}", byte));
                }
            }
        }
        encoded
    }
}
