<template>
  <SuperAdminLayout title="Audit Logs & Compliance Trail">
    <!-- Header Summary -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h2 style="font-size: 1.25rem; font-weight: 700;">Platform Mutation Audit Trail</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          Immutable activity log tracking all platform and tenant mutations, credentials, and impersonation sessions.
        </p>
      </div>
      <div style="display: flex; gap: 0.5rem; align-items: center;">
        <span class="badge badge-neutral" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">
          Total Logs: {{ logs.total }}
        </span>
      </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card-table" style="padding: 1.25rem; margin-bottom: 1.5rem;">
      <form @submit.prevent="applyFilters" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end;">
        <!-- Search Keyword -->
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Search Actor, Target or IP</label>
          <input
            v-model="filterForm.search"
            type="text"
            class="form-input"
            placeholder="e.g. email, UUID, or 127.0.0.1"
          />
        </div>

        <!-- Event Type -->
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Event Action</label>
          <select v-model="filterForm.event" class="form-input">
            <option value="">All Events</option>
            <option value="created">Created</option>
            <option value="updated">Updated</option>
            <option value="deleted">Deleted</option>
            <option value="impersonated">Impersonated</option>
            <option value="impersonation_ended">Impersonation Ended</option>
            <option value="suspended">Suspended</option>
            <option value="reactivated">Reactivated</option>
          </select>
        </div>

        <!-- Tenant Dropdown -->
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Organization / Tenant</label>
          <select v-model="filterForm.tenant_id" class="form-input">
            <option value="">All Tenants (Global)</option>
            <option v-for="t in tenants" :key="t.id" :value="t.id">
              {{ t.name }}
            </option>
          </select>
        </div>

        <!-- Target Model Type -->
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Resource Type</label>
          <select v-model="filterForm.auditable_type" class="form-input">
            <option value="">All Resource Types</option>
            <option value="Tenant">Tenant</option>
            <option value="User">User</option>
            <option value="Location">Location</option>
            <option value="Product">Product</option>
            <option value="TaxRule">TaxRule</option>
            <option value="Sale">Sale</option>
            <option value="FbrCredential">FbrCredential</option>
          </select>
        </div>

        <!-- Action Buttons -->
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

    <!-- Audit Logs Stream Table -->
    <div class="card-table">
      <div class="table-header">
        <h2>Audit Log Stream</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">Showing {{ logs.from || 0 }} to {{ logs.to || 0 }} of {{ logs.total }}</span>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Action</th>
              <th>Actor</th>
              <th>Tenant</th>
              <th>Target Resource</th>
              <th>Network & IP</th>
              <th style="text-align: right;">Changes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="logs.data.length === 0">
              <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2.5rem 1rem;">
                No audit logs found matching your filter criteria.
              </td>
            </tr>
            <tr v-for="log in logs.data" :key="log.id">
              <!-- Timestamp -->
              <td style="white-space: nowrap; font-size: 0.82rem; color: var(--text-secondary);">
                {{ log.created_at }}
              </td>

              <!-- Event Badge -->
              <td>
                <span class="badge" :style="getEventBadgeStyle(log.event)" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">
                  {{ formatEventName(log.event) }}
                </span>
              </td>

              <!-- Actor -->
              <td>
                <div v-if="log.user" style="display: flex; flex-direction: column;">
                  <span style="font-weight: 600; font-size: 0.85rem;">
                    {{ log.user.name }}
                    <span v-if="log.user.is_super_admin" class="badge" style="background: rgba(236, 72, 153, 0.2); color: #f472b6; font-size: 0.65rem; padding: 0.1rem 0.35rem; margin-left: 0.25rem;">
                      Super Admin
                    </span>
                  </span>
                  <span style="font-size: 0.75rem; color: var(--text-muted);">{{ log.user.email }}</span>
                </div>
                <span v-else style="font-size: 0.8rem; color: var(--text-muted); font-style: italic;">
                  System / Unauthenticated
                </span>
              </td>

              <!-- Tenant -->
              <td>
                <div v-if="log.tenant">
                  <span style="font-weight: 600; font-size: 0.85rem;">{{ log.tenant.name }}</span>
                  <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; display: block;">{{ log.tenant.province }}</span>
                </div>
                <span v-else style="font-size: 0.78rem; color: #818cf8; font-weight: 600;">
                  Platform Wide
                </span>
              </td>

              <!-- Target Resource -->
              <td>
                <div style="font-size: 0.85rem; font-weight: 600;">{{ log.auditable_type }}</div>
                <div style="font-size: 0.72rem; color: var(--text-muted); font-family: monospace;" :title="log.auditable_id">
                  {{ log.auditable_id ? log.auditable_id.substring(0, 13) + '...' : '—' }}
                </div>
              </td>

              <!-- Network -->
              <td>
                <div style="font-size: 0.8rem; font-family: monospace; color: var(--text-secondary);">
                  {{ log.ip_address || '127.0.0.1' }}
                </div>
              </td>

              <!-- Changes Modal Trigger -->
              <td style="text-align: right;">
                <button
                  v-if="log.old_values || log.new_values"
                  @click="openDetailsModal(log)"
                  class="btn btn-secondary"
                  style="padding: 0.3rem 0.65rem; font-size: 0.75rem;"
                >
                  View Diff
                </button>
                <span v-else style="font-size: 0.75rem; color: var(--text-muted);">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="logs.links && logs.links.length > 3" class="pagination" style="padding: 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.35rem;">
        <Link
          v-for="(link, i) in logs.links"
          :key="i"
          :href="link.url || '#'"
          class="btn btn-secondary"
          :class="{ active: link.active, disabled: !link.url }"
          style="padding: 0.35rem 0.65rem; font-size: 0.78rem;"
          v-html="link.label"
        />
      </div>
    </div>

    <!-- Modal: View JSON Diff -->
    <div v-if="showModal && selectedLog" class="modal-backdrop">
      <div class="modal-card" style="max-width: 650px;">
        <div class="modal-header">
          <div>
            <h3 style="font-size: 1.05rem;">
              Mutation Details: <span style="color: #ec4899;">{{ formatEventName(selectedLog.event) }}</span> on {{ selectedLog.auditable_type }}
            </h3>
            <span style="font-size: 0.75rem; color: var(--text-muted);">
              Timestamp: {{ selectedLog.created_at }} • Target ID: {{ selectedLog.auditable_id }}
            </span>
          </div>
          <button @click="showModal = false" class="modal-close">&times;</button>
        </div>

        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
          <!-- Diff Comparison -->
          <div v-if="selectedLog.event === 'updated'" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
              <h4 style="font-size: 0.8rem; font-weight: 700; color: #fb7185; margin: 0 0 0.5rem 0; text-transform: uppercase;">
                Previous Values (Before)
              </h4>
              <pre class="json-code-box">{{ formatJson(selectedLog.old_values) }}</pre>
            </div>
            <div>
              <h4 style="font-size: 0.8rem; font-weight: 700; color: #34d399; margin: 0 0 0.5rem 0; text-transform: uppercase;">
                New Values (After)
              </h4>
              <pre class="json-code-box">{{ formatJson(selectedLog.new_values) }}</pre>
            </div>
          </div>

          <!-- Single Payload (Created, Deleted, Impersonated) -->
          <div v-else>
            <h4 style="font-size: 0.8rem; font-weight: 700; color: #818cf8; margin: 0 0 0.5rem 0; text-transform: uppercase;">
              Mutation Payload
            </h4>
            <pre class="json-code-box">{{ formatJson(selectedLog.new_values || selectedLog.old_values) }}</pre>
          </div>

          <!-- User Agent & Environment Details -->
          <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-color); font-size: 0.78rem; color: var(--text-muted);">
            <div><strong>IP Address:</strong> {{ selectedLog.ip_address || '127.0.0.1' }}</div>
            <div style="margin-top: 0.25rem; word-break: break-all;"><strong>User Agent:</strong> {{ selectedLog.user_agent || 'N/A' }}</div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" @click="showModal = false" class="btn btn-secondary">
            Close
          </button>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  logs: {
    type: Object,
    required: true,
  },
  tenants: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const filterForm = ref({
  search: props.filters.search || '',
  event: props.filters.event || '',
  tenant_id: props.filters.tenant_id || '',
  auditable_type: props.filters.auditable_type || '',
});

