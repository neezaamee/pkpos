<script setup lang="ts">
import { ref, onMounted } from 'vue';
import type { PosSettings } from '../types/pos';
import { getSettings, saveSettings, testPrinter, openCashDrawer, syncCatalog, pairTerminal, clearLocalSales } from '../services/posService';

const settings = ref<PosSettings>({
  api_base_url: 'https://01pos.01output.com/api/v1',
  fbr_pos_id: 'POS-001',
  fbr_bearer_token: '',
  fbr_ntn: '1234567-8',
  fbr_strn: '12-34-5678-001-99',
  seller_name: 'Metro Cash & Carry',
  seller_address: 'Plot 5, Main Boulevard, Lahore',
  seller_province: 'Punjab',
  printer_type: 'mock',
  printer_ip: '127.0.0.1',
  printer_port: 9100,
});

const isSaving = ref(false);
const saveFeedback = ref<string | null>(null);
const testPrintFeedback = ref<string | null>(null);
const isSyncing = ref(false);
const syncFeedback = ref<string | null>(null);

const pairingCode = ref('');
const isPairing = ref(false);
const pairingFeedback = ref<{ type: 'success' | 'error'; message: string } | null>(null);

async function handlePair() {
  if (!pairingCode.value.trim()) {
    pairingFeedback.value = { type: 'error', message: 'Please enter the 8-character pairing code.' };
    return;
  }
  isPairing.value = true;
  pairingFeedback.value = null;
  try {
    const res = await pairTerminal(pairingCode.value, settings.value.api_base_url);
    if (res.success) {
      pairingFeedback.value = { type: 'success', message: res.message };
      pairingCode.value = '';
      await loadSettingsData();
    } else {
      pairingFeedback.value = { type: 'error', message: res.message };
    }
  } catch (err: any) {
    pairingFeedback.value = { type: 'error', message: 'Pairing error: ' + (err?.message || String(err)) };
  } finally {
    isPairing.value = false;
  }
}

async function loadSettingsData() {
  try {
    settings.value = await getSettings();
  } catch (err) {
    console.error('Failed to load settings', err);
  }
}

async function handleSave() {
  isSaving.value = true;
  saveFeedback.value = null;
  try {
    await saveSettings(settings.value);
    saveFeedback.value = 'Settings saved locally in SQLite.';
    setTimeout(() => {
      saveFeedback.value = null;
    }, 4000);
  } catch (err: any) {
    saveFeedback.value = 'Failed to save: ' + (err?.message || String(err));
  } finally {
    isSaving.value = false;
  }
}

async function handleTestPrint() {
  testPrintFeedback.value = null;
  try {
    await testPrinter();
    testPrintFeedback.value = 'Test print sequence sent to printer.';
    setTimeout(() => {
      testPrintFeedback.value = null;
    }, 4000);
  } catch (err: any) {
    testPrintFeedback.value = 'Printer test failed: ' + (err?.message || String(err));
  }
}

async function handleOpenDrawer() {
  try {
    await openCashDrawer();
  } catch (err) {
    console.error('Cash drawer kick failed', err);
  }
}

async function handleSyncCatalog() {
  isSyncing.value = true;
  syncFeedback.value = null;
  try {
    const res = await syncCatalog();
    syncFeedback.value = res.message;
    await loadSettingsData();
  } catch (err: any) {
    syncFeedback.value = 'Sync failed: ' + (err?.message || String(err));
  } finally {
    isSyncing.value = false;
  }
}

async function handleForceFullSync() {
  isSyncing.value = true;
  syncFeedback.value = null;
  try {
    if (typeof window !== 'undefined' && window.localStorage) {
      localStorage.setItem('pk_pos_cached_products', JSON.stringify([]));
    }
    const res = await syncCatalog();
    syncFeedback.value = 'Full cache refresh: ' + res.message;
    await loadSettingsData();
  } catch (err: any) {
    syncFeedback.value = 'Sync failed: ' + (err?.message || String(err));
  } finally {
    isSyncing.value = false;
  }
}

