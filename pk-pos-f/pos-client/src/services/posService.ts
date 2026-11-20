import type {
  CachedProduct,
  CatalogSyncSummary,
  CustomerProfile,
  CustomerTransactionItem,
  LocalSaleSummary,
  LocalSaleWithItems,
  PosSettings,
  ProcessResult,
} from '../types/pos';

export const APP_VERSION = '0.1.1';

export function isAndroidDevice(): boolean {
  return typeof navigator !== 'undefined' && /android/i.test(navigator.userAgent);
}

function isTauri(): boolean {
  return (
    typeof window !== 'undefined' &&
    ('__TAURI_INTERNALS__' in window || '__TAURI__' in window)
  );
}

// In-memory catalog products cache
const mockProducts: CachedProduct[] = [];

function getStoredSales(): LocalSaleWithItems[] {
  if (typeof window !== 'undefined' && window.localStorage) {
    try {
      const s = localStorage.getItem('pk_pos_local_sales');
      if (s) return JSON.parse(s);
    } catch (e) {
      console.error('Failed to read sales from localStorage', e);
    }
  }
  return [];
}

function saveStoredSales(sales: LocalSaleWithItems[]): void {
  if (typeof window !== 'undefined' && window.localStorage) {
    try {
      localStorage.setItem('pk_pos_local_sales', JSON.stringify(sales));
    } catch (e) {
      console.error('Failed to write sales to localStorage', e);
    }
  }
}

let catalogAutoSyncTriggered = false;

export async function getCachedProducts(query?: string): Promise<CachedProduct[]> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<CachedProduct[]>('get_cached_products', { query });
  }

  // Auto-sync catalog in background once if running in browser
  if (!catalogAutoSyncTriggered) {
    catalogAutoSyncTriggered = true;
    syncCatalog().catch(() => {});
  }

  // Browser fallback with localStorage cache
  let list: CachedProduct[] = [];
  if (typeof window !== 'undefined' && window.localStorage) {
    try {
      const cached = localStorage.getItem('pk_pos_cached_products');
      if (cached) {
        const parsed = JSON.parse(cached);
        if (Array.isArray(parsed)) {
          list = parsed;
        }
      }
    } catch (e) {
      console.error('Failed to parse cached products', e);
    }
  }

  // Fallback to in-memory mock products only if localStorage was never set
  if (list.length === 0 && mockProducts.length > 0) {
    list = [...mockProducts];
  }

  if (!query || query.trim() === '') {
    return list;
  }
  const q = query.toLowerCase().trim();
  return list.filter(
    (p) =>
      p.name.toLowerCase().includes(q) ||
      (p.barcode && p.barcode.includes(q)) ||
      p.hs_code.includes(q) ||
      (p.generic_name && p.generic_name.toLowerCase().includes(q)) ||
      (p.drap_registration_number && p.drap_registration_number.toLowerCase().includes(q))
  );
}

export interface BarcodeLookupResult {
  product: CachedProduct;
  packUom?: import('../types/pos').ProductUomConversion;
}

export async function getProductByBarcode(barcode: string): Promise<BarcodeLookupResult | null> {
  const trimmed = barcode.trim();
  if (!trimmed) return null;

  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    const prod = await invoke<CachedProduct | null>('get_product_by_barcode', { barcode: trimmed });
    return prod ? { product: prod } : null;
  }

  const all = await getCachedProducts();
  for (const p of all) {
    if (p.barcode === trimmed) {
      return { product: p };
    }
    if (p.uom_conversions && p.uom_conversions.length > 0) {
      const match = p.uom_conversions.find((u) => u.barcode === trimmed);
      if (match) {
        return { product: p, packUom: match };
      }
    }
  }
  return null;
}

