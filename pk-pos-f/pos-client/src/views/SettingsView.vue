<script setup lang="ts">
import { ref, onMounted } from 'vue';
import type { PosSettings } from '../types/pos';
import { getSettings, saveSettings, testPrinter, openCashDrawer, syncCatalog } from '../services/posService';

const settings = ref<PosSettings>({
  api_base_url: 'http://127.0.0.1:8000/api/v1',
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
          <label class="form-label">Register API Token (Sanctum)</label>
          <input
            type="password"
            v-model="settings.api_token"
            class="form-input"
            placeholder="Auto-provisioned or paste token..."
          />
          <span class="field-hint">Authenticates this POS terminal for sync against your store tenant.</span>
        </div>

        <div class="card-actions">
          <button class="primary-btn" :disabled="isSaving" @click="handleSave">
            {{ isSaving ? 'Saving...' : 'Save Configuration' }}
          </button>
          <button class="secondary-btn" :disabled="isSyncing" @click="handleSyncCatalog">
            {{ isSyncing ? 'Syncing...' : 'Sync Catalog Now' }}
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