async function handleResetTerminalData() {
  if (confirm('Clear all local terminal data (cached products and local orders history) for this register?')) {
    await clearLocalSales();
    if (typeof window !== 'undefined' && window.localStorage) {
      localStorage.setItem('pk_pos_cached_products', JSON.stringify([]));
    }
    await syncCatalog();
    await loadSettingsData();
    saveFeedback.value = 'Local terminal cache & sales history wiped clean.';
    setTimeout(() => { saveFeedback.value = null; }, 4000);
  }
}

onMounted(() => {
  loadSettingsData();
});
</script>

<template>
  <div class="settings-container">
    <div class="settings-header">
      <h2 class="settings-title">POS Client Settings</h2>
      <p class="settings-subtitle">Manage backend endpoint, hardware printer, and tax configuration</p>
    </div>

    <div class="settings-grid">
      <!-- Section 0: Hardware Terminal Fleet Pairing -->
      <div class="settings-card" style="grid-column: 1 / -1; border: 1px solid #6366f1; background: linear-gradient(180deg, rgba(99, 102, 241, 0.08) 0%, rgba(15, 23, 42, 0.4) 100%);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
          <div>
            <h3 class="card-title" style="color: #a5b4fc; display: flex; align-items: center; gap: 0.5rem;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
              </svg>
              Pair with Tenant / Store Organization
            </h3>
            <p class="card-desc">
              Connect this physical cash register to any merchant tenant using the 8-character code generated from <strong>Super Admin &gt; POS Terminals</strong>.
            </p>
          </div>
          <div style="text-align: right;">
            <span v-if="settings.api_token" class="status-pill paired" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.35rem 0.75rem; border-radius: 9999px; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem;">
              <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
              Paired: {{ settings.seller_name }} ({{ settings.terminal_code || 'POS-001' }})
            </span>
            <span v-else class="status-pill unpaired" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); padding: 0.35rem 0.75rem; border-radius: 9999px; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem;">
              <span style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b;"></span>
              Unpaired Register
            </span>
          </div>
        </div>

        <div style="display: flex; gap: 0.75rem; margin-top: 1.25rem; align-items: center; flex-wrap: wrap;">
          <input
            type="text"
            v-model="pairingCode"
            maxlength="12"
            placeholder="Enter 8-char Code (e.g. 7X9B2K4A)"
            style="text-transform: uppercase; letter-spacing: 0.1em; font-family: monospace; font-size: 1.1rem; max-width: 340px; font-weight: 700;"
            class="form-input"
            @keyup.enter="handlePair"
          />
          <button class="primary-btn" :disabled="isPairing || !pairingCode.trim()" @click="handlePair" style="background: #6366f1;">
            {{ isPairing ? 'Pairing Hardware...' : 'Pair Terminal Now' }}
          </button>
        </div>

        <div v-if="pairingFeedback" style="margin-top: 1rem;">
          <p :class="['feedback-toast', pairingFeedback.type]" style="margin-top: 0;">{{ pairingFeedback.message }}</p>
        </div>
      </div>

      <!-- Section 1: Laravel Backend Connection -->
      <div class="settings-card">
        <h3 class="card-title">Backend API Configuration</h3>
        <p class="card-desc">Configure the central Laravel API server URL.</p>

        <div class="form-group">
          <label class="form-label">Laravel API Base URL</label>
          <input
            type="text"
            v-model="settings.api_base_url"
            class="form-input"
            placeholder="http://127.0.0.1:8000/api/v1"
          />
          <span class="field-hint">Used for outbox background worker and catalog synchronization.</span>
        </div>

        <div class="form-group">
          <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Terminal Sanctum API Token</span>
            <span style="font-size: 0.75rem; color: #10b981; font-weight: 600;" v-if="settings.api_token">✓ Auto-Provisioned via Pairing</span>
            <span style="font-size: 0.75rem; color: #f59e0b; font-weight: 600;" v-else>⚠️ Unpaired (No Token)</span>
          </label>
          <input
            type="password"
            v-model="settings.api_token"
            class="form-input"
            placeholder="Auto-provisioned upon pairing or manual override..."
          />
          <span class="field-hint">
            Cryptographic bearer token generated by Laravel Sanctum. Automatically populated when you pair above.
          </span>
        </div>

        <div class="card-actions" style="flex-wrap: wrap; gap: 0.5rem;">
          <button class="primary-btn" :disabled="isSaving" @click="handleSave">
            {{ isSaving ? 'Saving...' : 'Save Configuration' }}
          </button>
          <button class="secondary-btn" :disabled="isSyncing" @click="handleSyncCatalog">
            {{ isSyncing ? 'Syncing...' : 'Sync Catalog Now' }}
          </button>
          <button class="secondary-btn" :disabled="isSyncing" @click="handleForceFullSync" title="Wipes local product cache and downloads complete tenant catalog from server" style="color: #cbd5e1;">
            Clear Cache &amp; Re-Sync
          </button>
          <button class="secondary-btn" :disabled="isSyncing" @click="handleResetTerminalData" title="Wipes local sales and products for testing a fresh tenant" style="color: #f87171; border-color: rgba(239, 68, 68, 0.4);">
            Reset Local Orders &amp; Cache
          </button>
        </div>

        <p v-if="saveFeedback" class="feedback-toast success">{{ saveFeedback }}</p>
        <p v-if="syncFeedback" class="feedback-toast info">{{ syncFeedback }}</p>
      </div>

      <!-- Section 2: FBR Credentials (Read-Only) -->
      <div class="settings-card">
        <h3 class="card-title">FBR POS Credentials</h3>
        <p class="card-desc">Synced from Laravel tenant config via <code>/api/v1/tenant/config</code>.</p>

        <div class="form-row">
          <div class="form-group flex-1">
            <label class="form-label">FBR POS ID</label>
            <input type="text" :value="settings.fbr_pos_id" class="form-input read-only" readonly />
          </div>
          <div class="form-group flex-1">
            <label class="form-label">NTN</label>
            <input type="text" :value="settings.fbr_ntn" class="form-input read-only" readonly />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">STRN</label>
          <input type="text" :value="settings.fbr_strn" class="form-input read-only" readonly />
        </div>

        <div class="form-group">
          <label class="form-label">FBR IRIS Bearer Token</label>
          <input
            type="password"
            :value="settings.fbr_bearer_token ? '••••••••••••••••••••••••' : '(Not configured)'"
            class="form-input read-only"
            readonly
          />
          <span class="field-hint">Token is secured and managed by the Rust backend / central server.</span>
        </div>

        <div class="card-actions">
          <button class="secondary-btn" :disabled="isSyncing" @click="handleSyncCatalog">
            {{ isSyncing ? 'Syncing...' : 'Sync FBR Credentials' }}
          </button>
        </div>
      </div>

      <!-- Section 3: Hardware ESC/POS Receipt Printer -->
      <div class="settings-card">
        <h3 class="card-title">ESC/POS Receipt Printer</h3>
        <p class="card-desc">Supports native ESC/POS command sequences and QR code (GS ( k).</p>

        <div class="form-group">
          <label class="form-label">Printer Connection Type</label>
          <select v-model="settings.printer_type" class="form-select">
            <option value="mock">Mock / Virtual Printer (Testing)</option>
            <option value="usb">USB Direct Thermal Printer</option>
            <option value="network">Network Thermal Printer (TCP/IP)</option>
          </select>
        </div>

        <div v-if="settings.printer_type === 'network'" class="form-row">
          <div class="form-group flex-2">
            <label class="form-label">Printer IP Address</label>
            <input
              type="text"
              v-model="settings.printer_ip"
              class="form-input"
              placeholder="192.168.1.100"
            />
          </div>
          <div class="form-group flex-1">
            <label class="form-label">Port</label>
            <input
              type="number"
              v-model.number="settings.printer_port"
              class="form-input"
              placeholder="9100"
            />
          </div>
        </div>

        <div class="card-actions">
          <button class="secondary-btn" @click="handleTestPrint">
            Test Print Receipt
          </button>
          <button class="secondary-btn" @click="handleOpenDrawer">
            Open Cash Drawer
          </button>
          <button class="primary-btn" @click="handleSave">
            Save Printer
          </button>
        </div>

        <p v-if="testPrintFeedback" class="feedback-toast info">{{ testPrintFeedback }}</p>
      </div>

      <!-- Section 4: Store / Receipt Header Details -->
      <div class="settings-card">
        <h3 class="card-title">Receipt Header Info</h3>
        <p class="card-desc">Seller details printed on official & provisional tax receipts.</p>

        <div class="form-group">
          <label class="form-label">Store / Business Name</label>
          <input type="text" v-model="settings.seller_name" class="form-input" />
        </div>

        <div class="form-row">
          <div class="form-group flex-2">
            <label class="form-label">Address</label>
            <input type="text" v-model="settings.seller_address" class="form-input" />
          </div>
          <div class="form-group flex-1">
            <label class="form-label">Province</label>
            <input type="text" v-model="settings.seller_province" class="form-input" />
          </div>
        </div>

        <div class="card-actions">
          <button class="primary-btn" :disabled="isSaving" @click="handleSave">
            Save Receipt Header
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.settings-container {
  padding: 1.5rem;
  height: calc(100vh - 64px);
  overflow-y: auto;
  box-sizing: border-box;
  background: #0f172a;
  color: #f8fafc;
}

.settings-header {
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #1e293b;
}

.settings-title {
  margin: 0 0 0.25rem;
  font-size: 1.5rem;
  font-weight: 700;
  color: #ffffff;
}

.settings-subtitle {
  margin: 0;
  font-size: 0.85rem;
  color: #94a3b8;
}

.settings-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
  gap: 1.5rem;
}

