use crate::db::LocalSale;
use serde::{Deserialize, Serialize};
use thiserror::Error;

#[derive(Error, Debug)]
pub enum PrinterError {
    #[error("Network error: {0}")]
    Network(#[from] std::io::Error),
    #[error("Device communication error: {0}")]
    Device(String),
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
pub enum FbrResult {
    Submitted { usin: String },
    Offline { provisional_ref: String },
}

impl From<&crate::fbr::ProcessResult> for Option<FbrResult> {
    fn from(res: &crate::fbr::ProcessResult) -> Self {
        match res {
            crate::fbr::ProcessResult::Submitted { usin } => {
                Some(FbrResult::Submitted { usin: usin.clone() })
            }
            crate::fbr::ProcessResult::Offline { provisional_ref } => Some(FbrResult::Offline {
                provisional_ref: provisional_ref.clone(),
            }),
            crate::fbr::ProcessResult::Rejected { .. } => None,
        }
    }
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub enum PrinterConnection {
    Network { host: String, port: u16 },
    Usb { vendor_id: u16, product_id: u16 },
    Mock,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct SellerInfo {
    pub name: String,
    pub ntn: String,
    pub strn: String,
    pub address: String,
    pub province: String,
}

impl Default for SellerInfo {
    fn default() -> Self {
        Self {
            name: "Pakistan POS Store".to_string(),
            ntn: "1234567-8".to_string(),
            strn: "12-34-5678-001-99".to_string(),
            address: "Main Boulevard, Lahore".to_string(),
            province: "Punjab".to_string(),
        }
    }
}

pub fn map_payment_mode_fbr(method: &str) -> &'static str {
    match method.to_lowercase().as_str() {
        "cash" | "1" => "1 (Cash)",
        "card" | "2" => "2 (Card)",
        "raast" | "3" => "3 (Raast)",
        "wallet" | "mobile_wallet" | "4" => "4 (Mobile Wallet)",
        _ => "1 (Cash)",
    }
}

pub fn format_invoice_date(iso_str: &str) -> String {
    if let Ok(dt) = chrono::DateTime::parse_from_rfc3339(iso_str) {
        dt.format("%Y-%m-%d %H:%M:%S").to_string()
    } else if let Ok(naive) = chrono::NaiveDateTime::parse_from_str(iso_str, "%Y-%m-%d %H:%M:%S") {
        naive.format("%Y-%m-%d %H:%M:%S").to_string()
    } else {
        chrono::Utc::now().format("%Y-%m-%d %H:%M:%S").to_string()
    }
}

pub struct ReceiptPrinter {
    pub connection: PrinterConnection,
}

impl ReceiptPrinter {
    pub fn new(connection: PrinterConnection) -> Self {
        Self { connection }
    }

    /// Builds the ESC/POS cash drawer kick command bytes: ESC p 0 25 250.
    pub fn build_drawer_kick_bytes() -> Vec<u8> {
        vec![0x1B, 0x70, 0x00, 0x19, 0xFA]
    }

    /// Triggers the cash drawer to open.
    pub fn open_drawer(&self) -> Result<(), PrinterError> {
        let bytes = Self::build_drawer_kick_bytes();
        self.send_bytes(&bytes)
    }

    /// Generates native ESC/POS QR code command sequence (GS ( k) for Epson-compatible printers.
    /// Does NOT render QR as a bitmap.
    pub fn build_native_qr_bytes(data: &str) -> Vec<u8> {
        let mut bytes = Vec::new();

        // 1. Select QR Model 2: GS ( k 4 0 49 65 50 0
        bytes.extend_from_slice(&[0x1D, 0x28, 0x6B, 0x04, 0x00, 0x31, 0x41, 0x32, 0x00]);

        // 2. Set module size to 5 (~0.8 inch for 25x25): GS ( k 3 0 49 67 5
        bytes.extend_from_slice(&[0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x43, 0x05]);

        // 3. Set Error Correction Level M: GS ( k 3 0 49 69 49
        bytes.extend_from_slice(&[0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x45, 0x31]);

        // 4. Store data in symbol storage area: GS ( k pL pH 49 80 48 <data>
        let data_len = data.len() + 3;
        let p_l = (data_len & 0xFF) as u8;
        let p_h = ((data_len >> 8) & 0xFF) as u8;

        bytes.extend_from_slice(&[0x1D, 0x28, 0x6B, p_l, p_h, 0x31, 0x50, 0x30]);
        bytes.extend_from_slice(data.as_bytes());

        // 5. Print the stored QR code: GS ( k 3 0 49 81 48
        bytes.extend_from_slice(&[0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x51, 0x30]);

        bytes
    }

    /// Builds the complete ESC/POS byte sequence for a sale receipt with detailed items and seller info.
    pub fn build_detailed_receipt_bytes(
        &self,
        sale: &LocalSale,
        items: &[crate::db::LocalSaleItem],
        fbr_result: &FbrResult,
        seller: &SellerInfo,
    ) -> Vec<u8> {
        let mut b = Vec::new();

        // Initialize printer: ESC @
        b.extend_from_slice(&[0x1B, 0x40]);

        // Center align: ESC a 1
        b.extend_from_slice(&[0x1B, 0x61, 0x01]);

        // Seller Header
        b.extend_from_slice(format!("{}\n", seller.name).as_bytes());
        b.extend_from_slice(format!("NTN: {} | STRN: {}\n", seller.ntn, seller.strn).as_bytes());
        b.extend_from_slice(format!("{}, {}\n", seller.address, seller.province).as_bytes());

        let formatted_date = format_invoice_date(&sale.created_at);
        let payment_mode = map_payment_mode_fbr(&sale.payment_method);

        match fbr_result {
            FbrResult::Submitted { usin } => {
                b.extend_from_slice(b"Official Sales Tax Invoice\n\n");

                // Left align
                b.extend_from_slice(&[0x1B, 0x61, 0x00]);
                b.extend_from_slice(format!("FBR USIN:     {}\n", usin).as_bytes());
                b.extend_from_slice(format!("Invoice No:   {}\n", sale.sale_number).as_bytes());
                b.extend_from_slice(format!("Date/Time:    {}\n", formatted_date).as_bytes());
                b.extend_from_slice(format!("Payment Mode: {}\n", payment_mode).as_bytes());

                // Line items
                if !items.is_empty() {
                    b.extend_from_slice(b"--------------------------------\n");
                    b.extend_from_slice(b"Item / PCT Code\nQty x Price       Tax     Total\n");
                    b.extend_from_slice(b"--------------------------------\n");
                    for it in items {
                        let unit_price = it.unit_price_minor as f64 / 100.0;
                        let line_tax = it.tax_minor as f64 / 100.0;
                        let line_total = it.total_minor as f64 / 100.0;
                        let rate_pct = it.tax_rate_basis_points as f64 / 100.0;
                        b.extend_from_slice(format!("{}\n", it.product_name).as_bytes());
                        b.extend_from_slice(format!("PCT: {}\n", it.hs_code).as_bytes());
                        b.extend_from_slice(
                            format!(
                                "{}x Rs.{:.2} ({:.1}%) Rs.{:.2} Rs.{:.2}\n",
                                it.quantity, unit_price, rate_pct, line_tax, line_total
                            )
                            .as_bytes(),
                        );
                    }
                }

                b.extend_from_slice(b"--------------------------------\n");

                let subtotal = sale.subtotal_minor as f64 / 100.0;
                let tax = sale.tax_minor as f64 / 100.0;
                let total = sale.total_minor as f64 / 100.0;

                b.extend_from_slice(format!("Subtotal:          Rs. {:>9.2}\n", subtotal).as_bytes());
                b.extend_from_slice(format!("Total Sales Tax:   Rs. {:>9.2}\n", tax).as_bytes());
                b.extend_from_slice(format!("Total Retail Price:Rs. {:>9.2}\n", total).as_bytes());
                b.extend_from_slice(b"--------------------------------\n\n");

                // Center align for native QR code
                b.extend_from_slice(&[0x1B, 0x61, 0x01]);
                let qr_data = format!("USIN:{};INV:{}", usin, sale.sale_number);
                b.extend_from_slice(&Self::build_native_qr_bytes(&qr_data));

                b.extend_from_slice(b"\nVerify via Tax Asaan app\n");
            }
            FbrResult::Offline { provisional_ref } => {
                b.extend_from_slice(b"*** PROVISIONAL / OFFLINE ***\n\n");

                // Left align
                b.extend_from_slice(&[0x1B, 0x61, 0x00]);
                b.extend_from_slice(format!("Provisional Ref: {}\n", provisional_ref).as_bytes());
                b.extend_from_slice(format!("Sale No:         {}\n", sale.sale_number).as_bytes());
                b.extend_from_slice(format!("Date/Time:       {}\n", formatted_date).as_bytes());
                b.extend_from_slice(format!("Payment Mode:    {}\n", payment_mode).as_bytes());

                // Line items
                if !items.is_empty() {
                    b.extend_from_slice(b"--------------------------------\n");
                    b.extend_from_slice(b"Item / PCT Code\nQty x Price       Tax     Total\n");
                    b.extend_from_slice(b"--------------------------------\n");
                    for it in items {
                        let unit_price = it.unit_price_minor as f64 / 100.0;
                        let line_tax = it.tax_minor as f64 / 100.0;
                        let line_total = it.total_minor as f64 / 100.0;
                        let rate_pct = it.tax_rate_basis_points as f64 / 100.0;
                        b.extend_from_slice(format!("{}\n", it.product_name).as_bytes());
                        b.extend_from_slice(format!("PCT: {}\n", it.hs_code).as_bytes());
                        b.extend_from_slice(
                            format!(
                                "{}x Rs.{:.2} ({:.1}%) Rs.{:.2} Rs.{:.2}\n",
                                it.quantity, unit_price, rate_pct, line_tax, line_total
                            )
                            .as_bytes(),
                        );
                    }
                }

                b.extend_from_slice(b"--------------------------------\n");

                let subtotal = sale.subtotal_minor as f64 / 100.0;
                let tax = sale.tax_minor as f64 / 100.0;
                let total = sale.total_minor as f64 / 100.0;

                b.extend_from_slice(format!("Subtotal:          Rs. {:>9.2}\n", subtotal).as_bytes());
                b.extend_from_slice(format!("Total Sales Tax:   Rs. {:>9.2}\n", tax).as_bytes());
                b.extend_from_slice(format!("Total Retail Price:Rs. {:>9.2}\n", total).as_bytes());
                b.extend_from_slice(b"--------------------------------\n\n");

                // Center align for native QR code with provisional reference
                b.extend_from_slice(&[0x1B, 0x61, 0x01]);
                b.extend_from_slice(&Self::build_native_qr_bytes(provisional_ref));

                // Mandated explanation notice (NO FBR logo, NO Tax Asaan app verification)
                b.extend_from_slice(
                    b"\nThis receipt was generated in offline mode. The invoice number is provisional. It will be submitted to FBR within 24 hours. The QR code cannot be verified temporarily.\n",
                );
            }
        }

        // Feed & cut paper: GS V 65 0
        b.extend_from_slice(&[0x1D, 0x56, 0x41, 0x00]);

        b
    }

    /// Builds standard receipt bytes with default seller info and without line items (backward compatibility).
    pub fn build_receipt_bytes(&self, sale: &LocalSale, fbr_result: &FbrResult) -> Vec<u8> {
        self.build_detailed_receipt_bytes(sale, &[], fbr_result, &SellerInfo::default())
    }

    /// Prints a sale receipt.
    pub fn print_sale(&self, sale: &LocalSale, fbr_result: &FbrResult) -> Result<(), PrinterError> {
        let bytes = self.build_receipt_bytes(sale, fbr_result);
        self.send_bytes(&bytes)
    }

    /// Prints a detailed sale receipt with items and seller info.
    pub fn print_detailed_sale(
        &self,
        sale: &LocalSale,
        items: &[crate::db::LocalSaleItem],
        fbr_result: &FbrResult,
        seller: &SellerInfo,
    ) -> Result<(), PrinterError> {
        let bytes = self.build_detailed_receipt_bytes(sale, items, fbr_result, seller);
        self.send_bytes(&bytes)
    }

    /// Prints a test receipt to verify connection.
    pub fn test_print(&self) -> Result<(), PrinterError> {
        let mut b = Vec::new();
        b.extend_from_slice(&[0x1B, 0x40]); // Init
        b.extend_from_slice(&[0x1B, 0x61, 0x01]); // Center
        b.extend_from_slice(b"*** TEST RECEIPT ***\n");
        b.extend_from_slice(b"PAKISTAN POS FOUNDATION\n");
        b.extend_from_slice(b"Printer connection is OK\n");
        b.extend_from_slice(format!("Time: {}\n\n", chrono::Utc::now().to_rfc3339()).as_bytes());
        b.extend_from_slice(&[0x1D, 0x56, 0x41, 0x00]); // Cut
        self.send_bytes(&b)
    }

    fn send_bytes(&self, bytes: &[u8]) -> Result<(), PrinterError> {
        match &self.connection {
            PrinterConnection::Network { host, port } => {
                use std::io::Write;
                use std::net::TcpStream;
                let addr = format!("{}:{}", host, port);
                let socket_addr = addr
                    .parse()
                    .map_err(|e| PrinterError::Device(format!("{}", e)))?;
                let mut stream =
                    TcpStream::connect_timeout(&socket_addr, std::time::Duration::from_secs(3))?;
                stream.write_all(bytes)?;
                stream.flush()?;
                Ok(())
            }
            PrinterConnection::Usb { .. } => Ok(()),
            PrinterConnection::Mock => Ok(()),
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn create_test_sale() -> LocalSale {
        LocalSale {
            id: "sale-uuid-123".to_string(),
            sale_number: "INV-2026-00042".to_string(),
            payment_method: "card".to_string(),
            subtotal_minor: 150000,
            tax_minor: 27000,
            total_minor: 177000,
            fbr_status: "submitted".to_string(),
            fbr_usin: Some("USIN-PAK-998877".to_string()),
            created_at: "2026-10-22T10:00:00Z".to_string(),
        }
    }

    #[test]
    fn test_receipt_byte_generation_and_assertions() {
        let printer = ReceiptPrinter::new(PrinterConnection::Mock);
        let sale = create_test_sale();

        // 1. Test Submitted Receipt
        let submitted_result = FbrResult::Submitted {
            usin: "USIN-PAK-998877".to_string(),
        };
        let submitted_bytes = printer.build_receipt_bytes(&sale, &submitted_result);
        let submitted_str = String::from_utf8_lossy(&submitted_bytes);

        // Asserts submitted receipt contains the USIN
        assert!(
            submitted_str.contains("USIN-PAK-998877"),
            "Submitted receipt must contain the USIN"
        );
        // Asserts Tax Asaan verification is included
        assert!(submitted_str.contains("Verify via Tax Asaan app"));

        // Asserts native QR command bytes (GS ( k: 0x1D, 0x28, 0x6B) are present
        let qr_prefix: &[u8] = &[0x1D, 0x28, 0x6B];
        assert!(
            submitted_bytes
                .windows(qr_prefix.len())
                .any(|w| w == qr_prefix),
            "Native QR command bytes GS ( k must be present in submitted receipt"
        );

        // 2. Test Provisional / Offline Receipt
        let provisional_result = FbrResult::Offline {
            provisional_ref: "PROV-1234567-8-POS-001-20261022-000042".to_string(),
        };
        let provisional_bytes = printer.build_receipt_bytes(&sale, &provisional_result);
        let provisional_str = String::from_utf8_lossy(&provisional_bytes);

        // Asserts provisional receipt contains the word "PROVISIONAL"
        assert!(
            provisional_str.contains("PROVISIONAL"),
            "Provisional receipt must contain the word 'PROVISIONAL'"
        );
        assert!(provisional_str.contains("PROV-1234567-8-POS-001-20261022-000042"));
        assert!(provisional_str.contains("This receipt was generated in offline mode."));

        // Asserts provisional receipt does NOT contain "Verify via Tax Asaan"
        assert!(
            !provisional_str.contains("Verify via Tax Asaan"),
            "Provisional receipt must NOT contain 'Verify via Tax Asaan'"
        );

        // Asserts native QR command bytes are present in provisional receipt
        assert!(
            provisional_bytes
                .windows(qr_prefix.len())
                .any(|w| w == qr_prefix),
            "Native QR command bytes GS ( k must be present in provisional receipt"
        );

        // 3. Test Drawer Kick Command
        let drawer_bytes = ReceiptPrinter::build_drawer_kick_bytes();
        assert_eq!(
            drawer_bytes,
            vec![0x1B, 0x70, 0x00, 0x19, 0xFA],
            "Drawer kick bytes must match ESC p 0 25 250"
        );
    }

    #[test]
    fn test_detailed_fbr_receipt_compliance() {
        let printer = ReceiptPrinter::new(PrinterConnection::Mock);
        let sale = create_test_sale();

        let seller = SellerInfo {
            name: "Punjab Hypermarket".to_string(),
            ntn: "7654321-9".to_string(),
            strn: "98-76-5432-100-00".to_string(),
            address: "Gulberg III, Lahore".to_string(),
            province: "Punjab".to_string(),
        };

        let items = vec![
            crate::db::LocalSaleItem {
                id: "i1".to_string(),
                sale_id: sale.id.clone(),
                product_id: Some("p1".to_string()),
                product_name: "Super Basmati Rice".to_string(),
                hs_code: "10063010".to_string(),
                quantity: 2,
                unit_price_minor: 35000,
                tax_rate_basis_points: 1800,
                tax_minor: 12600,
                total_minor: 82600,
            },
            crate::db::LocalSaleItem {
                id: "i2".to_string(),
                sale_id: sale.id.clone(),
                product_id: Some("p2".to_string()),
                product_name: "Cooking Oil 1L".to_string(),
                hs_code: "15121900".to_string(),
                quantity: 1,
                unit_price_minor: 55000,
                tax_rate_basis_points: 1800,
                tax_minor: 9900,
                total_minor: 64900,
            },
        ];

        // 1. Submitted Sale Detailed Receipt
        let submitted_res = FbrResult::Submitted {
            usin: "USIN-LIVE-778899".to_string(),
        };
        let bytes = printer.build_detailed_receipt_bytes(&sale, &items, &submitted_res, &seller);
        let text = String::from_utf8_lossy(&bytes);

        // Seller assertions
        assert!(text.contains("Punjab Hypermarket"));
        assert!(text.contains("NTN: 7654321-9"));
        assert!(text.contains("STRN: 98-76-5432-100-00"));
        assert!(text.contains("Gulberg III, Lahore, Punjab"));

        // Items and PCT code assertions
        assert!(text.contains("Super Basmati Rice"));
        assert!(text.contains("PCT: 10063010"));
        assert!(text.contains("Cooking Oil 1L"));
        assert!(text.contains("PCT: 15121900"));

        // Payment Mode & Date format (YYYY-MM-DD HH:mm:ss)
        assert!(text.contains("Payment Mode: 2 (Card)"));
        assert!(text.contains("2026-10-22 10:00:00"));

        // Totals
        assert!(text.contains("Total Sales Tax:"));
        assert!(text.contains("Total Retail Price:"));
        assert!(text.contains("Verify via Tax Asaan app"));

        // 2. Offline Provisional Detailed Receipt
        let offline_res = FbrResult::Offline {
            provisional_ref: "PROV-7654321-9-POS-001-20261022-000001".to_string(),
        };
        let off_bytes = printer.build_detailed_receipt_bytes(&sale, &items, &offline_res, &seller);
        let off_text = String::from_utf8_lossy(&off_bytes);

        assert!(off_text.contains("*** PROVISIONAL / OFFLINE ***"));
        assert!(off_text.contains("PROV-7654321-9-POS-001-20261022-000001"));
        assert!(!off_text.contains("Verify via Tax Asaan app"));
        assert!(off_text.contains("This receipt was generated in offline mode. The invoice number is provisional. It will be submitted to FBR within 24 hours. The QR code cannot be verified temporarily."));
    }
}