function applyFilters() {
  router.get('/superadmin/audit-logs', filterForm.value, {
    preserveState: true,
    preserveScroll: true,
  });
}

function resetFilters() {
  filterForm.value = {
    search: '',
    event: '',
    tenant_id: '',
    auditable_type: '',
  };
  applyFilters();
}

const showModal = ref(false);
const selectedLog = ref(null);

function openDetailsModal(log) {
  selectedLog.value = log;
  showModal.value = true;
}

function formatJson(val) {
  if (!val) return 'None';
  try {
    return JSON.stringify(val, null, 2);
  } catch (e) {
    return String(val);
  }
}

function formatEventName(event) {
  if (!event) return 'Event';
  return event.replace(/_/g, ' ');
}

function getEventBadgeStyle(event) {
  if (event === 'created') {
    return 'background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3);';
  }
  if (event === 'updated') {
    return 'background: rgba(6, 182, 212, 0.15); color: #22d3ee; border: 1px solid rgba(6, 182, 212, 0.3);';
  }
  if (event === 'deleted') {
    return 'background: rgba(244, 63, 94, 0.15); color: #fb7185; border: 1px solid rgba(244, 63, 94, 0.3);';
  }
  if (event === 'impersonated') {
    return 'background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3);';
  }
  if (event === 'impersonation_ended') {
    return 'background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3);';
  }
  if (event === 'suspended') {
    return 'background: rgba(225, 29, 72, 0.15); color: #f43f5e; border: 1px solid rgba(225, 29, 72, 0.3);';
  }
  return 'background: rgba(99, 102, 241, 0.15); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.3);';
}
</script>

<style scoped>
.json-code-box {
  background: #0f172a;
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 0.75rem;
  font-size: 0.75rem;
  font-family: monospace;
  color: #38bdf8;
  white-space: pre-wrap;
  word-break: break-all;
  max-height: 250px;
  overflow-y: auto;
  margin: 0;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 1rem;
}

.modal-card {
  background: #1e293b;
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  width: 100%;
  max-width: 600px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  overflow: hidden;
}

.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-header h3 {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0;
}

.modal-close {
  background: transparent;
  border: none;
  color: var(--text-muted);
  font-size: 1.5rem;
  cursor: pointer;
  line-height: 1;
  padding: 0;
}

.modal-close:hover {
  color: #fff;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border-color);
  background: rgba(15, 23, 42, 0.4);
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}
</style>

