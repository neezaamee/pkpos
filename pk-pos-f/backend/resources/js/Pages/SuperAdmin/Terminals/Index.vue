<template>
  <SuperAdminLayout title="POS Terminal Fleet Management">
    <!-- Header Summary -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h2 style="font-size: 1.25rem; font-weight: 700;">POS Terminal Fleet & Hardware Licensing</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          Real-time surveillance, client pairing, and remote killswitch for physical desktop POS registers across Pakistan.
        </p>
      </div>
      <div style="display: flex; gap: 0.75rem; align-items: center;">
        <button @click="openPairingModal" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.4rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="16"></line>
            <line x1="8" y1="12" x2="16" y2="12"></line>
          </svg>
          Generate Pairing Code
        </button>
      </div>
    </div>

    <!-- Fleet Metrics KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Total Paired Fleet
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: var(--text-main);">
          {{ metrics.total }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Registered cash registers
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="display: flex; align-items: center; gap: 0.4rem;">
          <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981;"></span>
          <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
            Online Terminals
          </div>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #10b981;">
          {{ metrics.online }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Heartbeat within 15 minutes
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
          Awaiting desktop connection
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Revoked / Locked
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #ef4444;">
          {{ metrics.revoked }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Access remotely deactivated
        </div>
      </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card-table" style="padding: 1.25rem; margin-bottom: 1.5rem;">
      <form @submit.prevent="applyFilters" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Search Terminal</label>
          <input
            v-model="filterForm.search"
            type="text"
            class="form-input"
            placeholder="Code, name, hostname, UUID..."
          />
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Merchant Organization</label>
          <select v-model="filterForm.tenant_id" class="form-input">
            <option value="">All Merchants</option>
            <option v-for="t in tenants" :key="t.id" :value="t.id">
              {{ t.name }}
            </option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Connectivity & Status</label>
          <select v-model="filterForm.status" class="form-input">
            <option value="">All Statuses</option>
            <option value="online">Online (Heartbeat &lt;15m)</option>
            <option value="offline">Offline / Standby</option>
            <option value="pending">Pending Pairing</option>
            <option value="revoked">Revoked / Locked</option>
          </select>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
          <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.55rem 0.85rem;">
            Filter
          </button>
          <button type="button" @click="resetFilters" class="btn btn-secondary" style="padding: 0.55rem 0.85rem;">
            Reset
          </button>
        </div>
      </form>
    </div>

    <!-- Terminals Fleet Table -->
    <div class="card-table">
      <div class="table-header">
        <h2>Terminal Registers Directory</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">
          Showing {{ terminals.from || 0 }} to {{ terminals.to || 0 }} of {{ terminals.total }} units
        </span>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Terminal</th>
              <th>Merchant / Branch</th>
              <th>Hardware Identifier</th>
              <th>App Version</th>
              <th>Connectivity Status</th>
              <th>Last Heartbeat</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in terminals.data" :key="item.id">
              <td>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                  <span style="font-family: monospace; font-size: 0.88rem; font-weight: 700; color: #38bdf8; background: rgba(56, 189, 248, 0.1); padding: 0.2rem 0.5rem; border-radius: 4px;">
                    {{ item.terminal_code }}
                  </span>
                  <div style="font-weight: 600; font-size: 0.9rem;">{{ item.name }}</div>
                </div>
              </td>
              <td>
                <div style="font-weight: 500;">{{ item.tenant_name }}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                  Branch: {{ item.location_name }}
                </div>
              </td>
              <td>
                <div style="font-family: monospace; font-size: 0.78rem; color: var(--text-main);">
                  {{ item.hardware_uuid ? item.hardware_uuid.substring(0, 18) + '...' : 'Not Paired' }}
                </div>
                <div v-if="item.hostname" style="font-size: 0.72rem; color: var(--text-muted);">
                  Host: {{ item.hostname }} ({{ item.platform_os }})
                </div>
              </td>
              <td>
                <span class="badge badge-neutral" style="font-family: monospace; font-size: 0.75rem;">
                  v{{ item.app_version }}
                </span>
              </td>
              <td>
                <div v-if="item.pairing_status === 'revoked' || !item.is_active">
                  <span class="badge badge-danger">Revoked</span>
                  <div v-if="item.revocation_reason" style="font-size: 0.7rem; color: #ef4444; margin-top: 0.2rem; max-width: 180px;">
                    {{ item.revocation_reason }}
                  </div>
                </div>
                <div v-else-if="item.pairing_status === 'pending_pairing'">
                  <span class="badge badge-warning">Pending Pairing</span>
                  <div v-if="item.pairing_token" style="font-size: 0.75rem; font-family: monospace; font-weight: 700; color: #f59e0b; margin-top: 0.2rem;">
                    Code: {{ item.pairing_token }}
                  </div>
                </div>
                <div v-else-if="item.is_online" style="display: flex; align-items: center; gap: 0.35rem;">
                  <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #10b981; box-shadow: 0 0 6px #10b981;"></span>
                  <span class="badge badge-success">Online</span>
                </div>
                <div v-else>
                  <span class="badge badge-neutral">Offline</span>
                </div>
              </td>
              <td>
                <span style="font-size: 0.8rem; color: var(--text-muted);">
                  {{ item.last_heartbeat_at || 'Never' }}
                </span>
              </td>
              <td style="text-align: right;">
                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                  <button @click="openEditModal(item)" class="btn btn-secondary" style="padding: 0.25rem 0.55rem; font-size: 0.75rem;">
                    Edit
                  </button>

                  <button
                    v-if="item.pairing_status === 'revoked' || !item.is_active"
                    @click="reactivateTerminal(item)"
                    class="btn btn-primary"
                    style="padding: 0.25rem 0.55rem; font-size: 0.75rem; background: #10b981; border-color: #10b981;"
                  >
                    Reactivate
                  </button>
                  <button
                    v-else
                    @click="openRevokeModal(item)"
                    class="btn btn-danger"
                    style="padding: 0.25rem 0.55rem; font-size: 0.75rem;"
                  >
                    Revoke
                  </button>

                  <button @click="deleteTerminal(item)" class="btn btn-secondary" style="padding: 0.25rem 0.45rem; font-size: 0.75rem; color: #ef4444;">
                    &times;
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="terminals.data.length === 0">
              <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                No POS terminals found matching current filters.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="terminals.links && terminals.links.length > 3" style="display: flex; justify-content: flex-end; padding: 1rem; gap: 0.35rem; border-top: 1px solid var(--border-color);">
        <Link
          v-for="(link, i) in terminals.links"
          :key="i"
          :href="link.url || '#'"
          class="btn btn-secondary"
          :class="{ 'btn-primary': link.active, disabled: !link.url }"
          style="padding: 0.35rem 0.65rem; font-size: 0.75rem;"
          v-html="link.label"
        />
      </div>
    </div>

    <!-- Generate Pairing Code Modal -->
    <div v-if="showPairingModal" class="modal-overlay" @click.self="showPairingModal = false">
      <div class="modal-container" style="max-width: 520px;">
        <div class="modal-header">
          <h3>Generate POS Terminal Pairing Code</h3>
          <button @click="showPairingModal = false" class="close-btn">&times;</button>
        </div>
        <form @submit.prevent="submitPairingForm">
          <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
            <p style="font-size: 0.85rem; color: var(--text-muted);">
              Generates a one-time 8-character pairing code valid for 48 hours. Cashiers or technicians will enter this code on the newly installed desktop POS client.
            </p>

            <div class="form-group">
              <label class="form-label">Merchant Organization *</label>
              <select v-model="pairingForm.tenant_id" class="form-input" required>
                <option value="" disabled>Select Merchant...</option>
                <option v-for="t in tenants" :key="t.id" :value="t.id">
                  {{ t.name }} ({{ t.province.toUpperCase() }})
                </option>
              </select>
              <span v-if="pairingForm.errors.tenant_id" class="form-error">{{ pairingForm.errors.tenant_id }}</span>
            </div>

            <div class="form-group">
              <label class="form-label">Branch Location</label>
              <select v-model="pairingForm.location_id" class="form-input">
                <option value="">Unassigned (Select later)</option>
                <option v-for="loc in filteredLocations" :key="loc.id" :value="loc.id">
                  {{ loc.name }}
                </option>
              </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Terminal Code *</label>
                <input
                  v-model="pairingForm.terminal_code"
                  type="text"
                  class="form-input"
                  placeholder="e.g. POS-REG-01"
                  required
                />
                <span v-if="pairingForm.errors.terminal_code" class="form-error">{{ pairingForm.errors.terminal_code }}</span>
              </div>

              <div class="form-group">
                <label class="form-label">Terminal Name *</label>
                <input
                  v-model="pairingForm.name"
                  type="text"
                  class="form-input"
                  placeholder="e.g. Counter 1 Express"
                  required
                />
                <span v-if="pairingForm.errors.name" class="form-error">{{ pairingForm.errors.name }}</span>
              </div>
            </div>
          </div>

          <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--border-color);">
            <button type="button" @click="showPairingModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="pairingForm.processing">
              {{ pairingForm.processing ? 'Generating...' : 'Generate Code' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Terminal Modal -->
    <div v-if="showEditModal" class="modal-overlay" @click.self="showEditModal = false">
      <div class="modal-container" style="max-width: 480px;">
        <div class="modal-header">
          <h3>Edit Terminal Details</h3>
          <button @click="showEditModal = false" class="close-btn">&times;</button>
        </div>
        <form @submit.prevent="submitEditForm">
          <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
            <div class="form-group">
              <label class="form-label">Terminal Code *</label>
              <input
                v-model="editForm.terminal_code"
                type="text"
                class="form-input"
                required
              />
              <span v-if="editForm.errors.terminal_code" class="form-error">{{ editForm.errors.terminal_code }}</span>
            </div>

            <div class="form-group">
              <label class="form-label">Terminal Name *</label>
              <input
                v-model="editForm.name"
                type="text"
                class="form-input"
                required
              />
              <span v-if="editForm.errors.name" class="form-error">{{ editForm.errors.name }}</span>
            </div>

            <div class="form-group">
              <label class="form-label">Branch Location</label>
              <select v-model="editForm.location_id" class="form-input">
                <option value="">Unassigned</option>
                <option v-for="loc in getLocationsForTenant(editingItem?.tenant_id)" :key="loc.id" :value="loc.id">
                  {{ loc.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--border-color);">
            <button type="button" @click="showEditModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="editForm.processing">
              {{ editForm.processing ? 'Saving...' : 'Update Terminal' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Revoke Terminal Modal -->
    <div v-if="showRevokeModal" class="modal-overlay" @click.self="showRevokeModal = false">
      <div class="modal-container" style="max-width: 460px;">
        <div class="modal-header">
          <h3 style="color: #ef4444;">Remotely Revoke POS Terminal</h3>
          <button @click="showRevokeModal = false" class="close-btn">&times;</button>
        </div>
        <form @submit.prevent="submitRevokeForm">
          <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
            <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.4;">
              Are you sure you want to revoke <strong>{{ revokingItem?.terminal_code }}</strong> ({{ revokingItem?.name }})?
              The physical machine will immediately be rejected during heartbeat checks and will be locked from syncing sales.
            </p>

            <div class="form-group">
              <label class="form-label">Revocation Reason *</label>
              <textarea
                v-model="revokeForm.reason"
                rows="3"
                class="form-input"
                placeholder="e.g. Lost/stolen hardware, rogue cashier, or counter decommissioned..."
                required
              ></textarea>
              <span v-if="revokeForm.errors.reason" class="form-error">{{ revokeForm.errors.reason }}</span>
            </div>
          </div>

          <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--border-color);">
            <button type="button" @click="showRevokeModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-danger" :disabled="revokeForm.processing">
              {{ revokeForm.processing ? 'Revoking...' : 'Revoke Hardware Access' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  terminals: Object,
  metrics: Object,
  tenants: Array,
  locations: Array,
  filters: Object,
});

const filterForm = reactive({
  tenant_id: props.filters?.tenant_id || '',
  status: props.filters?.status || '',
  search: props.filters?.search || '',
  app_version: props.filters?.app_version || '',
});

function applyFilters() {
  router.get('/superadmin/terminals', filterForm, { preserveState: true, replace: true });
}

function resetFilters() {
  filterForm.tenant_id = '';
  filterForm.status = '';
  filterForm.search = '';
  filterForm.app_version = '';
  applyFilters();
}

// Pairing Modal
const showPairingModal = ref(false);
const pairingForm = useForm({
  tenant_id: '',
  location_id: '',
  terminal_code: '',
  name: '',
});

const filteredLocations = computed(() => {
  if (!pairingForm.tenant_id) return [];
  return (props.locations || []).filter(l => l.tenant_id === pairingForm.tenant_id);
});

function openPairingModal() {
  pairingForm.reset();
  pairingForm.clearErrors();
  pairingForm.tenant_id = props.tenants?.[0]?.id || '';
  pairingForm.terminal_code = `POS-${Math.floor(100 + Math.random() * 900)}`;
  pairingForm.name = 'Main Cash Register';
  showPairingModal.value = true;
}

function submitPairingForm() {
  pairingForm.post('/superadmin/terminals/pairing-code', {
    onSuccess: () => {
      showPairingModal.value = false;
    },
  });
}

// Edit Modal
const showEditModal = ref(false);
const editingItem = ref(null);
const editForm = useForm({
  terminal_code: '',
  name: '',
  location_id: '',
});

function getLocationsForTenant(tenantId) {
  if (!tenantId) return [];
  return (props.locations || []).filter(l => l.tenant_id === tenantId);
}

function openEditModal(item) {
  editingItem.value = item;
  editForm.clearErrors();
  editForm.terminal_code = item.terminal_code;
  editForm.name = item.name;
  editForm.location_id = item.location_id || '';
  showEditModal.value = true;
}

function submitEditForm() {
  if (!editingItem.value) return;
  editForm.put(`/superadmin/terminals/${editingItem.value.id}`, {
    onSuccess: () => {
      showEditModal.value = false;
    },
  });
}

// Revoke Modal
const showRevokeModal = ref(false);
const revokingItem = ref(null);
const revokeForm = useForm({
  reason: '',
});

function openRevokeModal(item) {
  revokingItem.value = item;
  revokeForm.reset();
  revokeForm.clearErrors();
  showRevokeModal.value = true;
}

function submitRevokeForm() {
  if (!revokingItem.value) return;
  revokeForm.post(`/superadmin/terminals/${revokingItem.value.id}/revoke`, {
    onSuccess: () => {
      showRevokeModal.value = false;
    },
  });
}

function reactivateTerminal(item) {
  if (confirm(`Reactivate terminal '${item.terminal_code}'?`)) {
    router.post(`/superadmin/terminals/${item.id}/reactivate`);
  }
}

function deleteTerminal(item) {
  if (confirm(`Are you sure you want to delete terminal '${item.terminal_code}'?`)) {
    router.delete(`/superadmin/terminals/${item.id}`);
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 1.5rem;
}

.modal-container {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  width: 100%;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
  animation: modalIn 0.15s ease-out;
}

@keyframes modalIn {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0;
}

.close-btn {
  background: transparent;
  border: none;
  font-size: 1.5rem;
  line-height: 1;
  color: var(--text-muted);
  cursor: pointer;
}

.close-btn:hover {
  color: var(--text-main);
}

.modal-body {
  padding: 1.5rem;
}

.form-error {
  color: #ef4444;
  font-size: 0.75rem;
  margin-top: 0.25rem;
  display: block;
}
</style>

