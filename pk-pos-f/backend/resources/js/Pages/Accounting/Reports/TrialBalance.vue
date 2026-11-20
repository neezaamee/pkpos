<template>
  <AdminLayout title="Trial Balance">
    <div class="report-page">
      <!-- Header -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Trial Balance</h1>
          <p class="page-subtitle">Mathematical equilibrium verification of all General Ledger accounts as of {{ report.as_of_date }}</p>
        </div>
        <div class="header-actions">
          <button @click="printReport" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="6 9 6 2 18 2 18 9"></polyline>
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
              <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print
          </button>
        </div>
      </div>

      <!-- Filters & Balance Banner -->
      <div class="top-row">
        <div class="date-filter">
          <label>As of Date:</label>
          <input type="date" v-model="asOfDate" class="form-input" />
          <button @click="applyFilter" class="btn btn-primary">Update</button>
        </div>

        <div v-if="report.is_balanced" class="status-banner banner-success">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <span><strong>In Balance:</strong> Total Debits equal Total Credits (Rs. {{ formatMoney(report.total_debits_minor) }}).</span>
        </div>
        <div v-else class="status-banner banner-danger">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span><strong>Out of Balance:</strong> Difference of Rs. {{ formatMoney(Math.abs(report.difference_minor)) }}.</span>
        </div>
      </div>

      <!-- Trial Balance Table -->
      <div class="table-container card">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width: 110px;">Code</th>
              <th>Account Name</th>
              <th style="width: 130px;">Classification</th>
              <th style="width: 90px;">Normal</th>
              <th style="width: 140px; text-align: right;">Period Debits</th>
              <th style="width: 140px; text-align: right;">Period Credits</th>
              <th style="width: 150px; text-align: right;">Trial Debit (Rs.)</th>
              <th style="width: 150px; text-align: right;">Trial Credit (Rs.)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in report.rows" :key="row.account_id">
              <td class="font-mono font-bold">{{ row.code }}</td>
              <td>
                <Link :href="`/accounts/${row.account_id}`" class="link-acc">
                  {{ row.name }}
                </Link>
              </td>
              <td>
                <span class="badge" :class="'badge-' + row.type">{{ row.type.toUpperCase() }}</span>
              </td>
              <td class="font-sm font-capitalize text-muted">{{ row.normal_balance }}</td>
              <td class="text-right font-mono text-muted">
                {{ row.period_debits_minor > 0 ? formatMoney(row.period_debits_minor) : '—' }}
              </td>
              <td class="text-right font-mono text-muted">
                {{ row.period_credits_minor > 0 ? formatMoney(row.period_credits_minor) : '—' }}
              </td>
              <td class="text-right font-mono font-bold text-debit">
                {{ row.trial_debit_minor > 0 ? 'Rs. ' + formatMoney(row.trial_debit_minor) : '—' }}
              </td>
              <td class="text-right font-mono font-bold text-credit">
                {{ row.trial_credit_minor > 0 ? 'Rs. ' + formatMoney(row.trial_credit_minor) : '—' }}
              </td>
            </tr>
            <tr v-if="report.rows.length === 0">
              <td colspan="8" class="text-center py-6 text-muted">
                No posted transactions found as of this date.
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="row-totals">
              <td colspan="6" class="text-right font-bold uppercase">Grand Totals:</td>
              <td class="text-right font-mono font-bold text-debit text-lg">
                Rs. {{ formatMoney(report.total_debits_minor) }}
              </td>
              <td class="text-right font-mono font-bold text-credit text-lg">
                Rs. {{ formatMoney(report.total_credits_minor) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  report: {
    as_of_date: string;
    rows: Array<any>;
    total_debits_minor: number;
    total_credits_minor: number;
    difference_minor: number;
    is_balanced: boolean;
  };
  filters: {
    as_of_date?: string;
  };
}>();

const asOfDate = ref(props.filters.as_of_date || props.report.as_of_date);

function applyFilter() {
  router.get('/reports/trial-balance', {
    as_of_date: asOfDate.value || undefined,
  }, { preserveState: true });
}

function printReport() {
  window.print();
}

function formatMoney(minor: number): string {
  const rs = (minor || 0) / 100;
  return rs.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<style scoped>
.report-page {
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
  color: #f8fafc;
  margin: 0;
}
.page-subtitle {
  color: #94a3b8;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.top-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.date-filter {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #cbd5e1;
}

.status-banner {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.65rem 1.25rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.banner-success {
  background: rgba(74, 222, 128, 0.12);
  border: 1px solid rgba(74, 222, 128, 0.3);
  color: #4ade80;
}

.banner-danger {
  background: rgba(248, 113, 113, 0.12);
  border: 1px solid rgba(248, 113, 113, 0.3);
  color: #f87171;
}

.card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th, .data-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #334155;
  font-size: 0.875rem;
}

.data-table th {
  background: rgba(15, 23, 42, 0.6);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  color: #94a3b8;
}

.row-totals {
  background: rgba(15, 23, 42, 0.8);
  font-weight: 700;
}

.link-acc {
  color: #38bdf8;
  text-decoration: none;
  font-weight: 600;
}
.link-acc:hover { text-decoration: underline; }

.badge {
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.2rem 0.45rem;
  border-radius: 0.25rem;
}
.badge-asset { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }
.badge-liability { background: rgba(248, 113, 113, 0.15); color: #f87171; }
.badge-equity { background: rgba(192, 132, 252, 0.15); color: #c084fc; }
.badge-revenue { background: rgba(74, 222, 128, 0.15); color: #4ade80; }
.badge-expense { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }

.text-debit { color: #38bdf8; }
.text-credit { color: #4ade80; }
.text-lg { font-size: 1rem; }

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
}
.btn-primary { background: #0284c7; color: white; }
.btn-secondary { background: #334155; color: #f8fafc; }

.form-input {
  padding: 0.45rem 0.65rem;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 0.375rem;
  color: #f8fafc;
  font-size: 0.875rem;
}

.font-mono { font-family: monospace; }
.font-bold { font-weight: 700; }
.font-sm { font-size: 0.75rem; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.text-muted { color: #94a3b8; }
</style>