export async function syncCatalog(): Promise<CatalogSyncSummary> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<CatalogSyncSummary>('sync_catalog');
  }

  // Browser dev mode: sync directly from central Laravel backend
  const settings = await getSettings();
  const baseUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  try {
    const headers: Record<string, string> = {
      Accept: 'application/json',
    };
    if (settings.api_token) {
      headers['Authorization'] = `Bearer ${settings.api_token}`;
    }
    if (settings.terminal_code) {
      headers['X-Terminal-Code'] = settings.terminal_code;
    }
    const response = await fetch(`${baseUrl}/catalog/sync`, { headers });
    if (response.ok) {
      const data = await response.json();
      if (Array.isArray(data.products)) {
        mockProducts.length = 0;
        for (const item of data.products) {
          mockProducts.push({
            id: item.id,
            name: item.name,
            hs_code: item.hs_code,
            price_minor: item.price_minor,
            tax_category: item.tax_category || 'general_goods',
            unit: item.unit || 'piece',
            barcode: item.barcode || '',
            tax_rate_basis_points: item.tax_rate_basis_points || 1800,
            generic_name: item.generic_name,
            drap_registration_number: item.drap_registration_number,
            dosage_form: item.dosage_form,
            strength: item.strength,
            manufacturer: item.manufacturer,
            is_prescription_required: item.is_prescription_required,
            is_narcotic: item.is_narcotic,
            cold_chain: item.cold_chain,
            rack_location: item.rack_location,
            fefo_batch: item.fefo_batch,
            variants: item.variants || [],
            uom_conversions: item.uom_conversions || [],
            allows_decimals: item.allows_decimals ?? (item.unit === 'kg' || item.unit === 'gram' || item.unit === 'litre' || item.unit === 'meter'),
            last_synced_at: item.updated_at || new Date().toISOString(),
          });
        }
        if (typeof window !== 'undefined' && window.localStorage) {
          localStorage.setItem('pk_pos_cached_products', JSON.stringify(mockProducts));
        }
      }

      if (data.tenant) {
        settings.seller_name = data.tenant.name || settings.seller_name;
        settings.fbr_ntn = data.tenant.ntn || settings.fbr_ntn;
        settings.fbr_strn = data.tenant.strn || settings.fbr_strn;
        settings.fbr_pos_id = data.tenant.fbr_pos_id || settings.fbr_pos_id;
        settings.fbr_bearer_token = data.tenant.fbr_bearer_token || settings.fbr_bearer_token;
        if (data.tenant.address) settings.seller_address = data.tenant.address;
        if (data.tenant.province) settings.seller_province = data.tenant.province;
        await saveSettings(settings);
      }

      return {
        synced_count: data.products?.length || 0,
        server_updated_at: data.server_updated_at || new Date().toISOString(),
        offline: false,
        message: `Successfully synced ${data.products?.length || 0} products & FBR credentials from Laravel backend.`,
      };
    } else {
      const errText = await response.text();
      let errorMsg = `Server returned HTTP ${response.status}`;
      try {
        const parsed = JSON.parse(errText);
        if (parsed.message) errorMsg = parsed.message;
      } catch (e) {}

      return {
        synced_count: mockProducts.length,
        server_updated_at: new Date().toISOString(),
        offline: true,
        message: `Sync failed: ${errorMsg}`,
      };
    }
  } catch (err: any) {
    console.warn('Backend sync failed, using fallback:', err);
    return {
      synced_count: mockProducts.length,
      server_updated_at: new Date().toISOString(),
      offline: true,
      message: `Backend connection error: ${err?.message || 'Server unreachable'}. Using local cache.`,
    };
  }
}

