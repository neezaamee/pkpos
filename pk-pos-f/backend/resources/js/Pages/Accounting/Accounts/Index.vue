<template>
  <AdminLayout title="Chart of Accounts">
    <div class="coa-page">
      <!-- Header -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Chart of Accounts</h1>
          <p class="page-subtitle">Standard Pakistani SME General Ledger accounts, hierarchies, and real-time balances</p>
        </div>
        <div class="header-actions">
          <button @click="syncHistorical" class="btn btn-secondary" :disabled="isSyncing">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="23 4 23 10 17 10"></polyline>
              <polyline points="1 20 1 14 7 14"></polyline>
              <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
            </svg>
            {{ isSyncing ? 'Syncing...' : 'Sync Past Sales & Bills' }}
          </button>
          <button @click="showModal = true" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            New Account
          </button>
        </div>
      </div>

      <!-- Metrics Row -->
      <div class="metrics-grid">
        <div class="metric-card">
          <div class="metric-label">Total Accounts</div>
          <div class="metric-value">{{ metrics.total_accounts }}</div>
        </div>
        <div class="metric-card border-asset">
          <div class="metric-label">Assets (1000s)</div>
          <div class="metric-value text-asset">{{ metrics.assets_count }}</div>
        </div>
        <div class="metric-card border-liability">
          <div class="metric-label">Liabilities (2000s)</div>
          <div class="metric-value text-liability">{{ metrics.liabilities_count }}</div>
        </div>
        <div class="metric-card border-equity">
          <div class="metric-label">Equity (3000s)</div>
          <div class="metric-value text-equity">{{ metrics.equity_count }}</div>
        </div>
        <div class="metric-card border-revenue">
          <div class="metric-label">Revenue (4000s)</div>
          <div class="metric-value text-revenue">{{ metrics.revenue_count }}</div>
        </div>
        <div class="metric-card border-expense">
          <div class="metric-label">Expenses (5000-6000s)</div>
          <div class="metric-value text-expense">{{ metrics.expense_count }}</div>
        </div>
      </div>

      <!-- Filters Row -->
      <div class="filters-bar">
        <div class="filter-group">
          <label>Type Filter:</label>
          <select v-model="filterType" @change="applyFilters" class="form-select">
            <option value="">All Account Types</option>
            <option value="asset">Assets</option>
            <option value="liability">Liabilities</option>
            <option value="equity">Equity</option>
            <option value="revenue">Revenue</option>
            <option value="expense">Expenses</option>
          </select>
        </div>
        <div class="search-group">
          <input
            type="text"
            v-model="searchQuery"
            placeholder="Search by code or account name..."
            @keyup.enter="applyFilters"
            class="form-input"
          />
          <button @click="applyFilters" class="btn btn-secondary">Filter</button>
        </div>
      </div>

      <!-- Accounts Table -->
      <div class="table-container card">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width: 120px;">Code</th>
              <th>Account Name</th>
              <th style="width: 140px;">Classification</th>
              <th style="width: 140px;">Sub-Type</th>
              <th style="width: 100px;">Normal</th>
              <th style="width: 90px;">System</th>
              <th style="width: 160px; text-align: right;">Current Balance</th>
              <th style="width: 120px; text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="acc in accounts" :key="acc.id" :class="{ 'row-system': acc.is_system }">
              <td class="font-mono font-bold">{{ acc.code }}</td>
              <td>
                <div class="account-name">
                  <span v-if="acc.parent_id" class="child-indent">↳</span>
                  <span class="name-text">{{ acc.name }}</span>
                </div>
                <div v-if="acc.description" class="account-desc">{{ acc.description }}</div>
              </td>
              <td>
                <span class="badge" :class="'badge-' + acc.type">{{ acc.type.toUpperCase() }}</span>
              </td>
              <td class="text-muted font-sm">{{ acc.sub_type }}</td>
              <td class="font-sm font-capitalize">{{ acc.normal_balance }}</td>
              <td>
                <span v-if="acc.is_system" class="badge-system">Core</span>
                <span v-else class="text-muted font-sm">Custom</span>
              </td>
              <td class="text-right font-mono font-bold" :class="{ 'text-negative': acc.current_balance_minor < 0 }">
                Rs. {{ formatMoney(acc.current_balance_minor) }}
              </td>
              <td class="text-center">
                <Link :href="`/accounts/${acc.id}`" class="btn-action">
                  Ledger
                </Link>
              </td>
            </tr>
            <tr v-if="accounts.length === 0">
              <td colspan="8" class="text-center py-6 text-muted">
                No accounts match the selected filters.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- New Account Modal -->
      <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
        <div class="modal-dialog">
          <div class="modal-header">
            <h3>Create Custom Account</h3>
            <button @click="showModal = false" class="btn-close">&times;</button>
          </div>
          <form @submit.prevent="submitAccount" class="modal-body">
            <div class="form-row">
              <div class="form-field">
                <label>Account Code *</label>
                <input v-model="form.code" type="text" placeholder="e.g. 1014 or 6080" required class="form-input font-mono" />
              </div>
              <div class="form-field">
                <label>Account Name *</label>
                <input v-model="form.name" type="text" placeholder="e.g. HBL Corporate Account" required class="form-input" />
              </div>
            </div>

            <div class="form-row">
              <div class="form-field">
                <label>Classification Type *</label>
                <select v-model="form.type" @change="onTypeChange" required class="form-select">
                  <option value="asset">Asset (1000s)</option>
                  <option value="liability">Liability (2000s)</option>
                  <option value="equity">Equity (3000s)</option>
                  <option value="revenue">Revenue (4000s)</option>
                  <option value="expense">Expense (5000-6000s)</option>
                </select>
              </div>
              <div class="form-field">
                <label>Normal Balance *</label>
                <select v-model="form.normal_balance" required class="form-select">
                  <option value="debit">Debit</option>
                  <option value="credit">Credit</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-field">
                <label>Sub-Type *</label>
                <input v-model="form.sub_type" type="text" placeholder="e.g. bank, operating_expense" required class="form-input" />
              </div>
              <div class="form-field">
                <label>Opening Balance (Rs.)</label>
                <input v-model.number="form.opening_balance_rs" type="number" step="0.01" placeholder="0.00" class="form-input" />
              </div>
            </div>

            <div class="form-field">
              <label>Description / Notes</label>
              <textarea v-model="form.description" rows="2" placeholder="Account purpose..." class="form-input"></textarea>
            </div>

            <div class="modal-footer">
              <button type="button" @click="showModal = false" class="btn btn-secondary">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="form.processing">Create Account</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  accounts: Array<any>;
  filters: {
    type?: string;
    search?: string;
  };
  metrics: {
    total_accounts: number;
    assets_count: number;
    liabilities_count: number;
    equity_count: number;
    revenue_count: number;
    expense_count: number;
  };
}>();

