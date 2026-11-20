<template>
  <AdminLayout title="Profit & Loss Statement">
    <div class="report-page">
      <!-- Header -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Profit &amp; Loss Statement</h1>
          <p class="page-subtitle">Income Statement from <strong>{{ report.start_date }}</strong> to <strong>{{ report.end_date }}</strong></p>
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

      <!-- Filters & Date Presets -->
      <div class="filters-bar">
        <div class="date-inputs">
          <div class="filter-group">
            <label>From:</label>
            <input type="date" v-model="startDate" class="form-input" />
          </div>
          <div class="filter-group">
            <label>To:</label>
            <input type="date" v-model="endDate" class="form-input" />
          </div>
          <button @click="applyFilter" class="btn btn-primary">Update</button>
        </div>

        <div class="quick-presets">
          <button @click="setPreset('this_month')" class="btn-preset">This Month</button>
          <button @click="setPreset('last_month')" class="btn-preset">Last Month</button>
          <button @click="setPreset('ytd')" class="btn-preset">Year to Date</button>
        </div>
      </div>

      <!-- Key KPI Cards -->
      <div class="metrics-grid">
        <div class="metric-card border-rev">
          <div class="metric-label">Net Sales Revenue</div>
          <div class="metric-value font-mono text-rev">Rs. {{ formatMoney(report.revenue.net_sales_revenue_minor) }}</div>
        </div>
        <div class="metric-card border-cogs">
          <div class="metric-label">Total Cost of Goods (COGS)</div>
          <div class="metric-value font-mono text-cogs">Rs. {{ formatMoney(report.cogs.total_cogs_minor) }}</div>
        </div>
        <div class="metric-card border-gp">
          <div class="metric-label">Gross Profit ({{ report.gross_margin_percentage }}%)</div>
          <div class="metric-value font-mono text-gp">Rs. {{ formatMoney(report.gross_profit_minor) }}</div>
        </div>
        <div class="metric-card border-np" :class="{ 'card-loss': report.net_profit_minor < 0 }">
          <div class="metric-label">Net Operating Margin ({{ report.net_margin_percentage }}%)</div>
          <div class="metric-value font-mono" :class="report.net_profit_minor >= 0 ? 'text-np' : 'text-danger'">
            Rs. {{ formatMoney(report.net_profit_minor) }}
          </div>
        </div>
      </div>

      <!-- P&L Statement Card -->
      <div class="card pnl-card">
        <!-- 1. Operating Revenue Section -->
        <div class="pnl-section">
          <div class="section-title">1. Operating Revenue</div>
          <table class="pnl-table">
            <tbody>
              <tr>
                <td class="pl-4">Gross POS &amp; Credit Sales</td>
                <td class="text-right font-mono font-bold">Rs. {{ formatMoney(report.revenue.gross_sales_minor) }}</td>
              </tr>
              <tr v-if="report.revenue.sales_returns_minor > 0">
                <td class="pl-8 text-muted">Less: Sales Returns &amp; Credit Notes (Contra)</td>
                <td class="text-right font-mono text-danger">- Rs. {{ formatMoney(report.revenue.sales_returns_minor) }}</td>
              </tr>
              <tr v-if="report.revenue.sales_discounts_minor > 0">
                <td class="pl-8 text-muted">Less: Cashier &amp; Promotional Discounts (Contra)</td>
                <td class="text-right font-mono text-danger">- Rs. {{ formatMoney(report.revenue.sales_discounts_minor) }}</td>
              </tr>
              <tr v-if="report.revenue.other_income_minor > 0">
                <td class="pl-8 text-muted">Other Incidental Income / Scrap</td>
                <td class="text-right font-mono text-gp">+ Rs. {{ formatMoney(report.revenue.other_income_minor) }}</td>
              </tr>
              <tr class="subtotal-row">
                <td class="font-bold">Total Net Revenue</td>
                <td class="text-right font-mono font-bold text-rev">Rs. {{ formatMoney(report.revenue.net_sales_revenue_minor) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 2. Cost of Goods Sold Section -->
        <div class="pnl-section">
          <div class="section-title">2. Cost of Goods Sold (COGS)</div>
          <table class="pnl-table">
            <tbody>
              <tr v-for="item in report.cogs.rows" :key="item.account_id">
                <td class="pl-4">{{ item.code }} — {{ item.name }}</td>
                <td class="text-right font-mono">Rs. {{ formatMoney(item.amount_minor) }}</td>
              </tr>
              <tr v-if="report.cogs.rows.length === 0">
                <td class="pl-4 text-muted">Standard inventory consumption cost</td>
                <td class="text-right font-mono">Rs. 0.00</td>
              </tr>
              <tr class="subtotal-row">
                <td class="font-bold">Total Cost of Goods Sold</td>
                <td class="text-right font-mono font-bold text-cogs">Rs. {{ formatMoney(report.cogs.total_cogs_minor) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Gross Profit Banner -->
        <div class="highlight-banner gp-banner">
          <div>
            <div class="hl-label">Gross Commercial Profit</div>
            <div class="hl-sub">Net Revenue minus Cost of Goods</div>
          </div>
          <div class="hl-val text-gp">
            Rs. {{ formatMoney(report.gross_profit_minor) }}
            <span class="hl-pct">({{ report.gross_margin_percentage }}%)</span>
          </div>
        </div>

        <!-- 3. Operating Expenses Section -->
        <div class="pnl-section">
          <div class="section-title">3. Operating &amp; Administrative Expenses</div>
          <table class="pnl-table">
            <tbody>
              <tr v-for="item in report.operating_expenses.rows" :key="item.account_id">
                <td class="pl-4">{{ item.code }} — {{ item.name }}</td>
                <td class="text-right font-mono">Rs. {{ formatMoney(item.amount_minor) }}</td>
              </tr>
              <tr v-if="report.operating_expenses.rows.length === 0">
                <td class="pl-4 text-muted">No operating expenses posted in this period</td>
                <td class="text-right font-mono">Rs. 0.00</td>
              </tr>
              <tr class="subtotal-row">
                <td class="font-bold">Total Operating Expenses</td>
                <td class="text-right font-mono font-bold text-danger">Rs. {{ formatMoney(report.operating_expenses.total_expenses_minor) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Net Profit Final Banner -->
        <div class="highlight-banner np-banner" :class="{ 'np-loss': report.net_profit_minor < 0 }">
          <div>
            <div class="hl-label">Net Operating {{ report.net_profit_minor >= 0 ? 'Profit' : 'Loss' }}</div>
            <div class="hl-sub">Final bottom line after all direct costs and operating expenses</div>
          </div>
          <div class="hl-val" :class="report.net_profit_minor >= 0 ? 'text-np' : 'text-danger'">
            Rs. {{ formatMoney(report.net_profit_minor) }}
            <span class="hl-pct">({{ report.net_margin_percentage }}%)</span>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  report: {
    start_date: string;
    end_date: string;
    revenue: {
      gross_sales_minor: number;
      sales_returns_minor: number;
      sales_discounts_minor: number;
      other_income_minor: number;
      net_sales_revenue_minor: number;
      rows: Array<any>;
    };
    cogs: {
      total_cogs_minor: number;
      rows: Array<any>;
    };
    gross_profit_minor: number;
    gross_margin_percentage: number;
    operating_expenses: {
      total_expenses_minor: number;
      rows: Array<any>;
    };
    net_profit_minor: number;
    net_margin_percentage: number;
  };
  filters: {
    start_date?: string;
    end_date?: string;
  };
}>();

const startDate = ref(props.filters.start_date || props.report.start_date);
const endDate = ref(props.filters.end_date || props.report.end_date);

function applyFilter() {
  router.get('/reports/profit-loss', {
    start_date: startDate.value || undefined,
    end_date: endDate.value || undefined,
  }, { preserveState: true });
}

function setPreset(preset: string) {
  const now = new Date();
  if (preset === 'this_month') {
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    startDate.value = firstDay.toISOString().split('T')[0];
    endDate.value = now.toISOString().split('T')[0];
  } else if (preset === 'last_month') {
    const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);
    startDate.value = firstDay.toISOString().split('T')[0];
    endDate.value = lastDay.toISOString().split('T')[0];
  } else if (preset === 'ytd') {
    const firstDay = new Date(now.getFullYear(), 0, 1);
    startDate.value = firstDay.toISOString().split('T')[0];
    endDate.value = now.toISOString().split('T')[0];
  }
  applyFilter();
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
  max-width: 1200px;
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

.filters-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.date-inputs {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.quick-presets {
  display: flex;
  gap: 0.5rem;
}

.btn-preset {
  background: #334155;
  border: 1px solid #475569;
  color: #f8fafc;
  padding: 0.35rem 0.75rem;
  border-radius: 0.25rem;
  font-size: 0.8125rem;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-preset:hover {
  background: #475569;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.metric-card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  padding: 1rem;
}

.border-rev { border-left: 4px solid #38bdf8; }
.border-cogs { border-left: 4px solid #fbbf24; }
.border-gp { border-left: 4px solid #2dd4bf; }
.border-np { border-left: 4px solid #4ade80; }
.card-loss { border-left: 4px solid #f87171; }

.metric-label {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  color: #94a3b8;
}

.metric-value {
  font-size: 1.4rem;
  font-weight: 700;
  margin-top: 0.25rem;
}

.text-rev { color: #38bdf8; }
.text-cogs { color: #fbbf24; }
.text-gp { color: #2dd4bf; }
.text-np { color: #4ade80; }
.text-danger { color: #f87171; }

.card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  padding: 1.5rem;
}

.pnl-section {
  margin-bottom: 1.5rem;
}

.section-title {
  font-size: 0.875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
  margin-bottom: 0.5rem;
  border-bottom: 1px solid #334155;
  padding-bottom: 0.35rem;
}

.pnl-table {
  width: 100%;
  border-collapse: collapse;
}

.pnl-table td {
  padding: 0.55rem 0.5rem;
  font-size: 0.875rem;
  border-bottom: 1px solid rgba(51, 65, 85, 0.4);
}

.subtotal-row {
  background: rgba(15, 23, 42, 0.5);
  border-top: 1px solid #334155;
}

.highlight-banner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.25rem;
  border-radius: 0.5rem;
  margin: 1.5rem 0;
}

.gp-banner {
  background: rgba(45, 212, 191, 0.08);
  border: 1px solid rgba(45, 212, 191, 0.3);
}

.np-banner {
  background: rgba(74, 222, 128, 0.1);
  border: 1px solid rgba(74, 222, 128, 0.3);
}
.np-loss {
  background: rgba(248, 113, 113, 0.1);
  border: 1px solid rgba(248, 113, 113, 0.3);
}

.hl-label {
  font-size: 1.1rem;
  font-weight: 800;
  color: #f8fafc;
}
.hl-sub {
  font-size: 0.75rem;
  color: #94a3b8;
  margin-top: 0.15rem;
}

.hl-val {
  font-size: 1.6rem;
  font-weight: 800;
  font-family: monospace;
}
.hl-pct {
  font-size: 1rem;
  font-weight: 600;
  opacity: 0.85;
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

.pl-4 { padding-left: 1rem !important; }
.pl-8 { padding-left: 2rem !important; }
.font-mono { font-family: monospace; }
.font-bold { font-weight: 700; }
.text-right { text-align: right; }
.text-muted { color: #94a3b8; }
</style>
