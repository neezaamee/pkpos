<template>
  <SuperAdminLayout title="Helpdesk & Support Ticketing">
    <!-- Header Summary -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h2 style="font-size: 1.25rem; font-weight: 700;">Customer Support & Helpdesk Hub</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          Triage merchant inquiries, resolve FBR fiscalization errors, and inspect diagnostic machine snapshots.
        </p>
      </div>
      <div style="display: flex; gap: 0.75rem; align-items: center;">
        <Link href="/superadmin/support/knowledge-base" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.4rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
          Knowledge Base & Canned Guides
        </Link>
      </div>
    </div>

    <!-- Ticket Metrics KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Open Support Queue
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #38bdf8;">
          {{ metrics.open_count }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Pending staff intervention
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="display: flex; align-items: center; gap: 0.4rem;">
          <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ef4444; box-shadow: 0 0 8px #ef4444;"></span>
          <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
            Critical (Counter Down)
          </div>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #ef4444;">
          {{ metrics.critical_count }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Hardware or fiscal stoppage
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Resolved Today
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #10b981;">
          {{ metrics.resolved_today_count }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Successfully closed today
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Total Platform Tickets
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: var(--text-main);">
          {{ metrics.total_tickets }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Historical support volume
        </div>
      </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card-table" style="padding: 1.25rem; margin-bottom: 1.5rem;">
      <form @submit.prevent="applyFilters" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Search Ticket or Submitter</label>
          <input
            v-model="filterForm.search"
            type="text"
            class="form-input"
            placeholder="TCK-1001, subject, email..."
          />
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Status</label>
          <select v-model="filterForm.status" class="form-input">
            <option value="">All Statuses</option>
            <option value="open">Open</option>
            <option value="in_progress">In Progress</option>
            <option value="resolved">Resolved</option>
            <option value="closed">Closed</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Priority</label>
          <select v-model="filterForm.priority" class="form-input">
            <option value="">All Priorities</option>
            <option value="critical_counter_down">Critical (Counter Down)</option>
            <option value="high">High</option>
            <option value="medium">Medium</option>
            <option value="low">Low</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Category</label>
          <select v-model="filterForm.category" class="form-input">
            <option value="">All Categories</option>
            <option value="fbr_sync_failure">FBR Sync Failure</option>
            <option value="hardware_printer">Hardware & Printer</option>
            <option value="tax_calculation">Tax Calculation</option>
            <option value="billing">Account & Billing</option>
            <option value="general">General Support</option>
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

    <!-- Tickets Stream Table -->
    <div class="card-table">
      <div class="table-header">
        <h2>Active Support Queue</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">
          Showing {{ tickets.from || 0 }} to {{ tickets.to || 0 }} of {{ tickets.total }} tickets
        </span>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Ticket #</th>
              <th>Subject & Category</th>
              <th>Merchant Tenant</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Assigned Staff</th>
              <th>Updated</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in tickets.data" :key="item.id">
              <td>
                <span style="font-family: monospace; font-size: 0.85rem; font-weight: 700; color: #38bdf8;">
                  {{ item.ticket_number }}
                </span>
                <span v-if="item.has_snapshot" class="badge badge-neutral" style="font-size: 0.65rem; margin-top: 0.2rem; display: block; max-width: fit-content;">
                  Snapshot Attached
                </span>
              </td>
              <td>
                <div style="font-weight: 600; font-size: 0.9rem;">{{ item.subject }}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                  Category: <strong>{{ formatCategory(item.category) }}</strong> &bull; By: {{ item.user_name }}
                </div>
              </td>
              <td>
                <div style="font-weight: 500;">{{ item.tenant_name }}</div>
                <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;">
                  {{ item.tenant_province }}
                </div>
              </td>
              <td>
                <span class="badge" :class="getPriorityBadgeClass(item.priority)">
                  {{ formatPriority(item.priority) }}
                </span>
              </td>
              <td>
                <span class="badge" :class="getStatusBadgeClass(item.status)">
                  {{ formatStatus(item.status) }}
                </span>
              </td>
              <td>
                <span style="font-size: 0.82rem; color: var(--text-main);">
                  {{ item.assigned_to_name }}
                </span>
              </td>
              <td>
                <span style="font-size: 0.8rem; color: var(--text-muted);">
                  {{ item.updated_at }}
                </span>
              </td>
              <td style="text-align: right;">
                <Link :href="`/superadmin/support/tickets/${item.id}`" class="btn btn-secondary" style="padding: 0.3rem 0.65rem; font-size: 0.78rem;">
                  Open Ticket &rarr;
                </Link>
              </td>
            </tr>
            <tr v-if="tickets.data.length === 0">
              <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                No support tickets found matching the selected filters.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="tickets.links && tickets.links.length > 3" style="display: flex; justify-content: flex-end; padding: 1rem; gap: 0.35rem; border-top: 1px solid var(--border-color);">
        <Link
          v-for="(link, i) in tickets.links"
          :key="i"
          :href="link.url || '#'"
          class="btn btn-secondary"
          :class="{ 'btn-primary': link.active, disabled: !link.url }"
          style="padding: 0.35rem 0.65rem; font-size: 0.75rem;"
          v-html="link.label"
        />
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  tickets: Object,
  metrics: Object,
  tenants: Array,
  filters: Object,
});

const filterForm = reactive({
  status: props.filters?.status || '',
  priority: props.filters?.priority || '',
  category: props.filters?.category || '',
  tenant_id: props.filters?.tenant_id || '',
  search: props.filters?.search || '',
});

function applyFilters() {
  router.get('/superadmin/support/tickets', filterForm, { preserveState: true, replace: true });
}

function resetFilters() {
  filterForm.status = '';
  filterForm.priority = '';
  filterForm.category = '';
  filterForm.tenant_id = '';
  filterForm.search = '';
  applyFilters();
}

function formatCategory(c) {
  const map = {
    fbr_sync_failure: 'FBR Sync Failure',
    hardware_printer: 'Hardware & Printer',
    tax_calculation: 'Tax Calculation',
    billing: 'Account & Billing',
    general: 'General Support',
  };
  return map[c] || c;
}

function formatPriority(p) {
  const map = {
    critical_counter_down: 'Critical (Counter Down)',
    high: 'High',
    medium: 'Medium',
    low: 'Low',
  };
  return map[p] || p;
}

function formatStatus(s) {
  const map = {
    open: 'Open',
    in_progress: 'In Progress',
    resolved: 'Resolved',
    closed: 'Closed',
  };
  return map[s] || s;
}

function getPriorityBadgeClass(p) {
  switch (p) {
    case 'critical_counter_down': return 'badge-danger';
    case 'high': return 'badge-warning';
    case 'medium': return 'badge-neutral';
    case 'low': return 'badge-neutral';
    default: return 'badge-neutral';
  }
}

function getStatusBadgeClass(s) {
  switch (s) {
    case 'open': return 'badge-warning';
    case 'in_progress': return 'badge-neutral';
    case 'resolved': return 'badge-success';
    case 'closed': return 'badge-neutral';
    default: return 'badge-neutral';
  }
}
</script>

