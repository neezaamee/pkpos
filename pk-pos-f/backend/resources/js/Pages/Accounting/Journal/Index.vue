<template>
  <AdminLayout title="General Journal">
    <div class="journal-page">
      <!-- Header -->
      <div class="page-header">
        <div>
          <h1 class="page-title">General Journal</h1>
          <p class="page-subtitle">Chronological ledger of all double-entry debit & credit transactions across PK-POS</p>
        </div>
        <div class="header-actions">
          <Link href="/journal-entries/create" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            New Manual Journal Entry
          </Link>
        </div>
      </div>

      <!-- Metrics -->
      <div class="metrics-grid">
        <div class="metric-card">
          <div class="metric-label">Total Journal Entries</div>
          <div class="metric-value font-mono">{{ metrics.total_entries }}</div>
        </div>
        <div class="metric-card border-automated">
          <div class="metric-label">Automated System Entries</div>
          <div class="metric-value font-mono text-automated">{{ metrics.automated_entries }}</div>
        </div>
        <div class="metric-card border-manual">
          <div class="metric-label">Manual Adjusting Entries</div>
          <div class="metric-value font-mono text-manual">{{ metrics.manual_entries }}</div>
        </div>
      </div>

      <!-- Filters -->
      <div class="filters-bar">
        <div class="filter-group">
          <label>Source:</label>
          <select v-model="filterSource" @change="applyFilters" class="form-select">
            <option value="">All Sources</option>
            <option value="sale">Sales Receipts</option>
            <option value="sale_return">Sales Returns / Credit Notes</option>
            <option value="purchase_order_grn">GRN Purchases</option>
            <option value="customer_payment">Customer Khata Payments</option>
            <option value="supplier_payment">Supplier Disbursements</option>
            <option value="cash_drawer">Drawer Petty Payouts</option>
            <option value="manual">Manual Adjustments</option>
          </select>
        </div>
        <div class="search-group">
          <input
            type="text"
            v-model="searchQuery"
            placeholder="Search entry #, reference, or description..."
            @keyup.enter="applyFilters"
            class="form-input"
          />
          <button @click="applyFilters" class="btn btn-secondary">Search</button>
        </div>
      </div>

      <!-- Journal Table -->
      <div class="table-container card">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width: 140px;">Entry #</th>
              <th style="width: 110px;">Date</th>
              <th style="width: 150px;">Source</th>
              <th style="width: 140px;">Reference</th>
              <th>Description / Narration</th>
              <th style="width: 140px; text-align: right;">Total Debits</th>
              <th style="width: 140px; text-align: right;">Total Credits</th>
              <th style="width: 100px; text-align: center;">Status</th>
              <th style="width: 100px; text-align: center;">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in entries.data" :key="entry.id">
              <td class="font-mono font-bold">
                <Link :href="`/journal-entries/${entry.id}`" class="link-entry">
                  {{ entry.entry_number }}
                </Link>
              </td>
              <td class="font-mono text-muted">{{ entry.entry_date }}</td>
              <td>
                <span class="badge" :class="'badge-' + (entry.source_type || 'manual')">
                  {{ formatSourceType(entry.source_type) }}
                </span>
              </td>
              <td class="font-mono font-sm">{{ entry.reference || '—' }}</td>
              <td>
                <div class="desc-text">{{ entry.description }}</div>
                <div class="lines-preview text-muted font-sm">
                  {{ entry.lines.length }} debit/credit line(s)
                </div>
              </td>
              <td class="text-right font-mono font-bold text-debit">
                Rs. {{ formatMoney(entry.total_debit_minor) }}
              </td>
              <td class="text-right font-mono font-bold text-credit">
                Rs. {{ formatMoney(entry.total_credit_minor) }}
              </td>
              <td class="text-center">
                <span class="badge-status" :class="'status-' + entry.status">{{ entry.status.toUpperCase() }}</span>
              </td>
              <td class="text-center">
                <Link :href="`/journal-entries/${entry.id}`" class="btn-action">
                  View
                </Link>
              </td>
            </tr>
            <tr v-if="entries.data.length === 0">
              <td colspan="9" class="text-center py-6 text-muted">
                No journal entries match the selected filters.
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="entries.links && entries.links.length > 3" class="pagination-bar">
          <Component
            :is="link.url ? Link : 'span'"
            v-for="(link, i) in entries.links"
            :key="i"
            :href="link.url"
            class="pagination-link"
            :class="{ active: link.active, disabled: !link.url }"
            v-html="link.label"
          />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  entries: {
    data: Array<any>;
    links: Array<any>;
  };
  filters: {
    source_type?: string;
    status?: string;
    search?: string;
  };
  metrics: {
    total_entries: number;
    manual_entries: number;
    automated_entries: number;
  };
}>();

