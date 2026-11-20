<template>
  <AdminLayout :title="`Ledger - ${ledger.account.code} ${ledger.account.name}`">
    <div class="ledger-page">
      <!-- Breadcrumb / Header -->
      <div class="page-header">
        <div>
          <div class="breadcrumb">
            <Link href="/accounts" class="breadcrumb-link">Chart of Accounts</Link>
            <span class="breadcrumb-sep">/</span>
            <span>Account Statement</span>
          </div>
          <h1 class="page-title">{{ ledger.account.code }} — {{ ledger.account.name }}</h1>
          <p class="page-subtitle">
            Classification: <span class="badge" :class="'badge-' + ledger.account.type">{{ ledger.account.type.toUpperCase() }}</span>
            &bull; Normal Balance: <strong>{{ ledger.account.normal_balance.toUpperCase() }}</strong>
          </p>
        </div>
        <div class="header-actions">
          <Link href="/accounts" class="btn btn-secondary">
            &larr; Back to COA
          </Link>
          <button @click="printStatement" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="6 9 6 2 18 2 18 9"></polyline>
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
              <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print
          </button>
        </div>
      </div>

      <!-- Date Filters & Summary Cards -->
      <div class="summary-grid">
        <div class="summary-card">
          <div class="summary-label">Opening Balance</div>
          <div class="summary-value font-mono">Rs. {{ formatMoney(ledger.opening_balance_minor) }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">Period Debits (+)</div>
          <div class="summary-value font-mono text-debit">Rs. {{ formatMoney(ledger.total_debit_minor) }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">Period Credits (-)</div>
          <div class="summary-value font-mono text-credit">Rs. {{ formatMoney(ledger.total_credit_minor) }}</div>
        </div>
        <div class="summary-card card-closing">
          <div class="summary-label">Closing Net Balance</div>
          <div class="summary-value font-mono text-highlight">Rs. {{ formatMoney(ledger.closing_balance_minor) }}</div>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="filters-bar">
        <div class="filter-group">
          <label>From:</label>
          <input type="date" v-model="startDate" class="form-input" />
        </div>
        <div class="filter-group">
          <label>To:</label>
          <input type="date" v-model="endDate" class="form-input" />
        </div>
        <button @click="applyDates" class="btn btn-primary">Filter Statement</button>
      </div>

      <!-- Transactions Ledger Table -->
      <div class="table-container card">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width: 110px;">Date</th>
              <th style="width: 140px;">Entry #</th>
              <th style="width: 140px;">Source / Ref</th>
              <th>Description / Memo</th>
              <th style="width: 130px; text-align: right;">Debit</th>
              <th style="width: 130px; text-align: right;">Credit</th>
              <th style="width: 150px; text-align: right;">Running Balance</th>
            </tr>
          </thead>
          <tbody>
            <!-- Opening Balance Row -->
            <tr class="row-opening">
              <td class="font-mono text-muted">{{ ledger.start_date }}</td>
              <td colspan="5" class="font-bold">Opening Balance Brought Forward</td>
              <td class="text-right font-mono font-bold">Rs. {{ formatMoney(ledger.opening_balance_minor) }}</td>
            </tr>

            <!-- Line Items -->
            <tr v-for="item in ledger.transactions" :key="item.id">
              <td class="font-mono">{{ item.entry_date }}</td>
              <td class="font-mono">
                <Link :href="`/journal-entries/${item.entry_id}`" class="link-entry">
                  {{ item.entry_number }}
                </Link>
              </td>
              <td>
                <span class="badge-source">{{ item.source_type || 'manual' }}</span>
                <span v-if="item.reference" class="ref-text font-mono">{{ item.reference }}</span>
              </td>
              <td>
                <div>{{ item.description }}</div>
                <div v-if="item.memo" class="memo-text">{{ item.memo }}</div>
              </td>
              <td class="text-right font-mono" :class="{ 'text-debit': item.debit_minor > 0 }">
                {{ item.debit_minor > 0 ? 'Rs. ' + formatMoney(item.debit_minor) : '—' }}
              </td>
              <td class="text-right font-mono" :class="{ 'text-credit': item.credit_minor > 0 }">
                {{ item.credit_minor > 0 ? 'Rs. ' + formatMoney(item.credit_minor) : '—' }}
              </td>
              <td class="text-right font-mono font-bold">
                Rs. {{ formatMoney(item.running_balance_minor) }}
              </td>
            </tr>

            <tr v-if="ledger.transactions.length === 0">
              <td colspan="7" class="text-center py-6 text-muted">
                No transactions recorded for this account during the selected date range.
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="row-totals">
              <td colspan="4" class="text-right font-bold">Period Totals:</td>
              <td class="text-right font-mono font-bold text-debit">Rs. {{ formatMoney(ledger.total_debit_minor) }}</td>
              <td class="text-right font-mono font-bold text-credit">Rs. {{ formatMoney(ledger.total_credit_minor) }}</td>
              <td class="text-right font-mono font-bold text-highlight">Rs. {{ formatMoney(ledger.closing_balance_minor) }}</td>
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
  ledger: {
    account: any;
    start_date: string;
    end_date: string;
    opening_balance_minor: number;
    total_debit_minor: number;
    total_credit_minor: number;
    closing_balance_minor: number;
    transactions: Array<any>;
  };
  filters: {
    start_date?: string;
    end_date?: string;
  };
}>();

const startDate = ref(props.filters.start_date || props.ledger.start_date);
const endDate = ref(props.filters.end_date || props.ledger.end_date);

function applyDates() {
  router.get(`/accounts/${props.ledger.account.id}`, {
    start_date: startDate.value || undefined,
    end_date: endDate.value || undefined,
  }, { preserveState: true });
}

function printStatement() {
  window.print();
}

function formatMoney(minor: number): string {
  const rs = (minor || 0) / 100;
  return rs.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<style scoped>
.ledger-page {
  padding: 1.5rem;
  max-width: 1400px;
  margin: 0 auto;
}

.breadcrumb {
  display: flex;
  gap: 0.5rem;
  font-size: 0.8125rem;
  color: var(--text-muted, #94a3b8);
  margin-bottom: 0.25rem;
}

.breadcrumb-link {
  color: #38bdf8;
  text-decoration: none;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-main, #f8fafc);
  margin: 0;
}

.page-subtitle {
  color: var(--text-muted, #94a3b8);
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.summary-card {
  background: var(--bg-surface, #1e293b);
  border: 1px solid var(--border-color, #334155);
  border-radius: 0.5rem;
  padding: 1rem;
}

.card-closing {
  border-left: 4px solid #38bdf8;
}

.summary-label {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--text-muted, #94a3b8);
}

.summary-value {
  font-size: 1.25rem;
  font-weight: 700;
  margin-top: 0.25rem;
  color: var(--text-main, #f8fafc);
}

.text-debit { color: #38bdf8; }
.text-credit { color: #f87171; }
.text-highlight { color: #4ade80; }

.filters-bar {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.filter-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: var(--text-muted, #cbd5e1);
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

.row-opening {
  background: rgba(56, 189, 248, 0.04);
}

.row-totals {
  background: rgba(15, 23, 42, 0.7);
  font-weight: 700;
}

.link-entry {
  color: #38bdf8;
  text-decoration: none;
  font-weight: 600;
}
.link-entry:hover {
  text-decoration: underline;
}

.badge-source {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 0.15rem 0.35rem;
  border-radius: 0.2rem;
  background: rgba(148, 163, 184, 0.15);
  color: #94a3b8;
  margin-right: 0.35rem;
}

.ref-text {
  font-size: 0.75rem;
  color: var(--text-muted, #94a3b8);
}

.memo-text {
  font-size: 0.75rem;
  color: var(--text-muted, #64748b);
  margin-top: 0.15rem;
}

.badge {
  padding: 0.15rem 0.4rem;
  border-radius: 0.2rem;
  font-size: 0.7rem;
  font-weight: 700;
}
.badge-asset { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }
.badge-liability { background: rgba(248, 113, 113, 0.15); color: #f87171; }
.badge-equity { background: rgba(192, 132, 252, 0.15); color: #c084fc; }
.badge-revenue { background: rgba(74, 222, 128, 0.15); color: #4ade80; }
.badge-expense { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }

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
.text-right { text-align: right; }
.text-center { text-align: center; }
.text-muted { color: #94a3b8; }
</style>
