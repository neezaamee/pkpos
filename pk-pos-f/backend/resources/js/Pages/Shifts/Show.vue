<template>
  <AdminLayout :title="`Shift Details: ${report_summary.z_report_number}`">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <Link href="/shifts" class="btn btn-outline" style="padding: 0.4rem 0.75rem; display: flex; align-items: center; gap: 0.35rem;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
          <span>Back to Shifts</span>
        </Link>
        <div>
          <h1 style="font-size: 1.35rem; font-weight: 700; color: var(--text-primary); margin: 0;">
            {{ report_summary.report_title }}
          </h1>
          <div style="font-size: 0.8rem; color: var(--text-muted); font-family: var(--font-mono);">
            {{ report_summary.z_report_number }} &bull; {{ shift.location?.name || 'Main Branch' }}
          </div>
        </div>
      </div>

      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <button class="btn btn-primary" @click="printZReport" style="display: flex; align-items: center; gap: 0.5rem;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 6 2 18 2 18 9"></polyline>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <rect x="6" y="14" width="12" height="8"></rect>
          </svg>
          <span>Print Fiscal {{ report_summary.report_type }}-Report</span>
        </button>
      </div>
    </div>

    <!-- Dual Layout: Thermal Receipt Preview on Left, Detailed Ledger on Right -->
    <div style="display: grid; grid-template-columns: 340px 1fr; gap: 1.5rem; align-items: start;">
      <!-- Thermal Paper Z-Report Card -->
      <div id="thermal-report-preview" class="card" style="background: #ffffff; color: #000000; font-family: var(--font-mono); font-size: 0.8rem; padding: 1.5rem 1.25rem; border: 1px dashed #cbd5e1; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-radius: 8px;">
        <div style="text-align: center; border-bottom: 1px dashed #000; padding-bottom: 0.75rem; margin-bottom: 0.75rem;">
          <div style="font-weight: 900; font-size: 1rem; letter-spacing: 0.05em;">{{ $page.props.auth?.user?.tenant?.name || 'PK-POS STORE' }}</div>
          <div style="font-size: 0.75rem; margin-top: 0.15rem;">{{ shift.location?.name }}</div>
          <div style="font-size: 0.7rem; color: #475569; margin-top: 0.25rem;">{{ report_summary.report_title }}</div>
          <div style="font-weight: 700; font-size: 0.85rem; margin-top: 0.25rem;">{{ report_summary.z_report_number }}</div>
        </div>

        <div style="font-size: 0.725rem; margin-bottom: 0.75rem; border-bottom: 1px dashed #000; padding-bottom: 0.5rem; line-height: 1.4;">
          <div style="display: flex; justify-content: space-between;">
            <span>Opened:</span>
            <span>{{ formatDate(report_summary.opened_at) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>Closed:</span>
            <span>{{ report_summary.closed_at ? formatDate(report_summary.closed_at) : 'IN PROGRESS' }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>Cashier:</span>
            <span>{{ report_summary.cashier_name }}</span>
          </div>
          <div v-if="report_summary.closed_by_name" style="display: flex; justify-content: space-between;">
            <span>Supervisor:</span>
            <span>{{ report_summary.closed_by_name }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>Terminal:</span>
            <span>{{ report_summary.terminal_code }}</span>
          </div>
        </div>

        <!-- Sales & Tax Breakdown -->
        <div style="margin-bottom: 0.75rem; border-bottom: 1px dashed #000; padding-bottom: 0.5rem; line-height: 1.5;">
          <div style="font-weight: 700; text-transform: uppercase; margin-bottom: 0.25rem;">Sales Performance</div>
          <div style="display: flex; justify-content: space-between;">
            <span>Total Invoices:</span>
            <span style="font-weight: 700;">{{ report_summary.sales_count }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>Gross Revenue:</span>
            <span style="font-weight: 700;">{{ formatMoney(report_summary.gross_sales_minor) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>Net Revenue:</span>
            <span>{{ formatMoney(report_summary.net_sales_minor) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>Sales Tax (FBR):</span>
            <span>{{ formatMoney(report_summary.tax_collected_minor) }}</span>
          </div>
        </div>

        <!-- Payment Methods -->
        <div style="margin-bottom: 0.75rem; border-bottom: 1px dashed #000; padding-bottom: 0.5rem; line-height: 1.5;">
          <div style="font-weight: 700; text-transform: uppercase; margin-bottom: 0.25rem;">Payment Tendered</div>
          <div style="display: flex; justify-content: space-between;">
            <span>Cash Sales:</span>
            <span>{{ formatMoney(report_summary.payment_breakdown.cash_minor) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>Card Payments:</span>
            <span>{{ formatMoney(report_summary.payment_breakdown.card_minor) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>Raast QR:</span>
            <span>{{ formatMoney(report_summary.payment_breakdown.raast_minor) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>Digital Wallet:</span>
            <span>{{ formatMoney(report_summary.payment_breakdown.wallet_minor) }}</span>
          </div>
        </div>

        <!-- Cash Drawer Reconciliation -->
        <div style="margin-bottom: 0.75rem; border-bottom: 1px dashed #000; padding-bottom: 0.5rem; line-height: 1.5;">
          <div style="font-weight: 700; text-transform: uppercase; margin-bottom: 0.25rem;">Drawer Reconciliation</div>
          <div style="display: flex; justify-content: space-between;">
            <span>Opening Float:</span>
            <span>{{ formatMoney(report_summary.cash_drawer_reconciliation.opening_float) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>(+) Cash Sales:</span>
            <span>{{ formatMoney(report_summary.cash_drawer_reconciliation.cash_sales) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>(+) Cash In:</span>
            <span>{{ formatMoney(report_summary.cash_drawer_reconciliation.cash_in) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>(-) Safe Drops:</span>
            <span>{{ formatMoney(report_summary.cash_drawer_reconciliation.cash_drops) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>(-) Petty Expenses:</span>
            <span>{{ formatMoney(report_summary.cash_drawer_reconciliation.payout_expenses) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between; font-weight: 700; border-top: 1px dotted #94a3b8; padding-top: 0.25rem; margin-top: 0.25rem;">
            <span>Expected in Till:</span>
            <span>{{ formatMoney(report_summary.cash_drawer_reconciliation.expected_cash) }}</span>
          </div>
          <div v-if="report_summary.cash_drawer_reconciliation.counted_cash !== null" style="display: flex; justify-content: space-between; font-weight: 700;">
            <span>Counted Cash:</span>
            <span>{{ formatMoney(report_summary.cash_drawer_reconciliation.counted_cash) }}</span>
          </div>
          <div
            v-if="report_summary.cash_drawer_reconciliation.variance !== null"
            style="display: flex; justify-content: space-between; font-weight: 900; margin-top: 0.25rem; padding: 0.2rem 0;"
          >
            <span>Variance:</span>
            <span>
              {{ report_summary.cash_drawer_reconciliation.variance === 0 ? 'Rs. 0 (Balanced)' : (report_summary.cash_drawer_reconciliation.variance > 0 ? `+${formatMoney(report_summary.cash_drawer_reconciliation.variance)} (Over)` : `${formatMoney(report_summary.cash_drawer_reconciliation.variance)} (Short)`) }}
            </span>
          </div>
        </div>

        <div style="text-align: center; font-size: 0.68rem; color: #64748b; margin-top: 0.75rem;">
          <div>*** END OF FISCAL REPORT ***</div>
          <div style="margin-top: 0.2rem;">Generated by PK-POS Fiscal Core</div>
        </div>
      </div>

      <!-- Cash Drawer Transaction History (Audit Trail) -->
      <div class="card-table">
        <div class="table-header">
          <div>
            <h2>Cash Movement Audit Trail</h2>
            <div style="font-size: 0.825rem; color: var(--text-muted);">
              Chronological log of every float, sale, cash drop, and petty payout during this shift
            </div>
          </div>
        </div>

        <div style="overflow-x: auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Time</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Balance After</th>
                <th>Reason / Description</th>
                <th>Staff</th>
                <th>Authorized By</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tx in shift.transactions" :key="tx.id">
                <td style="font-size: 0.8rem; font-family: var(--font-mono);">
                  {{ formatTime(tx.created_at) }}
                </td>
                <td>
                  <span
                    class="badge"
                    :style="getTransactionBadgeStyle(tx.type)"
                  >
                    {{ formatTransactionType(tx.type) }}
                  </span>
                </td>
                <td style="font-family: var(--font-mono); font-weight: 700;" :style="isCredit(tx.type) ? 'color: #10b981;' : 'color: #ef4444;'">
                  {{ isCredit(tx.type) ? '+' : '-' }}{{ formatMoney(tx.amount_minor) }}
                </td>
                <td style="font-family: var(--font-mono); font-weight: 600;">
                  {{ formatMoney(tx.balance_after_minor) }}
                </td>
                <td style="color: var(--text-primary); font-size: 0.85rem;">
                  {{ tx.reason || 'General transaction' }}
                </td>
                <td>
                  {{ tx.user?.name || 'Cashier' }}
                </td>
                <td>
                  <span v-if="tx.authorized_by" style="color: var(--accent-primary); font-weight: 600; font-size: 0.8rem;">
                    {{ tx.authorized_by.name }}
                  </span>
                  <span v-else style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                </td>
              </tr>

              <tr v-if="!shift.transactions || shift.transactions.length === 0">
                <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                  No drawer transactions recorded for this session.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  shift: {
    type: Object,
    required: true,
  },
  report_summary: {
    type: Object,
    required: true,
  },
});

function formatMoney(minor) {
  if (minor === undefined || minor === null) return 'Rs. 0';
  const val = Math.abs(minor) / 100;
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
  return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
}

function isCredit(type) {
  return ['opening_float', 'cash_in', 'sale'].includes(type);
}

function formatTransactionType(type) {
  switch (type) {
    case 'opening_float': return 'OPENING FLOAT';
    case 'cash_in': return 'CASH IN';
    case 'cash_drop': return 'SAFE DROP';
    case 'payout_expense': return 'EXPENSE PAYOUT';
    case 'sale': return 'CASH SALE';
    case 'refund': return 'CASH REFUND';
    default: return type.toUpperCase();
  }
}

function getTransactionBadgeStyle(type) {
  switch (type) {
    case 'opening_float':
      return 'background: rgba(99, 102, 241, 0.15); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.3);';
    case 'sale':
      return 'background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3);';
    case 'cash_in':
      return 'background: rgba(6, 182, 212, 0.15); color: #38bdf8; border: 1px solid rgba(6, 182, 212, 0.3);';
    case 'cash_drop':
      return 'background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3);';
    case 'payout_expense':
      return 'background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3);';
    default:
      return 'background: rgba(100, 116, 139, 0.15); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.3);';
  }
}

function printZReport() {
  window.print();
}
</script>

<style scoped>
@media print {
  body * {
    visibility: hidden;
  }
  #thermal-report-preview, #thermal-report-preview * {
    visibility: visible;
  }
  #thermal-report-preview {
    position: absolute;
    left: 0;
    top: 0;
    width: 80mm !important;
    box-shadow: none !important;
    border: none !important;
    padding: 0 !important;
  }
}
</style>