export async function processSale(saleJson: string): Promise<ProcessResult> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<ProcessResult>('process_sale', { saleJson });
  }

  // Browser dev mode with live central backend sync
  const parsed = JSON.parse(saleJson);
  const settings = await getSettings();
  const token = settings.api_token;
  const cleanUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  const salePayload = {
    id: parsed.id,
    sale_number: parsed.sale_number,
    payment_method: parsed.payment_method,
    payments: parsed.payments || undefined,
    subtotal_minor: parsed.subtotal_minor,
    tax_minor: parsed.tax_minor,
    total_minor: parsed.total_minor,
    customer_id: parsed.customer_id || undefined,
    buyer_name: parsed.buyer_name || undefined,
    buyer_phone: parsed.buyer_phone || undefined,
    buyer_cnic: parsed.buyer_cnic || undefined,
    buyer_ntn: parsed.buyer_ntn || undefined,
    prescription: parsed.prescription || undefined,
    status: 'completed',
    fbr_status: 'submitted',
    fbr_usin: 'USIN-MOCK-' + Math.floor(100000 + Math.random() * 900000),
    created_at: new Date().toISOString(),
    items: (parsed.items || []).map((item: any) => ({
      id: item.id || crypto.randomUUID(),
      product_id: item.product_id,
      product_name: item.product_name,
      hs_code: item.hs_code,
      unit_code: item.unit_code || 'pcs',
      unit_conversion_factor: item.unit_conversion_factor ?? 1,
      quantity: item.quantity,
      unit_price_minor: item.unit_price_minor,
      tax_rate_basis_points: item.tax_rate_basis_points,
      tax_minor: item.tax_minor ?? item.tax_amount_minor ?? 0,
      tax_amount_minor: item.tax_amount_minor ?? item.tax_minor ?? 0,
      total_minor: item.total_minor ?? item.line_total_minor ?? 0,
      line_total_minor: item.line_total_minor ?? item.total_minor ?? 0,
      discount_type: item.discount_type || 'none',
      discount_amount_minor: item.discount_amount_minor || 0,
      batch_id: item.batch_id || undefined,
      batch_number: item.batch_number || undefined,
      expiry_date: item.expiry_date || undefined,
    })),
  };

  let syncedWithBackend = false;
  if (token) {
    try {
      const response = await fetch(`${cleanUrl}/sales/sync`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`,
          'Idempotency-Key': salePayload.id,
          ...(settings.terminal_code ? { 'X-Terminal-Code': settings.terminal_code } : {}),
        },
        body: JSON.stringify({ sales: [salePayload] }),
      });
      if (response.ok) {
        const syncData = await response.json();
        if (syncData.synced > 0) {
          syncedWithBackend = true;
        } else if (syncData.errors && syncData.errors.length > 0) {
          console.warn('Backend rejected sale sync:', syncData.errors);
        }
      } else {
        console.warn('Sale sync to central backend returned status:', response.status);
      }
    } catch (err) {
      console.warn('Network error syncing sale to backend (offline mode):', err);
    }
  }

  const saleRecord: LocalSaleWithItems = {
    sale: {
      id: salePayload.id,
      sale_number: salePayload.sale_number,
      payment_method: salePayload.payment_method,
      subtotal_minor: salePayload.subtotal_minor,
      tax_minor: salePayload.tax_minor,
      total_minor: salePayload.total_minor,
      fbr_status: syncedWithBackend ? 'submitted' : 'provisional',
      fbr_usin: salePayload.fbr_usin,
      created_at: salePayload.created_at,
    },
    items: parsed.items || [],
  };

  const stored = getStoredSales();
  stored.unshift(saleRecord);
  saveStoredSales(stored);

  if (syncedWithBackend) {
    return {
      status: 'Submitted',
      data: { usin: saleRecord.sale.fbr_usin! },
    };
  }

  return {
    status: 'Offline',
    data: { provisional_ref: saleRecord.sale.sale_number },
  };
}

export async function getLocalSales(limit = 50): Promise<LocalSaleSummary[]> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<LocalSaleSummary[]>('get_local_sales', { limit });
  }

  const sales = getStoredSales();
  return sales.slice(0, limit).map((s) => ({
    id: s.sale.id,
    sale_number: s.sale.sale_number,
    payment_method: s.sale.payment_method,
    subtotal_minor: s.sale.subtotal_minor,
    tax_minor: s.sale.tax_minor,
    total_minor: s.sale.total_minor,
    fbr_status: s.sale.fbr_status,
    fbr_usin: s.sale.fbr_usin,
    created_at: s.sale.created_at,
    items_count: s.items.length,
  }));
}

export async function getLocalSaleDetails(saleId: string): Promise<LocalSaleWithItems> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<LocalSaleWithItems>('get_local_sale_details', { saleId });
  }

  const found = getStoredSales().find((s) => s.sale.id === saleId);
  if (!found) throw new Error('Sale not found');
  return found;
}

export async function reprintReceipt(saleId: string): Promise<void> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<void>('reprint_receipt', { saleId });
  }
}

export async function clearLocalSales(): Promise<void> {
  if (isTauri()) {
    try {
      const { invoke } = await import('@tauri-apps/api/core');
      await invoke('clear_local_sales');
    } catch (e) {
      console.error('Failed to clear SQLite sales', e);
    }
  }
  if (typeof window !== 'undefined' && window.localStorage) {
    localStorage.removeItem('pk_pos_local_sales');
    localStorage.removeItem('pk_pos_held_sales');
  }
}

export async function getSettings(): Promise<PosSettings> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<PosSettings>('get_settings');
  }

  // Browser fallback with localStorage persistence
  let local: Partial<PosSettings> = {};
  if (typeof window !== 'undefined' && window.localStorage) {
    try {
      const stored = localStorage.getItem('pk_pos_settings');
      if (stored) {
        local = JSON.parse(stored);
      }
    } catch (e) {
      console.error('Failed to read settings from localStorage', e);
    }
  }

  const result: PosSettings = {
    api_base_url: local.api_base_url || 'https://01pos.01output.com/api/v1',
    api_token: local.api_token || '',
    terminal_code: local.terminal_code || '',
    terminal_id: local.terminal_id || '',
    fbr_pos_id: local.fbr_pos_id || 'POS-001',
    fbr_bearer_token: local.fbr_bearer_token || '',
    fbr_ntn: local.fbr_ntn || '1234567-8',
    fbr_strn: local.fbr_strn || '12-34-5678-001-99',
    seller_name: local.seller_name || 'Apex Retailers Pakistan',
    seller_address: local.seller_address || 'Plot 5, Main Boulevard, Lahore',
    seller_province: local.seller_province || 'Punjab',
    printer_type: local.printer_type || 'mock',
    printer_ip: local.printer_ip || '127.0.0.1',
    printer_port: local.printer_port || 9100,
  };

  // Attempt to fetch current tenant config and auto-provision register token from backend if reachable
  try {
    const cleanUrl = result.api_base_url.replace(/\/$/, '');
    const headers: Record<string, string> = {};
    if (result.api_token) {
      headers['Authorization'] = `Bearer ${result.api_token}`;
    }
    const res = await fetch(`${cleanUrl}/tenant/config`, { headers });
    if (res.ok) {
      const data = await res.json();
      result.fbr_pos_id = data.pos_id || result.fbr_pos_id;
      result.fbr_bearer_token = data.bearer_token || result.fbr_bearer_token;
      result.fbr_ntn = data.ntn || result.fbr_ntn;
      result.fbr_strn = data.strn || result.fbr_strn;
      if (data.register_token && !result.api_token) {
        result.api_token = data.register_token;
      }
      if (data.name) result.seller_name = data.name;
      if (data.province) result.seller_province = data.province;
      if (typeof window !== 'undefined' && window.localStorage) {
        localStorage.setItem('pk_pos_settings', JSON.stringify(result));
      }
    }
  } catch (err) {
    // Offline or network error, fallback to cached settings
  }

  return result;
}

export async function saveSettings(settings: PosSettings): Promise<void> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<void>('save_settings', { settings });
  }

  if (typeof window !== 'undefined' && window.localStorage) {
    try {
      localStorage.setItem('pk_pos_settings', JSON.stringify(settings));
    } catch (e) {
      console.error('Failed to save settings to localStorage', e);
    }
  }
}

export async function testPrinter(): Promise<void> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<void>('test_printer');
  }
}

export async function openCashDrawer(): Promise<void> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<void>('open_cash_drawer');
  }
}

export interface OutboxSyncResult {
  success: boolean;
  synced_count: number;
  failed_count: number;
  remaining_count: number;
  message: string;
}

export async function getPendingOutboxCount(): Promise<number> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<number>('get_pending_outbox_count');
  }
  const sales = getStoredSales();
  return sales.filter((s) => s.sale.fbr_status === 'provisional').length;
}

export async function syncOutboxNow(): Promise<OutboxSyncResult> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<OutboxSyncResult>('sync_outbox_now');
  }

  // Browser fallback
  const settings = await getSettings();
  const token = settings.api_token;
  const cleanUrl = (settings.api_base_url || 'https://01pos.01output.com/api/v1').replace(/\/$/, '');
  const sales = getStoredSales();
  const unsubmitted = sales.filter((s) => s.sale.fbr_status === 'provisional');

  if (unsubmitted.length === 0) {
    return {
      success: true,
      synced_count: 0,
      failed_count: 0,
      remaining_count: 0,
      message: 'No pending local sales in outbox.',
    };
  }

  if (!token) {
    return {
      success: false,
      synced_count: 0,
      failed_count: unsubmitted.length,
      remaining_count: unsubmitted.length,
      message: 'Terminal is unpaired. Please pair terminal in Settings to sync sales.',
    };
  }

  let synced = 0;
  let failed = 0;
  let lastErr = '';

  for (const item of unsubmitted) {
    try {
      const payload = {
        id: item.sale.id,
        sale_number: item.sale.sale_number,
        payment_method: item.sale.payment_method,
        subtotal_minor: item.sale.subtotal_minor,
        tax_minor: item.sale.tax_minor,
        total_minor: item.sale.total_minor,
        status: 'completed',
        items: (item.items || []).map((i) => ({
          id: i.id || crypto.randomUUID(),
          product_id: i.product_id,
          product_name: i.product_name,
          hs_code: i.hs_code || '00000000',
          quantity: i.quantity,
          unit_price_minor: i.unit_price_minor,
          tax_rate_basis_points: i.tax_rate_basis_points || 1800,
          tax_amount_minor: i.tax_minor || 0,
          line_total_minor: i.total_minor || 0,
        })),
      };

      const res = await fetch(`${cleanUrl}/sales/sync`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`,
          'Idempotency-Key': item.sale.id,
          ...(settings.terminal_code ? { 'X-Terminal-Code': settings.terminal_code } : {}),
        },
        body: JSON.stringify({ sales: [payload] }),
      });

      if (res.ok) {
        const data = await res.json();
        if (data.synced > 0) {
          synced++;
          item.sale.fbr_status = 'submitted';
        } else {
          failed++;
          lastErr = JSON.stringify(data.errors || 'Validation error');
        }
      } else {
        failed++;
        lastErr = `HTTP ${res.status}`;
      }
    } catch (e: any) {
      failed++;
      lastErr = e?.message || String(e);
    }
  }

  saveStoredSales(sales);
  return {
    success: failed === 0,
    synced_count: synced,
    failed_count: failed,
    remaining_count: failed,
    message: failed === 0
      ? `All ${synced} sales synced successfully!`
      : `Synced ${synced} sales, ${failed} failed: ${lastErr}`,
  };
}

