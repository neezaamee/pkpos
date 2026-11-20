export type PaymentMethod = 'cash' | 'card' | 'raast' | 'wallet' | 'credit' | 'split';

export interface SplitPaymentItem {
  payment_method: 'cash' | 'card' | 'raast' | 'wallet' | 'credit' | 'cheque';
  amount_minor: number;
  tendered_minor?: number;
  change_minor?: number;
  reference?: string;
  notes?: string;
}

export interface CustomerProfile {
  id: string;
  name: string;
  phone: string;
  email?: string;
  cnic?: string;
  ntn?: string;
  strn?: string;
  address?: string;
  city?: string;
  credit_limit_minor: number;
  current_balance_minor: number;
  price_list_id?: string;
  is_active: boolean;
}

export interface CustomerTransactionItem {
  id: string;
  customer_id: string;
  type: 'credit_sale' | 'payment' | 'adjustment';
  amount_minor: number;
  balance_after_minor: number;
  payment_method?: string;
  receipt_number?: string;
  notes?: string;
  created_at: string;
}

export interface ProductVariantItem {
  id: string;
  name: string;
  sku?: string;
  barcode?: string;
  price_override_minor?: number;
}

export interface ProductUomConversion {
  id: string;
  unit_name: string;
  unit_code: string;
  barcode?: string;
  conversion_factor: number;
  price_minor: number;
}

export interface CachedProduct {
  id: string;
  name: string;
  hs_code: string;
  price_minor: number;
  tax_category?: string;
  unit: string;
  barcode?: string;
  tax_rate_basis_points: number;
  last_synced_at?: string;
  variants?: ProductVariantItem[];
  uom_conversions?: ProductUomConversion[];
  allows_decimals?: boolean;
}

export interface CartItem {
  product: CachedProduct;
  variant?: ProductVariantItem;
  quantity: number;
  unit_price_minor: number;
  tax_rate_basis_points: number;
  tax_minor: number;
  total_minor: number;
  selected_uom?: ProductUomConversion;
  unit_code?: string;
  unit_conversion_factor?: number;
}

export type ProcessResult =
  | { status: 'Submitted'; data: { usin: string } }
  | { status: 'Offline'; data: { provisional_ref: string } }
  | { status: 'Rejected'; data: { reason: string } };

export interface LocalSaleSummary {
  id: string;
  sale_number: string;
  payment_method: string;
  subtotal_minor: number;
  tax_minor: number;
  total_minor: number;
  fbr_status: string;
  fbr_usin?: string;
  created_at: string;
  items_count: number;
}

export interface LocalSaleItem {
  id: string;
  sale_id: string;
  product_id?: string;
  product_name: string;
  hs_code: string;
  quantity: number;
  unit_price_minor: number;
  tax_rate_basis_points: number;
  tax_minor: number;
  total_minor: number;
}

export interface LocalSaleWithItems {
  sale: {
    id: string;
    sale_number: string;
    payment_method: string;
    subtotal_minor: number;
    tax_minor: number;
    total_minor: number;
    fbr_status: string;
    fbr_usin?: string;
    created_at: string;
  };
  items: LocalSaleItem[];
}

export interface PosSettings {
  api_base_url: string;
  api_token?: string;
  fbr_pos_id: string;
  fbr_bearer_token: string;
  fbr_ntn: string;
  fbr_strn: string;
  seller_name: string;
  seller_address: string;
  seller_province: string;
  printer_type: string;
  printer_ip: string;
  printer_port: number;
  terminal_code?: string;
  terminal_id?: string;
  server_updated_at?: string;
}

export interface CatalogSyncSummary {
  synced_count: number;
  server_updated_at: string;
  offline: boolean;
  message: string;
}

export function formatPKR(minorUnits: number): string {
  const rupees = minorUnits / 100;
  return 'Rs. ' + rupees.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export interface CashierUser {
  id: string;
  name: string;
  email: string;
  role: string;
  max_discount_pct: number;
  pos_permissions: string[];
  is_supervisor: boolean;
}

export interface RegisterShift {
  id: string;
  tenant_id: string;
  location_id: string;
  terminal_id?: string;
  user_id: string;
  status: 'open' | 'closed';
  opened_at: string;
  closed_at?: string;
  opening_float_minor: number;
  cash_sales_minor: number;
  expected_cash_minor: number;
  closing_cash_minor?: number;
  variance_minor?: number;
  sales_count: number;
  z_report_number?: string;
  notes?: string;
}

export interface ShiftReportSummary {
  report_type: 'X' | 'Z';
  report_title: string;
  session_id: string;
  z_report_number: string;
  status: string;
  opened_at?: string;
  closed_at?: string;
  cashier_name: string;
  closed_by_name?: string;
  location_name: string;
  terminal_code: string;
  opening_float_minor: number;
  gross_sales_minor: number;
  net_sales_minor: number;
  tax_collected_minor: number;
  sales_count: number;
  refunds_count: number;
  payment_breakdown: {
    cash_minor: number;
    card_minor: number;
    raast_minor: number;
    wallet_minor: number;
  };
  cash_drawer_reconciliation: {
    opening_float: number;
    cash_sales: number;
    cash_in: number;
    cash_drops: number;
    payout_expenses: number;
    cash_refunds: number;
    expected_cash: number;
    counted_cash?: number;
    variance?: number;
    variance_status: 'exact' | 'overage' | 'shortage';
  };
  notes?: string;
}
