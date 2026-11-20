<template>
  <AdminLayout title="Balance Sheet">
    <div class="report-page">
      <!-- Header -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Balance Sheet</h1>
          <p class="page-subtitle">Statement of Financial Position as of <strong>{{ report.as_of_date }}</strong></p>
        </div>
        <div class="header-actions">
          <button @click="printReport" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="6 9 6 2 18 2 18 9"></polyline>
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
              <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print Statement
          </button>
        </div>
      </div>

      <!-- Top Row Filters & Equation Banner -->
      <div class="top-row">
        <div class="date-filter">
          <label>As of Date:</label>
          <input type="date" v-model="asOfDate" class="form-input" />
          <button @click="applyFilter" class="btn btn-primary">Update</button>
        </div>

        <div v-if="report.is_balanced" class="equation-banner banner-balanced">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <span><strong>Fundamental Accounting Equation Balanced:</strong> Assets (Rs. {{ formatMoney(report.assets.total_assets_minor) }}) = Liabilities &amp; Equity (Rs. {{ formatMoney(report.total_liabilities_and_equity_minor) }}).</span>
        </div>
        <div v-else class="equation-banner banner-unbalanced">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span>Equation Discrepancy: Difference of Rs. {{ formatMoney(Math.abs(report.difference_minor)) }}.</span>
        </div>
      </div>

      <!-- Two-Column Statement Layout -->
      <div class="balance-grid">
        <!-- 1. ASSETS COLUMN -->
        <div class="card column-card">
          <div class="column-header header-assets">
            <h2>Assets</h2>
            <div class="header-total font-mono">Rs. {{ formatMoney(report.assets.total_assets_minor) }}</div>
          </div>

          <!-- Current Assets -->
          <div class="bs-section">
            <div class="section-title">Current Assets</div>
            <table class="bs-table">
              <tbody>
                <tr v-for="item in report.assets.current_assets" :key="item.account_id">
                  <td>
                    <Link :href="`/accounts/${item.account_id}`" class="link-acc">
                      {{ item.code }} — {{ item.name }}
                    </Link>
                  </td>
                  <td class="text-right font-mono font-bold">Rs. {{ formatMoney(item.balance_minor) }}</td>
                </tr>
                <tr v-if="report.assets.current_assets.length === 0">
                  <td colspan="2" class="text-muted">No current assets</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Non-Current Assets -->
          <div v-if="report.assets.non_current_assets.length > 0" class="bs-section">
            <div class="section-title">Non-Current &amp; Fixed Assets</div>
            <table class="bs-table">
              <tbody>
                <tr v-for="item in report.assets.non_current_assets" :key="item.account_id">
                  <td>
                    <Link :href="`/accounts/${item.account_id}`" class="link-acc">
                      {{ item.code }} — {{ item.name }}
                    </Link>
                  </td>
                  <td class="text-right font-mono font-bold">Rs. {{ formatMoney(item.balance_minor) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Assets Total Footer -->
          <div class="column-footer total-assets-footer">
            <span class="font-bold">TOTAL ASSETS:</span>
            <span class="font-mono font-bold text-assets text-lg">Rs. {{ formatMoney(report.assets.total_assets_minor) }}</span>
          </div>
        </div>

        <!-- 2. LIABILITIES & EQUITY COLUMN -->
        <div class="card column-card">
          <div class="column-header header-liabilities">
            <h2>Liabilities &amp; Equity</h2>
            <div class="header-total font-mono">Rs. {{ formatMoney(report.total_liabilities_and_equity_minor) }}</div>
          </div>

          <!-- Liabilities -->
          <div class="bs-section">
            <div class="section-title">Liabilities</div>
            <table class="bs-table">
              <tbody>
                <tr v-for="item in report.liabilities.rows" :key="item.account_id">
                  <td>
                    <Link :href="`/accounts/${item.account_id}`" class="link-acc">
                      {{ item.code }} — {{ item.name }}
                    </Link>
                  </td>
                  <td class="text-right font-mono font-bold">Rs. {{ formatMoney(item.balance_minor) }}</td>
                </tr>
                <tr v-if="report.liabilities.rows.length === 0">
                  <td colspan="2" class="text-muted">No current liabilities</td>
                </tr>
                <tr class="subtotal-row">
                  <td class="font-bold">Total Liabilities</td>
                  <td class="text-right font-mono font-bold text-liab">Rs. {{ formatMoney(report.liabilities.total_liabilities_minor) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Equity -->
          <div class="bs-section">
            <div class="section-title">Equity</div>
            <table class="bs-table">
              <tbody>
                <tr v-for="item in report.equity.rows" :key="item.account_id">
                  <td>
                    <Link :href="`/accounts/${item.account_id}`" class="link-acc">
                      {{ item.code }} — {{ item.name }}
                    </Link>
                  </td>
                  <td class="text-right font-mono font-bold">Rs. {{ formatMoney(item.balance_minor) }}</td>
                </tr>
                <!-- Retained Current Period Net Earnings -->
                <tr class="row-earnings">
                  <td>
                    <div class="font-bold">Current Period Net Earnings (from P&amp;L)</div>
                    <div class="font-sm text-muted">Cumulative commercial net margin retained</div>
                  </td>
                  <td class="text-right font-mono font-bold" :class="report.equity.current_earnings_minor >= 0 ? 'text-equity' : 'text-danger'">
                    Rs. {{ formatMoney(report.equity.current_earnings_minor) }}
                  </td>
                </tr>
                <tr class="subtotal-row">
                  <td class="font-bold">Total Equity</td>
                  <td class="text-right font-mono font-bold text-equity">Rs. {{ formatMoney(report.equity.total_equity_minor) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Liabilities & Equity Total Footer -->
          <div class="column-footer total-liab-footer">
            <span class="font-bold">TOTAL LIABILITIES &amp; EQUITY:</span>
            <span class="font-mono font-bold text-equity text-lg">Rs. {{ formatMoney(report.total_liabilities_and_equity_minor) }}</span>
          </div>
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
  report: {
    as_of_date: string;
    assets: {
      current_assets: Array<any>;
      non_current_assets: Array<any>;
      total_assets_minor: number;
    };
    liabilities: {
      rows: Array<any>;
      total_liabilities_minor: number;
    };
    equity: {
      rows: Array<any>;
      current_earnings_minor: number;
      total_equity_minor: number;
    };
    total_liabilities_and_equity_minor: number;
    difference_minor: number;
    is_balanced: boolean;
  };
  filters: {
    as_of_date?: string;
  };
}>();

const asOfDate = ref(props.filters.as_of_date || props.report.as_of_date);

function applyFilter() {
  router.get('/reports/balance-sheet', {
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
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.date-filter {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #cbd5e1;
}

.equation-banner {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.65rem 1.25rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.banner-balanced {
  background: rgba(74, 222, 128, 0.12);
  border: 1px solid rgba(74, 222, 128, 0.3);
  color: #4ade80;
}

.banner-unbalanced {
  background: rgba(248, 113, 113, 0.12);
  border: 1px solid rgba(248, 113, 113, 0.3);
  color: #f87171;
}

.balance-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

@media (max-width: 900px) {
  .balance-grid {
    grid-template-columns: 1fr;
  }
}

.card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  display: flex;
  flex-direction: column;
}

.column-header {
  padding: 1rem 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #334155;
}

.column-header h2 {
  font-size: 1.25rem;
  font-weight: 800;
  margin: 0;
  color: #f8fafc;
}

.header-total {
  font-size: 1.25rem;
  font-weight: 800;
}

.header-assets {
  background: rgba(56, 189, 248, 0.08);
  border-top: 3px solid #38bdf8;
}

.header-liabilities {
  background: rgba(192, 132, 252, 0.08);
  border-top: 3px solid #c084fc;
}

.bs-section {
  padding: 1.25rem;
  flex: 1;
}

.section-title {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
  margin-bottom: 0.5rem;
  border-bottom: 1px solid #334155;
  padding-bottom: 0.25rem;
}

.bs-table {
  width: 100%;
  border-collapse: collapse;
}

.bs-table td {
  padding: 0.55rem 0.5rem;
  font-size: 0.875rem;
  border-bottom: 1px solid rgba(51, 65, 85, 0.3);
}

.subtotal-row {
  background: rgba(15, 23, 42, 0.4);
}

.row-earnings {
  background: rgba(74, 222, 128, 0.05);
}

.column-footer {
  padding: 1rem 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 2px solid #334155;
  background: rgba(15, 23, 42, 0.7);
}

.text-assets { color: #38bdf8; }
.text-liab { color: #f87171; }
.text-equity { color: #c084fc; }
.text-danger { color: #f87171; }
.text-lg { font-size: 1.15rem; }

.link-acc {
  color: #f8fafc;
  text-decoration: none;
}
.link-acc:hover {
  color: #38bdf8;
  text-decoration: underline;
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
.text-muted { color: #94a3b8; }
</style>
