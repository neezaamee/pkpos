pub mod catalog;
pub mod db;
pub mod fbr;
pub mod printer;
pub mod worker;

use std::sync::atomic::AtomicU64;
use std::sync::{Arc, Mutex};
use tauri::{Manager, State};

pub struct AppState {
    pub db: Arc<Mutex<rusqlite::Connection>>,
    pub fbr_client: fbr::FbrClient,
    pub printer: printer::ReceiptPrinter,
    pub counter: AtomicU64,
}

#[tauri::command]
fn open_cash_drawer(state: State<'_, AppState>) -> Result<(), String> {
    state.printer.open_drawer().map_err(|e| e.to_string())
}

#[tauri::command]
fn get_pending_outbox_count(state: State<'_, AppState>) -> Result<i64, String> {
    let conn = state.db.lock().map_err(|e| e.to_string())?;
    db::get_pending_outbox_count(&conn).map_err(|e| e.to_string())
}

#[tauri::command]
fn enqueue_sale(state: State<'_, AppState>, sale_json: String) -> Result<String, String> {
    let mut conn = state.db.lock().map_err(|e| e.to_string())?;
    db::enqueue_sale_tx(&mut conn, &sale_json).map_err(|e| e.to_string())
}

#[tauri::command]
async fn process_sale(
    state: State<'_, AppState>,
    sale_json: String,
) -> Result<fbr::ProcessResult, String> {
    let counter_val = state
        .counter
        .fetch_add(1, std::sync::atomic::Ordering::SeqCst);

    let parsed_id = serde_json::from_str::<serde_json::Value>(&sale_json)
        .ok()
        .and_then(|v| {
            v.get("id")
                .and_then(|s| s.as_str())
                .filter(|s| !s.is_empty())
                .map(|s| s.to_string())
        });

    let result = fbr::process_sale_impl(&state.db, &state.fbr_client, counter_val, &sale_json).await?;

    // Attempt to print receipt automatically without failing the sale if printing errors
    let db_arc = Arc::clone(&state.db);
    let print_fbr_res: Option<printer::FbrResult> = (&result).into();

    if let (Some(fbr_res), Some(ref id)) = (print_fbr_res, parsed_id) {
        if let Ok(conn) = db_arc.lock() {
            if let Ok(sale_with_items) = db::get_local_sale_with_items(&conn, id) {
                let s = db::get_all_settings(&conn).unwrap_or_default();
                let seller = printer::SellerInfo {
                    name: s.seller_name,
                    ntn: s.fbr_ntn,
                    strn: s.fbr_strn,
                    address: s.seller_address,
                    province: s.seller_province,
                };
                let _ = state.printer.print_detailed_sale(
                    &sale_with_items.sale,
                    &sale_with_items.items,
                    &fbr_res,
                    &seller,
                );
            }
        }
    }

    Ok(result)
}

#[tauri::command]
async fn sync_catalog(state: State<'_, AppState>) -> Result<catalog::CatalogSyncSummary, String> {
    catalog::sync_catalog_from_api(&state.db, None).await
}

#[tauri::command]
fn get_cached_products(
    state: State<'_, AppState>,
    query: Option<String>,
) -> Result<Vec<db::CachedProduct>, String> {
    let conn = state.db.lock().map_err(|e| e.to_string())?;
    db::get_cached_products(&conn, query.as_deref()).map_err(|e| e.to_string())
}

#[tauri::command]
fn get_product_by_barcode(
    state: State<'_, AppState>,
    barcode: String,
) -> Result<Option<db::CachedProduct>, String> {
    let conn = state.db.lock().map_err(|e| e.to_string())?;
    db::get_product_by_barcode(&conn, &barcode).map_err(|e| e.to_string())
}

#[tauri::command]
fn get_local_sales(
    state: State<'_, AppState>,
    limit: Option<usize>,
) -> Result<Vec<db::LocalSaleSummary>, String> {
    let conn = state.db.lock().map_err(|e| e.to_string())?;
    db::get_local_sales_summary(&conn, limit.unwrap_or(50)).map_err(|e| e.to_string())
}

#[tauri::command]
fn get_local_sale_details(
    state: State<'_, AppState>,
    sale_id: String,
) -> Result<db::LocalSaleWithItems, String> {
    let conn = state.db.lock().map_err(|e| e.to_string())?;
    db::get_local_sale_with_items(&conn, &sale_id).map_err(|e| e.to_string())
}

