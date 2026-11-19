<template>
  <AdminLayout title="Register Shifts & Z-Reports">
    <!-- Active Shift Real-Time Banner (if an open shift exists) -->
    <div v-if="active_shift" class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(6, 182, 212, 0.08) 100%); border: 1px solid rgba(16, 185, 129, 0.3);">
      <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
          <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(16, 185, 129, 0.2); color: #10b981; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="4" width="20" height="16" rx="2"></rect>
              <line x1="2" y1="10" x2="22" y2="10"></line>
              <line x1="7" y1="15" x2="7.01" y2="15"></line>
              <line x1="11" y1="15" x2="13" y2="15"></line>
            </svg>
          </div>
          <div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-weight: 700;">
                <span class="badge-dot" style="background: #34d399;"></span>
                REGISTER ACTIVE &amp; OPEN
              </span>
              <span style="font-size: 0.8rem; color: var(--text-muted);">
                Opened {{ formatTime(active_shift.opened_at) }} by <strong>{{ active_shift.cashier_name }}</strong>
              </span>
            </div>
            <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-top: 0.2rem;">
              {{ active_shift.location_name }} &bull; Terminal {{ active_shift.terminal_code }}
            </div>
          </div>
        </div>

        <!-- Quick Telemetry Stats -->
        <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
          <div>
            <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Opening Float</div>
            <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); font-family: var(--font-mono);">
              {{ formatMoney(active_shift.opening_float_minor) }}
            </div>
          </div>
          <div>
            <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Cash in Drawer</div>
            <div style="font-size: 1.05rem; font-weight: 700; color: #10b981; font-family: var(--font-mono);">
              {{ formatMoney(active_shift.cash_drawer_reconciliation.expected_cash) }}
            </div>
          </div>
          <div>
            <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Gross Shift Volume</div>
            <div style="font-size: 1.05rem; font-weight: 700; color: #6366f1; font-family: var(--font-mono);">
              {{ formatMoney(active_shift.gross_sales_minor) }} ({{ active_shift.sales_count }} sales)
            </div>
          </div>
          <Link
            :href="`/shifts/${active_shift.session_id}`"
            class="btn btn-primary"
            style="display: flex; align-items: center; gap: 0.5rem; padding: 0.45rem 0.9rem;"
          >
            <span>Live X-Report</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </Link>
        </div>
      </div>
    </div>

    <!-- Shifts & Z-Reports Table Card -->
    <div class="card-table">
      <div class="table-header">
        <div>
          <h2>Register Shift Sessions &amp; Fiscal Z-Reports</h2>
          <div style="font-size: 0.825rem; color: var(--text-muted);">
            Immutable cash drawer audit trail, daily register closures, and cash variance tracking
          </div>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Z-Report #</th>
              <th>Location / Device</th>
              <th>Cashier</th>
              <th>Opened</th>
              <th>Closed</th>
              <th>Opening Float</th>
              <th>Expected Cash</th>
              <th>Counted Cash</th>
              <th>Variance</th>
              <th>Status</th>
              <th>Audit</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="shift in shifts.data" :key="shift.id">
              <td style="font-family: var(--font-mono); font-weight: 700; color: var(--accent-primary);">
                {{ shift.z_report_number || ('SES-' + shift.id.substring(0, 8).toUpperCase()) }}
              </td>
              <td>
                <div style="font-weight: 600; color: var(--text-primary);">{{ shift.location?.name || 'Main Branch' }}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ shift.terminal?.name || 'Terminal' }}</div>
              </td>
              <td>
                <div style="font-weight: 500;">{{ shift.user?.name || 'Cashier' }}</div>
                <div v-if="shift.closed_by_user" style="font-size: 0.7rem; color: var(--text-muted);">
                  Closed: {{ shift.closed_by_user.name }}
                </div>
              </td>
              <td style="font-size: 0.8rem;">
                {{ formatDate(shift.opened_at) }}
              </td>
              <td style="font-size: 0.8rem;">
                {{ shift.closed_at ? formatDate(shift.closed_at) : 'Active / In Progress' }}
              </td>
              <td style="font-family: var(--font-mono);">
                {{ formatMoney(shift.opening_float_minor) }}
              </td>
              <td style="font-family: var(--font-mono); font-weight: 600;">
                {{ formatMoney(shift.expected_cash_minor) }}
              </td>
              <td style="font-family: var(--font-mono); font-weight: 700;">
                {{ shift.closing_cash_minor !== null ? formatMoney(shift.closing_cash_minor) : '—' }}
              </td>
              <td>
                <span
                  v-if="shift.variance_minor !== null"
                  class="badge"
                  :style="shift.variance_minor === 0
                    ? 'background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 700;'
                    : (shift.variance_minor > 0
                        ? 'background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); font-weight: 700;'
                        : 'background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); font-weight: 700;')"
                >
                  {{ shift.variance_minor === 0 ? 'Exact (+Rs 0)' : (shift.variance_minor > 0 ? `+${formatMoney(shift.variance_minor)} (Over)` : `${formatMoney(shift.variance_minor)} (Short)`) }}
                </span>
                <span v-else style="color: var(--text-muted); font-size: 0.8rem;">In Progress</span>
              </td>
              <td>
                <span
                  class="badge"
                  :style="shift.status === 'open'
                    ? 'background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3);'
                    : 'background: rgba(100, 116, 139, 0.15); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.3);'"
                >
                  {{ shift.status.toUpperCase() }}
                </span>
              </td>
              <td>
                <Link
                  :href="`/shifts/${shift.id}`"
                  class="btn btn-outline"
                  style="padding: 0.25rem 0.6rem; font-size: 0.75rem;"
                >
                  Inspect
                </Link>
              </td>
            </tr>

            <tr v-if="shifts.data.length === 0">
              <td colspan="11" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                No register shift sessions recorded yet. Shifts will appear once cashiers open registers in the POS client.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="shifts.links && shifts.links.length > 3" style="padding: 1rem; display: flex; justify-content: flex-end; gap: 0.35rem;">
        <Link
          v-for="(link, i) in shifts.links"
          :key="i"
          :href="link.url || '#'"
          v-html="link.label"
          class="btn btn-outline"
          :class="{ 'active': link.active, 'disabled': !link.url }"
          style="padding: 0.25rem 0.65rem; font-size: 0.75rem;"
        />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  shifts: {
    type: Object,
    required: true,
  },
  active_shift: {
    type: Object,
    default: null,
  },
});

function formatMoney(minor) {
  if (minor === undefined || minor === null) return 'Rs. 0';
  const val = minor / 100;
  return 'Rs. ' + val.toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatDate(isoStr) {
  if (!isoStr) return '—';
  const d = new Date(isoStr);
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
    ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}

function formatTime(isoStr) {
  if (!isoStr) return '—';
  const d = new Date(isoStr);
  return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}
</script>