export async function resetOutbox(): Promise<number> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<number>('reset_outbox_now');
  }
  return 0;
}

export interface VersionStatus {
  current_version: string;
  latest_version: string;
  is_outdated: boolean;
  is_critical: boolean;
  download_url?: string;
  release_title?: string;
}

export async function checkTerminalVersion(currentVersion = APP_VERSION): Promise<VersionStatus | null> {
  try {
    const settings = await getSettings();
    const baseUrl = (settings.api_base_url || 'https://01pos.01output.com/api/v1').replace(/\/$/, '');
    const res = await fetch(`${baseUrl}/version/check?current_version=${encodeURIComponent(currentVersion)}`, {
      headers: { Accept: 'application/json' },
    });
    if (res.ok) {
      return await res.json();
    }
  } catch (e) {
    // Ignore offline/network issues
  }
  return null;
}

export interface PairTerminalResult {
  success: boolean;
  message: string;
  terminal_code?: string;
  tenant_name?: string;
  location_name?: string;
}

export async function pairTerminal(pairingCode: string, customBaseUrl?: string): Promise<PairTerminalResult> {
  const settings = await getSettings();
  const baseUrl = (customBaseUrl || settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  let hwUuid = 'hw-pos-default';
  if (typeof window !== 'undefined' && window.localStorage) {
    hwUuid = localStorage.getItem('pk_pos_hardware_uuid') || '';
    if (!hwUuid) {
      hwUuid = 'hw-' + Math.random().toString(36).substring(2, 10) + '-' + Date.now();
      localStorage.setItem('pk_pos_hardware_uuid', hwUuid);
    }
  }

  const payload = {
    pairing_token: pairingCode.trim().toUpperCase(),
    hardware_uuid: hwUuid,
    hostname: 'TERMINAL-' + hwUuid.substring(0, 8).toUpperCase(),
    platform_os: isTauri() ? (isAndroidDevice() ? 'android-tauri' : 'windows-tauri') : 'browser-client',
    app_version: APP_VERSION,
  };

  try {
    const res = await fetch(`${baseUrl}/terminals/pair`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(payload),
    });

    const data = await res.json();

    if (!res.ok) {
      return {
        success: false,
        message: data.message || 'Pairing failed. Please verify the 8-character pairing code.',
      };
    }

    settings.api_token = data.token;
    settings.terminal_code = data.terminal_code;
    settings.terminal_id = data.terminal_id;
    settings.seller_name = data.tenant_name || settings.seller_name;
    settings.seller_address = data.location_name || settings.seller_address;
    settings.fbr_pos_id = data.fbr_pos_id || settings.fbr_pos_id;
    settings.fbr_ntn = data.ntn || settings.fbr_ntn;
    settings.fbr_strn = data.strn || settings.fbr_strn;
    if (customBaseUrl) {
      settings.api_base_url = customBaseUrl;
    }

    await saveSettings(settings);

    // Wipe previous tenant's cached products and previous local sales history so they do not leak into the new store
    mockProducts.length = 0;
    if (typeof window !== 'undefined' && window.localStorage) {
      localStorage.setItem('pk_pos_cached_products', JSON.stringify([]));
    }
    await clearLocalSales();
    if (isTauri()) {
      try {
        const { invoke } = await import('@tauri-apps/api/core');
        await invoke('clear_cached_products');
      } catch (e) {
        // non-blocking
      }
    }

    // Pull products and tax rules for the new tenant immediately
    await syncCatalog();

    return {
      success: true,
      message: `Successfully paired with ${data.tenant_name} (${data.terminal_code})!`,
      terminal_code: data.terminal_code,
      tenant_name: data.tenant_name,
      location_name: data.location_name,
    };
  } catch (err: any) {
    return {
      success: false,
      message: 'Network error connecting to API: ' + (err?.message || String(err)),
    };
  }
}

