export type PaymentMethod = 'cash' | 'card' | 'raast' | 'wallet';

export interface ProductVariantItem {
  id: string;
  name: string;
  sku?: string;
  barcode?: string;
  price_override_minor?: number;
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
}

export interface CartItem {
  product: CachedProduct;
  variant?: ProductVariantItem;
  quantity: number;
  unit_price_minor: number;
  tax_rate_basis_points: number;
  tax_minor: number;
  total_minor: number;
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