.settings-card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 0.75rem;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
}

.card-title {
  margin: 0 0 0.25rem;
  font-size: 1.1rem;
  font-weight: 600;
  color: #f1f5f9;
}

.card-desc {
  margin: 0 0 1.25rem;
  font-size: 0.8rem;
  color: #94a3b8;
}

.form-group {
  margin-bottom: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-row {
  display: flex;
  gap: 0.75rem;
}

.flex-1 { flex: 1; }
.flex-2 { flex: 2; }

.form-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: #cbd5e1;
}

.form-input, .form-select {
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  padding: 0.55rem 0.85rem;
  color: #ffffff;
  font-size: 0.85rem;
  outline: none;
  transition: border-color 0.15s ease;
}

.form-input:focus, .form-select:focus {
  border-color: #3b82f6;
}

.form-input.read-only {
  background: #182234;
  color: #94a3b8;
  cursor: default;
}

.field-hint {
  font-size: 0.7rem;
  color: #64748b;
}

.card-actions {
  display: flex;
  gap: 0.75rem;
  margin-top: auto;
  padding-top: 0.75rem;
}

.primary-btn {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 0.55rem 1.1rem;
  border-radius: 0.5rem;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease;
}

.primary-btn:hover:not(:disabled) {
  background: #1d4ed8;
}

.primary-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.secondary-btn {
  background: #334155;
  color: #f1f5f9;
  border: 1px solid #475569;
  padding: 0.55rem 1rem;
  border-radius: 0.5rem;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease;
}

.secondary-btn:hover:not(:disabled) {
  background: #475569;
}

.secondary-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.feedback-toast {
  margin-top: 0.75rem;
  padding: 0.5rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
}

.feedback-toast.success {
  background: rgba(16, 185, 129, 0.15);
  border: 1px solid #10b981;
  color: #34d399;
}

.feedback-toast.info {
  background: rgba(59, 130, 246, 0.15);
  border: 1px solid #3b82f6;
  color: #60a5fa;
}
</style>