const filterSource = ref(props.filters.source_type || '');
const searchQuery = ref(props.filters.search || '');

function applyFilters() {
  router.get('/journal-entries', {
    source_type: filterSource.value || undefined,
    search: searchQuery.value || undefined,
  }, { preserveState: true, replace: true });
}

function formatSourceType(source: string | null): string {
  if (!source || source === 'manual') return 'Manual Entry';
  return source.replace(/_/g, ' ').toUpperCase();
}

function formatMoney(minor: number): string {
  const rs = (minor || 0) / 100;
  return rs.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<style scoped>
.journal-page {
  padding: 1.5rem;
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.page-title {
  font-size: 1.75rem;
  font-weight: 800;
  color: var(--text-main, #f8fafc);
  margin: 0;
}

.page-subtitle {
  color: var(--text-muted, #94a3b8);
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.metric-card {
  background: var(--bg-surface, #1e293b);
  border: 1px solid var(--border-color, #334155);
  border-radius: 0.5rem;
  padding: 1rem;
}

.border-automated { border-left: 4px solid #38bdf8; }
.border-manual { border-left: 4px solid #c084fc; }

.metric-label {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--text-muted, #94a3b8);
}

.metric-value {
  font-size: 1.5rem;
  font-weight: 700;
  margin-top: 0.25rem;
  color: var(--text-main, #f8fafc);
}

.text-automated { color: #38bdf8; }
.text-manual { color: #c084fc; }

.filters-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.filter-group, .search-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.card {
  background: var(--bg-surface, #1e293b);
  border: 1px solid var(--border-color, #334155);
  border-radius: 0.5rem;
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th, .data-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--border-color, #334155);
  font-size: 0.875rem;
}

.data-table th {
  background: rgba(15, 23, 42, 0.6);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-muted, #94a3b8);
}

.link-entry {
  color: #38bdf8;
  text-decoration: none;
}
.link-entry:hover {
  text-decoration: underline;
}

.badge {
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.2rem 0.45rem;
  border-radius: 0.25rem;
}
.badge-sale { background: rgba(74, 222, 128, 0.15); color: #4ade80; }
.badge-sale_return { background: rgba(248, 113, 113, 0.15); color: #f87171; }
.badge-purchase_order_grn { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }
.badge-customer_payment { background: rgba(45, 212, 191, 0.15); color: #2dd4bf; }
.badge-supplier_payment { background: rgba(251, 146, 60, 0.15); color: #fb923c; }
.badge-cash_drawer { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
.badge-manual { background: rgba(192, 132, 252, 0.15); color: #c084fc; }

.badge-status {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 0.15rem 0.4rem;
  border-radius: 0.2rem;
}
.status-posted { background: rgba(74, 222, 128, 0.15); color: #4ade80; }
.status-draft { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }
.status-voided { background: rgba(248, 113, 113, 0.15); color: #f87171; }

.btn-action {
  font-size: 0.75rem;
  font-weight: 600;
  color: #38bdf8;
  padding: 0.25rem 0.5rem;
  border: 1px solid rgba(56, 189, 248, 0.3);
  border-radius: 0.25rem;
  text-decoration: none;
}

.text-debit { color: #38bdf8; }
.text-credit { color: #4ade80; }

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 600;
  border-radius: 0.375rem;
  cursor: pointer;
  border: 1px solid transparent;
  text-decoration: none;
}

.btn-primary { background: #0284c7; color: white; }
.btn-secondary { background: #334155; color: #f8fafc; }

.form-input, .form-select {
  padding: 0.5rem 0.75rem;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 0.375rem;
  color: #f8fafc;
  font-size: 0.875rem;
}

.pagination-bar {
  display: flex;
  justify-content: center;
  padding: 1rem;
  gap: 0.25rem;
  background: rgba(15, 23, 42, 0.4);
}

.pagination-link {
  padding: 0.35rem 0.75rem;
  border-radius: 0.25rem;
  font-size: 0.8125rem;
  color: #94a3b8;
  text-decoration: none;
}
.pagination-link.active {
  background: #0284c7;
  color: white;
  font-weight: 700;
}
.pagination-link.disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.font-mono { font-family: monospace; }
.font-bold { font-weight: 700; }
.font-sm { font-size: 0.75rem; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.text-muted { color: #94a3b8; }
</style>