export async function sendHeartbeat(): Promise<{ status: string; is_active: boolean; terminal_code?: string } | null> {
  const settings = await getSettings();
  const baseUrl = (settings.api_base_url || 'https://01pos.01output.com/api/v1').replace(/\/$/, '');

  let hwUuid = '';
  if (typeof window !== 'undefined' && window.localStorage) {
    hwUuid = localStorage.getItem('pk_pos_hardware_uuid') || localStorage.getItem('pk_pos_hw_uuid') || '';
    if (!hwUuid) {
      hwUuid = 'hw-' + Math.random().toString(36).substring(2, 10) + '-' + Date.now();
      localStorage.setItem('pk_pos_hardware_uuid', hwUuid);
      localStorage.setItem('pk_pos_hw_uuid', hwUuid);
    }
  }

  const payload: Record<string, any> = {
    terminal_code: settings.terminal_code || undefined,
    terminal_id: settings.terminal_id || undefined,
    hardware_uuid: hwUuid || undefined,
    app_version: APP_VERSION,
  };

  try {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (settings.api_token) {
      headers['Authorization'] = `Bearer ${settings.api_token}`;
    }
    if (settings.terminal_code) {
      headers['X-Terminal-Code'] = settings.terminal_code;
    }

    const res = await fetch(`${baseUrl}/terminals/heartbeat`, {
      method: 'POST',
      headers,
      body: JSON.stringify(payload),
    });

    if (res.ok) {
      return await res.json();
    }
  } catch (err) {
    // silently catch offline/unreachable
  }
  return null;
}

