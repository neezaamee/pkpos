import type {
  CachedProduct,
  CatalogSyncSummary,
  LocalSaleSummary,
  LocalSaleWithItems,
  PosSettings,
  ProcessResult,
} from '../types/pos';

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
      p.hs_code.includes(q)
  );
}

export async function getProductByBarcode(barcode: string): Promise<CachedProduct | null> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<CachedProduct | null>('get_product_by_barcode', { barcode });
  }

  const all = await getCachedProducts();
  return all.find((p) => p.barcode === barcode) || null;
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
            variants: item.variants || [],
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
    subtotal_minor: parsed.subtotal_minor,
    tax_minor: parsed.tax_minor,
    total_minor: parsed.total_minor,
    status: 'completed',
    fbr_status: 'submitted',
    fbr_usin: 'USIN-MOCK-' + Math.floor(100000 + Math.random() * 900000),
    created_at: new Date().toISOString(),
    items: (parsed.items || []).map((item: any) => ({
      id: item.id || crypto.randomUUID(),
      product_id: item.product_id,
      product_name: item.product_name,
      hs_code: item.hs_code,
      quantity: item.quantity,
      unit_price_minor: item.unit_price_minor,
      tax_rate_basis_points: item.tax_rate_basis_points,
      tax_minor: item.tax_minor ?? item.tax_amount_minor ?? 0,
      tax_amount_minor: item.tax_amount_minor ?? item.tax_minor ?? 0,
      total_minor: item.total_minor ?? item.line_total_minor ?? 0,
      line_total_minor: item.line_total_minor ?? item.total_minor ?? 0,
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
    api_base_url: local.api_base_url || 'http://127.0.0.1:8000/api/v1',
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

export async function getPendingOutboxCount(): Promise<number> {
  if (isTauri()) {
    const { invoke } = await import('@tauri-apps/api/core');
    return await invoke<number>('get_pending_outbox_count');
  }
  return 0;
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
    platform_os: isTauri() ? 'windows-tauri' : 'browser-client',
    app_version: '0.1.0',
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
  const baseUrl = (settings.api_base_url || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

  let hwUuid = '';
  if (typeof window !== 'undefined' && window.localStorage) {
    hwUuid = localStorage.getItem('pk_pos_hw_uuid') || '';
    if (!hwUuid) {
      hwUuid = crypto.randomUUID();
      localStorage.setItem('pk_pos_hw_uuid', hwUuid);
    }
  }

  const payload: Record<string, any> = {
    terminal_code: settings.terminal_code || undefined,
    terminal_id: settings.terminal_id || undefined,
    hardware_uuid: hwUuid || undefined,
    app_version: '0.1.0',
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