#[tauri::command]
fn reprint_receipt(state: State<'_, AppState>, sale_id: String) -> Result<(), String> {
    let conn = state.db.lock().map_err(|e| e.to_string())?;
    let sale_with_items = db::get_local_sale_with_items(&conn, &sale_id).map_err(|e| e.to_string())?;
    let s = db::get_all_settings(&conn).unwrap_or_default();
    let seller = printer::SellerInfo {
        name: s.seller_name,
        ntn: s.fbr_ntn,
        strn: s.fbr_strn,
        address: s.seller_address,
        province: s.seller_province,
    };

    let fbr_res = if let Some(ref usin) = sale_with_items.sale.fbr_usin {
        printer::FbrResult::Submitted { usin: usin.clone() }
    } else {
        printer::FbrResult::Offline {
            provisional_ref: format!("PROV-{}-{}", s.fbr_pos_id, sale_with_items.sale.sale_number),
        }
    };

    state
        .printer
        .print_detailed_sale(&sale_with_items.sale, &sale_with_items.items, &fbr_res, &seller)
        .map_err(|e| e.to_string())
}

#[tauri::command]
fn get_settings(state: State<'_, AppState>) -> Result<db::PosSettings, String> {
    let conn = state.db.lock().map_err(|e| e.to_string())?;
    db::get_all_settings(&conn).map_err(|e| e.to_string())
}

#[tauri::command]
fn save_settings(state: State<'_, AppState>, settings: db::PosSettings) -> Result<(), String> {
    let conn = state.db.lock().map_err(|e| e.to_string())?;
    db::save_all_settings(&conn, &settings).map_err(|e| e.to_string())
}

#[tauri::command]
fn test_printer(state: State<'_, AppState>) -> Result<(), String> {
    state.printer.test_print().map_err(|e| e.to_string())
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .setup(|app| {
            let app_data_dir = app
                .path()
                .app_data_dir()
                .unwrap_or_else(|_| std::path::PathBuf::from("."));
            let _ = std::fs::create_dir_all(&app_data_dir);
            let db_path = app_data_dir.join("pos_client.db");

            let conn = rusqlite::Connection::open(&db_path)
                .map_err(|e| Box::new(e) as Box<dyn std::error::Error>)?;

            // 1. Run SQLite migrations
            db::run_migrations(&conn)
                .map_err(|e| Box::new(e) as Box<dyn std::error::Error>)?;

            // 2. Crash recovery: reset in-flight processing records to pending
            let _ = db::recover_in_flight_records(&conn);

            let db_arc = Arc::new(Mutex::new(conn));

            // 3. Launch background outbox worker
            let worker_db = Arc::clone(&db_arc);
            let base_url = std::env::var("POS_API_BASE_URL")
                .unwrap_or_else(|_| "http://127.0.0.1:8000/api/v1".to_string());

            tauri::async_runtime::spawn(async move {
                worker::start_outbox_worker(worker_db, base_url).await;
            });

            // 4. Initialize FBR client from environment/config
            let fbr_bearer_token = std::env::var("FBR_BEARER_TOKEN").unwrap_or_default();
            let fbr_pos_id = std::env::var("FBR_POS_ID").unwrap_or_else(|_| "POS-001".to_string());
            let fbr_base_url = std::env::var("FBR_BASE_URL")
                .unwrap_or_else(|_| "https://fbr-iris-sandbox.gov.pk".to_string());
            let fbr_ntn = std::env::var("FBR_NTN").unwrap_or_else(|_| "1234567-8".to_string());

            let fbr_client = fbr::FbrClient::new(fbr_bearer_token, fbr_pos_id, fbr_base_url)
                .with_ntn(fbr_ntn);

            let printer = printer::ReceiptPrinter::new(printer::PrinterConnection::Mock);

            app.manage(AppState {
                db: db_arc,
                fbr_client,
                printer,
                counter: AtomicU64::new(1),
            });

            Ok(())
        })
        .invoke_handler(tauri::generate_handler![
            get_pending_outbox_count,
            enqueue_sale,
            process_sale,
            open_cash_drawer,
            sync_catalog,
            get_cached_products,
            get_product_by_barcode,
            get_local_sales,
            get_local_sale_details,
            reprint_receipt,
            get_settings,
            save_settings,
            test_printer
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