const showModal = ref(false);
const isSyncing = ref(false);
const filterType = ref(props.filters.type || '');
const searchQuery = ref(props.filters.search || '');

const form = useForm({
  code: '',
  name: '',
  type: 'asset',
  sub_type: 'bank',
  normal_balance: 'debit',
  opening_balance_rs: 0,
  opening_balance_minor: 0,
  description: '',
});

function onTypeChange() {
  if (['asset', 'expense'].includes(form.type)) {
    form.normal_balance = 'debit';
  } else {
    form.normal_balance = 'credit';
  }
}

function applyFilters() {
  router.get('/accounts', {
    type: filterType.value || undefined,
    search: searchQuery.value || undefined,
  }, { preserveState: true, replace: true });
}

function syncHistorical() {
  if (confirm('Synchronize all past completed sales, returns, and purchase orders into the General Ledger?')) {
    isSyncing.value = true;
    router.post('/accounts/sync', {}, {
      onFinish: () => {
        isSyncing.value = false;
      },
    });
  }
}

function submitAccount() {
  form.opening_balance_minor = Math.round(Number(form.opening_balance_rs || 0) * 100);
  form.post('/accounts', {
    onSuccess: () => {
      showModal.value = false;
      form.reset();
    },
  });
}

function formatMoney(minor: number): string {
  const rs = (minor || 0) / 100;
  return rs.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<style scoped>
.coa-page {
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

.header-actions {
  display: flex;
  gap: 0.75rem;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.metric-card {
  background: var(--bg-surface, #1e293b);
  border: 1px solid var(--border-color, #334155);
  border-radius: 0.5rem;
  padding: 1rem;
}

.border-asset { border-left: 4px solid #38bdf8; }
.border-liability { border-left: 4px solid #f87171; }
.border-equity { border-left: 4px solid #c084fc; }
.border-revenue { border-left: 4px solid #4ade80; }
.border-expense { border-left: 4px solid #fbbf24; }

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

.text-asset { color: #38bdf8; }
.text-liability { color: #f87171; }
.text-equity { color: #c084fc; }
.text-revenue { color: #4ade80; }
.text-expense { color: #fbbf24; }

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
}

.data-table th {
  background: rgba(15, 23, 42, 0.6);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-muted, #94a3b8);
}

.account-name {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.child-indent {
  color: var(--text-muted, #64748b);
  font-weight: 700;
}

.name-text {
  font-weight: 600;
  color: var(--text-main, #f8fafc);
}

.account-desc {
  font-size: 0.75rem;
  color: var(--text-muted, #94a3b8);
  margin-top: 0.15rem;
}

.badge {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.7rem;
  font-weight: 700;
}

.badge-asset { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }
.badge-liability { background: rgba(248, 113, 113, 0.15); color: #f87171; }
.badge-equity { background: rgba(192, 132, 252, 0.15); color: #c084fc; }
.badge-revenue { background: rgba(74, 222, 128, 0.15); color: #4ade80; }
.badge-expense { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }

.badge-system {
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  background: rgba(148, 163, 184, 0.15);
  color: #94a3b8;
  padding: 0.15rem 0.4rem;
  border-radius: 0.2rem;
}

.btn-action {
  font-size: 0.75rem;
  font-weight: 600;
  color: #38bdf8;
  padding: 0.25rem 0.5rem;
  border: 1px solid rgba(56, 189, 248, 0.3);
  border-radius: 0.25rem;
  text-decoration: none;
  transition: all 0.2s;
}

.btn-action:hover {
  background: rgba(56, 189, 248, 0.15);
}

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
  transition: all 0.2s;
}

.btn-primary {
  background: #0284c7;
  color: white;
}
.btn-primary:hover {
  background: #0369a1;
}

.btn-secondary {
  background: #334155;
  color: #f8fafc;
}
.btn-secondary:hover {
  background: #475569;
}

.form-input, .form-select {
  padding: 0.5rem 0.75rem;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 0.375rem;
  color: #f8fafc;
  font-size: 0.875rem;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

.modal-dialog {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  width: 100%;
  max-width: 580px;
  overflow: hidden;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
}

.modal-header {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #334155;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
  color: #f8fafc;
}

.btn-close {
  background: transparent;
  border: none;
  color: #94a3b8;
  font-size: 1.5rem;
  cursor: pointer;
}

.modal-body {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-field label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #cbd5e1;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 0.5rem;
  padding-top: 1rem;
  border-top: 1px solid #334155;
}

.font-mono { font-family: monospace; }
.font-bold { font-weight: 700; }
.font-sm { font-size: 0.8125rem; }
.font-capitalize { text-transform: capitalize; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.text-muted { color: #94a3b8; }
.text-negative { color: #f87171; }
</style>