// -------------------------------------------------------------
// Cashier PIN Authentication & Shift Management API Operations
// -------------------------------------------------------------

let currentCashierCache: any = null;

export function getActiveCashier(): any {
  if (currentCashierCache) return currentCashierCache;
  if (typeof window !== 'undefined' && window.localStorage) {
    try {
      const stored = localStorage.getItem('pk_pos_active_cashier');
      if (stored) {
        currentCashierCache = JSON.parse(stored);
        return currentCashierCache;
      }
    } catch (e) {}
  }
  return null;
}

export function setActiveCashier(cashier: any): void {
  currentCashierCache = cashier;
  if (typeof window !== 'undefined' && window.localStorage) {
    if (cashier) {
      localStorage.setItem('pk_pos_active_cashier', JSON.stringify(cashier));
    } else {
      localStorage.removeItem('pk_pos_active_cashier');
    }
  }
}

export async function authenticateCashierPin(pin: string): Promise<{ user: any; message: string }> {
  const settings = await getSettings();
  const baseUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  if (settings.api_token) {
    headers['Authorization'] = `Bearer ${settings.api_token}`;
  }

  const res = await fetch(`${baseUrl}/pos/auth/pin`, {
    method: 'POST',
    headers,
    body: JSON.stringify({ pin }),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({ message: 'Invalid PIN.' }));
    throw new Error(err.message || 'Invalid cashier PIN.');
  }

  const data = await res.json();
  setActiveCashier(data.user);
  return data;
}

export async function supervisorOverride(pin: string, action: string): Promise<{ authorized: boolean; supervisor?: any; message: string }> {
  const settings = await getSettings();
  const baseUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  if (settings.api_token) {
    headers['Authorization'] = `Bearer ${settings.api_token}`;
  }

  const res = await fetch(`${baseUrl}/pos/auth/supervisor-override`, {
    method: 'POST',
    headers,
    body: JSON.stringify({ pin, action }),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({ message: 'Authorization denied.' }));
    throw new Error(err.message || 'Authorization denied.');
  }

  return await res.json();
}

export async function getActiveShift(): Promise<{ active: boolean; session?: any; summary?: any }> {
  const settings = await getSettings();
  const baseUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  const headers: Record<string, string> = {
    'Accept': 'application/json',
  };
  if (settings.api_token) {
    headers['Authorization'] = `Bearer ${settings.api_token}`;
  }
  if (settings.terminal_id) {
    headers['X-Terminal-Id'] = settings.terminal_id;
  }

  try {
    const res = await fetch(`${baseUrl}/pos/shifts/current`, { headers });
    if (res.ok) {
      return await res.json();
    }
  } catch (e) {
    console.error('Failed to query active shift', e);
  }

  return { active: false };
}

export async function openRegisterShift(locationId: string, openingFloatMinor: number, notes?: string): Promise<{ message: string; session: any }> {
  const settings = await getSettings();
  const baseUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  if (settings.api_token) {
    headers['Authorization'] = `Bearer ${settings.api_token}`;
  }

  const res = await fetch(`${baseUrl}/pos/shifts/open`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      location_id: locationId,
      terminal_id: settings.terminal_id || undefined,
      opening_float_minor: openingFloatMinor,
      notes,
    }),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({ message: 'Failed to open shift.' }));
    throw new Error(err.message || 'Failed to open shift.');
  }

  return await res.json();
}

export async function recordCashMovement(
  sessionId: string,
  type: 'cash_in' | 'cash_drop' | 'payout_expense',
  amountMinor: number,
  reason: string,
  supervisorPin?: string
): Promise<any> {
  const settings = await getSettings();
  const baseUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  if (settings.api_token) {
    headers['Authorization'] = `Bearer ${settings.api_token}`;
  }

  const res = await fetch(`${baseUrl}/pos/shifts/cash-movement`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      register_session_id: sessionId,
      type,
      amount_minor: amountMinor,
      reason,
      supervisor_pin: supervisorPin,
    }),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({ message: 'Failed to record cash movement.' }));
    throw new Error(err.message || 'Failed to record cash movement.');
  }

  return await res.json();
}

export async function getShiftXReport(sessionId: string): Promise<{ report: any }> {
  const settings = await getSettings();
  const baseUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  const headers: Record<string, string> = {
    'Accept': 'application/json',
  };
  if (settings.api_token) {
    headers['Authorization'] = `Bearer ${settings.api_token}`;
  }

  const res = await fetch(`${baseUrl}/pos/shifts/${sessionId}/x-report`, { headers });
  if (!res.ok) {
    throw new Error('Failed to fetch X-Report.');
  }

  return await res.json();
}

