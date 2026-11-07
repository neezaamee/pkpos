<template>
  <AdminLayout title="POS Terminals & Hardware Counters">
    <!-- Header Summary -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h2 style="font-size: 1.25rem; font-weight: 700;">POS Terminals & Hardware Registers</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          Monitor real-time connectivity status of physical checkout counters in your store branches.
        </p>
      </div>
      <div style="display: flex; gap: 0.75rem; align-items: center;">
        <a
          href="/downloads/desktop"
          class="btn btn-secondary"
          style="display: flex; align-items: center; gap: 0.4rem; color: #38bdf8;"
          title="Download the compiled Windows POS executable"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
          </svg>
          Download Desktop App (.exe)
        </a>

        <button
          @click="openPairingModal"
          :disabled="!quota.can_add"
          class="btn btn-primary"
          style="display: flex; align-items: center; gap: 0.4rem;"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="16"></line>
            <line x1="8" y1="12" x2="16" y2="12"></line>
          </svg>
          Add New Terminal Counter
        </button>
      </div>
    </div>

    <!-- Feedback Flash Alerts -->
    <div v-if="$page.props.flash?.success" class="alert alert-success" style="margin-bottom: 1.25rem;">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="alert alert-danger" style="margin-bottom: 1.25rem;">
      {{ $page.props.flash.error }}
    </div>

    <!-- Fleet Status KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Registered Terminals
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: var(--text-main);">
          {{ metrics.total }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Plan Quota: {{ quota.max_terminals }}
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="display: flex; align-items: center; gap: 0.4rem;">
          <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981;"></span>
          <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
            Online Now
          </div>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #10b981;">
          {{ metrics.online }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Active heartbeat within 15 mins
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Offline / Standby
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: var(--text-muted);">
          {{ metrics.offline }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Offline or idle registers
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Pending Pairing
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #f59e0b;">
          {{ metrics.pending_pairing }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Awaiting 8-char code entry
        </div>
      </div>
    </div>

    <!-- Terminals Table -->
    <div class="card-table">
      <div class="table-header">
        <h2>Your Store Registers ({{ terminals.length }})</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">
          Hardware connectivity refreshed automatically
        </span>
      </div>

      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Counter &amp; Code</th>
              <th>Branch Location</th>
              <th>Live Status</th>
              <th>Pairing Token</th>
              <th>Last Heartbeat</th>
              <th>Last Sync</th>
              <th>Device IP / Info</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="terminals.length === 0">
              <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                <div style="margin-bottom: 0.5rem; font-size: 1.1rem; font-weight: 600;">No POS counters registered yet.</div>
                <p style="font-size: 0.85rem; margin-bottom: 1rem;">Click "Add New Terminal Counter" to generate an 8-character pairing code for your desktop app.</p>
                <button @click="openPairingModal" class="btn btn-primary" style="font-size: 0.85rem;">
                  Generate First Pairing Code
                </button>
              </td>
            </tr>

            <tr v-for="terminal in terminals" :key="terminal.id">
              <td>
                <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                  {{ terminal.name }}
                </div>
                <div style="font-family: monospace; font-size: 0.78rem; color: #818cf8; margin-top: 0.15rem;">
                  {{ terminal.terminal_code }}
                </div>
              </td>

              <td>
                <span style="font-size: 0.85rem; color: var(--text-secondary);">
                  {{ terminal.location_name }}
                </span>
              </td>

              <td>
                <div v-if="terminal.pairing_status === 'pending_pairing'" style="display: flex; align-items: center; gap: 0.35rem;">
                  <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #f59e0b;"></span>
                  <span class="badge badge-warning" style="font-size: 0.72rem;">Pending Pairing</span>
                </div>
                <div v-else-if="terminal.is_online" style="display: flex; align-items: center; gap: 0.35rem;">
                  <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981;"></span>
                  <span class="badge badge-success" style="font-size: 0.72rem; font-weight: 700;">Online</span>
                </div>
                <div v-else style="display: flex; align-items: center; gap: 0.35rem;">
                  <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #64748b;"></span>
                  <span class="badge badge-secondary" style="font-size: 0.72rem; background: rgba(100, 116, 139, 0.2); color: #94a3b8;">Offline</span>
                </div>
              </td>

              <td>
                <div v-if="terminal.pairing_token && terminal.pairing_status === 'pending_pairing'" style="display: flex; align-items: center; gap: 0.4rem;">
                  <span style="font-family: monospace; font-size: 0.9rem; font-weight: 800; color: #38bdf8; background: rgba(56, 189, 248, 0.15); padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid rgba(56, 189, 248, 0.3); letter-spacing: 0.1em;">
                    {{ terminal.pairing_token }}
                  </span>
                  <button
                    @click="copyToClipboard(terminal.pairing_token)"
                    class="btn-icon"
                    title="Copy Pairing Code"
                    style="padding: 0.2rem 0.4rem; background: transparent; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer; color: var(--text-muted);"
                  >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                      <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                  </button>
                </div>
                <span v-else-if="terminal.pairing_status === 'paired'" style="font-size: 0.75rem; color: #10b981; font-weight: 600;">
                  ✓ Paired &amp; Activated
                </span>
                <span v-else style="font-size: 0.75rem; color: var(--text-muted);">-</span>
              </td>

              <td>
                <span v-if="terminal.last_heartbeat_at" style="font-size: 0.8rem; color: var(--text-main);">
                  {{ terminal.last_heartbeat_at }}
                </span>
                <span v-else style="font-size: 0.75rem; color: var(--text-muted);">
                  Never connected
                </span>
              </td>

              <td>
                <span v-if="terminal.last_sync_at" style="font-size: 0.8rem; color: var(--text-main);">
                  {{ terminal.last_sync_at }}
                </span>
                <span v-else style="font-size: 0.75rem; color: var(--text-muted);">
                  No orders yet
                </span>
              </td>

              <td>
                <div style="font-size: 0.78rem; font-family: monospace; color: var(--text-secondary);">
                  {{ terminal.ip_address || '—' }}
                </div>
                <div v-if="terminal.app_version" style="font-size: 0.7rem; color: var(--text-muted);">
                  Client v{{ terminal.app_version }}
                </div>
              </td>

              <td style="text-align: right;">
                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                  <button
                    v-if="terminal.pairing_status === 'pending_pairing'"
                    @click="regenerateCode(terminal.id)"
                    class="btn btn-secondary"
                    style="padding: 0.3rem 0.6rem; font-size: 0.72rem;"
                    title="Generate a fresh 8-character pairing code"
                  >
                    Regenerate Code
                  </button>
                  <button
                    @click="deleteTerminal(terminal.id, terminal.name)"
                    class="btn btn-danger"
                    style="padding: 0.3rem 0.6rem; font-size: 0.72rem;"
                    title="Delete counter"
                  >
                    Remove
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal: Generate Pairing Code -->
    <div v-if="showPairingModal" class="modal-backdrop" @click.self="showPairingModal = false">
      <div class="modal-card" style="max-width: 480px;">
        <div class="modal-header">
          <h3 style="font-size: 1.1rem; font-weight: 700;">Add POS Counter Terminal</h3>
          <button @click="showPairingModal = false" class="close-btn">&times;</button>
        </div>

        <form @submit.prevent="submitPairingCode">
          <div style="padding: 1.25rem;">
            <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1.25rem;">
              Register a new hardware counter. You will receive an 8-character security code to type into the desktop POS application.
            </p>

            <div class="form-group">
              <label class="form-label">Counter Terminal Code</label>
              <input
                v-model="pairForm.terminal_code"
                type="text"
                class="form-input"
                placeholder="e.g. POS-002, COUNTER-EAST"
                required
              />
              <span class="field-hint">Unique identifier for this register in your store.</span>
            </div>

            <div class="form-group">
              <label class="form-label">Counter Friendly Name</label>
              <input
                v-model="pairForm.name"
                type="text"
                class="form-input"
                placeholder="e.g. Main Cash Register, Electronics Counter"
                required
              />
            </div>

            <div class="form-group" v-if="locations.length > 0">
              <label class="form-label">Store Branch Location</label>
              <select v-model="pairForm.location_id" class="form-input">
                <option value="">Unassigned (Default Branch)</option>
                <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                  {{ loc.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="modal-footer" style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" @click="showPairingModal = false" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="pairForm.processing">
              {{ pairForm.processing ? 'Generating...' : 'Generate 8-Char Code' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  terminals: {
    type: Array,
    default: () => [],
  },
  metrics: {
    type: Object,
    default: () => ({ total: 0, online: 0, offline: 0, pending_pairing: 0 }),
  },
  quota: {
    type: Object,
    default: () => ({ can_add: true, max_terminals: 'Unlimited' }),
  },
  locations: {
    type: Array,
    default: () => [],
  },
  tenant: {
    type: Object,
    default: () => ({}),
  },
});

const showPairingModal = ref(false);

const pairForm = useForm({
  terminal_code: '',
  name: '',
  location_id: '',
});

function openPairingModal() {
  const nextNum = props.terminals.length + 1;
  const pad = String(nextNum).padStart(3, '0');
  pairForm.terminal_code = `POS-${pad}`;
  pairForm.name = `Checkout Counter ${nextNum}`;
  pairForm.location_id = props.locations[0]?.id || '';
  showPairingModal.value = true;
}

function submitPairingCode() {
  pairForm.post('/terminals/pairing-code', {
    onSuccess: () => {
      showPairingModal.value = false;
      pairForm.reset();
    },
  });
}

function regenerateCode(id) {
  if (confirm('Regenerate a new 8-character pairing code for this counter?')) {
    router.post(`/terminals/${id}/regenerate-code`);
  }
}

function deleteTerminal(id, name) {
  if (confirm(`Remove counter "${name}"? The desktop app will no longer be able to sync until re-paired.`)) {
    router.delete(`/terminals/${id}`);
  }
}

function copyToClipboard(text) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text);
    alert(`Copied "${text}" to clipboard! Enter this code in your POS app Settings.`);
  }
}
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}
.modal-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  width: 100%;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
  overflow: hidden;
}
.modal-header {
  padding: 1.25rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.close-btn {
  background: transparent;
  border: none;
  font-size: 1.5rem;
  color: var(--text-muted);
  cursor: pointer;
}
.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
</style>