export async function closeRegisterShift(sessionId: string, closingCashMinor: number, notes?: string): Promise<{ message: string; z_report: any }> {
  const settings = await getSettings();
  const baseUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  if (settings.api_token) {
    headers['Authorization'] = `Bearer ${settings.api_token}`;
  }

  const res = await fetch(`${baseUrl}/pos/shifts/${sessionId}/close`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      closing_cash_minor: closingCashMinor,
      notes,
    }),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({ message: 'Failed to close shift.' }));
    throw new Error(err.message || 'Failed to close shift.');
  }

  return await res.json();
}

// -------------------------------------------------------------
// Customer CRM & Khata / Udhaar (Phase 2)
// -------------------------------------------------------------

export async function searchCustomers(query: string = ''): Promise<CustomerProfile[]> {
  const settings = await getSettings();
  const baseUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  const headers: Record<string, string> = {
    'Accept': 'application/json',
  };
  if (settings.api_token) {
    headers['Authorization'] = `Bearer ${settings.api_token}`;
  }

  try {
    const url = new URL(`${baseUrl}/pos/customers`);
    if (query.trim()) {
      url.searchParams.set('q', query.trim());
    }
    const res = await fetch(url.toString(), { headers });
    if (res.ok) {
      const data = await res.json();
      return data.customers || [];
    }
  } catch (e) {
    console.warn('Network error fetching customers from backend, checking local cache:', e);
  }

  // Local storage fallback for dev/offline mode
  if (typeof window !== 'undefined' && window.localStorage) {
    try {
      const local = localStorage.getItem('pk_pos_customers');
      if (local) {
        const parsed: CustomerProfile[] = JSON.parse(local);
        if (!query.trim()) return parsed;
        const q = query.toLowerCase().trim();
        return parsed.filter(
          (c) =>
            c.name.toLowerCase().includes(q) ||
            c.phone.includes(q) ||
            (c.cnic && c.cnic.includes(q))
        );
      }
    } catch (e) {}
  }

  return [];
}

export async function createCustomer(data: Partial<CustomerProfile>): Promise<CustomerProfile> {
  const settings = await getSettings();
  const baseUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  if (settings.api_token) {
    headers['Authorization'] = `Bearer ${settings.api_token}`;
  }

  try {
    const res = await fetch(`${baseUrl}/pos/customers`, {
      method: 'POST',
      headers,
      body: JSON.stringify(data),
    });
    if (res.ok) {
      const result = await res.json();
      return result.customer;
    } else {
      const err = await res.json().catch(() => ({ message: 'Failed to create customer.' }));
      throw new Error(err.message || 'Failed to create customer.');
    }
  } catch (e: any) {
    if (e.message && !e.message.includes('Failed to fetch')) {
      throw e;
    }
  }

  // Offline / mock fallback
  const newCust: CustomerProfile = {
    id: crypto.randomUUID(),
    name: data.name || 'Walk-in Customer',
    phone: data.phone || '03000000000',
    email: data.email,
    cnic: data.cnic,
    ntn: data.ntn,
    strn: data.strn,
    address: data.address,
    city: data.city,
    credit_limit_minor: data.credit_limit_minor || 0,
    current_balance_minor: 0,
    is_active: true,
  };

  if (typeof window !== 'undefined' && window.localStorage) {
    try {
      const local = localStorage.getItem('pk_pos_customers');
      const list: CustomerProfile[] = local ? JSON.parse(local) : [];
      list.push(newCust);
      localStorage.setItem('pk_pos_customers', JSON.stringify(list));
    } catch (e) {}
  }

  return newCust;
}

export async function recordCustomerPayment(
  customerId: string,
  amountMinor: number,
  paymentMethod: string,
  notes?: string
): Promise<{ message: string; transaction: CustomerTransactionItem; customer: CustomerProfile }> {
  const settings = await getSettings();
  const baseUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  if (settings.api_token) {
    headers['Authorization'] = `Bearer ${settings.api_token}`;
  }

  const res = await fetch(`${baseUrl}/pos/customers/${customerId}/payments`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      amount_minor: amountMinor,
      payment_method: paymentMethod,
      notes,
    }),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({ message: 'Failed to record payment.' }));
    throw new Error(err.message || 'Failed to record payment.');
  }

  return await res.json();
}

export interface ReturnableLineItem {
  sale_item_id: string;
  product_id: string;
  product_name: string;
  unit_code: string;
  unit_conversion_factor: number;
  sold_quantity: number;
  already_returned_quantity: number;
  returnable_quantity: number;
  unit_price_minor: number;
  tax_rate_basis_points: number;
}

export interface VerifyInvoiceResponse {
  sale: {
    id: string;
    sale_number: string;
    total_minor: number;
    payment_method: string;
    fbr_usin?: string;
    customer_id?: string;
    customer?: {
      id: string;
      name: string;
      phone: string;
      current_balance_minor: number;
    };
  };
  returnable_items: ReturnableLineItem[];
}

export interface ProcessReturnPayload {
  sale_id: string;
  register_session_id?: string;
  reason: string;
  refund_method: string;
  notes?: string;
  items: Array<{
    sale_item_id: string;
    quantity_returned: number;
    restock_condition: string;
  }>;
}

export async function verifyInvoiceForReturn(invoiceNumber: string): Promise<VerifyInvoiceResponse> {
  const settings = await getSettings();
  const baseUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  if (settings.api_token) {
    headers['Authorization'] = `Bearer ${settings.api_token}`;
  }

  const res = await fetch(`${baseUrl}/pos/returns/verify-invoice`, {
    method: 'POST',
    headers,
    body: JSON.stringify({ invoice_number: invoiceNumber }),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({ error: 'Invoice lookup failed.' }));
    throw new Error(err.error || err.message || 'Invoice not found.');
  }

  return await res.json();
}

export async function processSaleReturn(payload: ProcessReturnPayload): Promise<any> {
  const settings = await getSettings();
  const baseUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  if (settings.api_token) {
    headers['Authorization'] = `Bearer ${settings.api_token}`;
  }

  const res = await fetch(`${baseUrl}/pos/returns`, {
    method: 'POST',
    headers,
    body: JSON.stringify(payload),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({ message: 'Failed to process return.' }));
    throw new Error(err.message || 'Return processing rejected.');
  }

  return await res.json();
}

export interface ReceiptTemplateData {
  template: {
    business_name?: string;
    branch_name?: string;
    address_line1?: string;
    address_line2?: string;
    phone?: string;
    email?: string;
    website?: string;
    ntn_label: string;
    strn_label: string;
    pos_id_label: string;
    paper_width: '80mm' | '58mm';
    show_tax_breakdown: boolean;
    show_customer_info: boolean;
    show_fbr_qr: boolean;
    header_note?: string;
    footer_policy?: string;
    footer_greeting?: string;
    logo_url?: string;
  };
  tenant: {
    id: string;
    name: string;
    ntn?: string;
    strn?: string;
    province?: string;
    fbr_pos_id?: string;
  };
}

export async function fetchReceiptTemplate(): Promise<ReceiptTemplateData | null> {
  const settings = await getSettings();
  const token = settings.api_token;
  const cleanUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  if (typeof window !== 'undefined' && window.localStorage) {
    const cached = localStorage.getItem('pk_pos_receipt_template');
    if (cached) {
      try {
        return JSON.parse(cached);
      } catch (e) {
        // ignore
      }
    }
  }

  if (!token) return null;

  try {
    const res = await fetch(`${cleanUrl}/pos/receipt-template`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
    });
    if (res.ok) {
      const data: ReceiptTemplateData = await res.json();
      if (typeof window !== 'undefined' && window.localStorage) {
        localStorage.setItem('pk_pos_receipt_template', JSON.stringify(data));
      }
      return data;
    }
  } catch (e) {
    console.error('Failed to fetch receipt template:', e);
  }

  return null;
}

/**
 * Search pharmacy medicines by brand name, generic name, or DRAP registration number.
 */
export async function searchPharmacyMedicines(query: string, locationId?: string): Promise<any[]> {
  const settings = await getSettings();
  const token = settings.api_token;
  const cleanUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  if (!token) return [];

  try {
    const params = new URLSearchParams({ q: query });
    if (locationId) params.append('location_id', locationId);

    const res = await fetch(`${cleanUrl}/pos/pharmacy/medicines?${params.toString()}`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
    });

    if (res.ok) {
      return await res.json();
    }
  } catch (e) {
    console.error('Failed to search pharmacy medicines:', e);
  }

  return [];
}

/**
 * Fetch all active batches for a given product sorted by FEFO.
 */
export async function fetchProductBatches(productId: string, locationId?: string): Promise<any[]> {
  const settings = await getSettings();
  const token = settings.api_token;
  const cleanUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  if (!token) return [];

  try {
    const params = new URLSearchParams();
    if (locationId) params.append('location_id', locationId);

    const res = await fetch(`${cleanUrl}/pos/pharmacy/batches/${productId}?${params.toString()}`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
    });

    if (res.ok) {
      return await res.json();
    }
  } catch (e) {
    console.error(`Failed to fetch batches for product ${productId}:`, e);
  }

  return [];
}

/**
 * Fetch batches nearing expiry.
 */
export async function fetchExpiringBatches(days = 90): Promise<any[]> {
  const settings = await getSettings();
  const token = settings.api_token;
  const cleanUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  if (!token) return [];

  try {
    const res = await fetch(`${cleanUrl}/pos/pharmacy/expiring-batches?days=${days}`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
    });

    if (res.ok) {
      return await res.json();
    }
  } catch (e) {
    console.error('Failed to fetch expiring batches:', e);
  }

  return [];
}
