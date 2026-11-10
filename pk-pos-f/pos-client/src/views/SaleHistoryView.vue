<script setup lang="ts">
import { ref, onMounted } from 'vue';
import type { LocalSaleSummary, LocalSaleWithItems } from '../types/pos';
import { formatPKR } from '../types/pos';
import { getLocalSales, getLocalSaleDetails, reprintReceipt, getPendingOutboxCount, clearLocalSales } from '../services/posService';

const sales = ref<LocalSaleSummary[]>([]);
const pendingCount = ref<number>(0);
const isLoading = ref<boolean>(true);
const selectedSale = ref<LocalSaleWithItems | null>(null);
const isReprinting = ref<boolean>(false);
const reprintSuccess = ref<string | null>(null);

async function handleClearHistory() {
  if (confirm('Clear local transaction history for this terminal? This will wipe cached orders from local storage.')) {
    await clearLocalSales();
    await loadHistory();
  }
}

async function loadHistory() {
  isLoading.value = true;
  try {
    sales.value = await getLocalSales(100);
    pendingCount.value = await getPendingOutboxCount();
  } catch (err) {
    console.error('Failed to load sales history', err);
  } finally {
    isLoading.value = false;
  }
}

async function viewSaleDetails(saleId: string) {
  try {
    reprintSuccess.value = null;
    selectedSale.value = await getLocalSaleDetails(saleId);
  } catch (err) {
    console.error('Failed to load sale details', err);
  }
}

function closeModal() {
  selectedSale.value = null;
  reprintSuccess.value = null;
}

async function handleReprint() {
  if (!selectedSale.value || isReprinting.value) return;
  isReprinting.value = true;
  reprintSuccess.value = null;
  try {
    await reprintReceipt(selectedSale.value.sale.id);
    reprintSuccess.value = 'Receipt reprint command dispatched to printer.';
    setTimeout(() => {
      reprintSuccess.value = null;
    }, 4000);
  } catch (err: any) {
    reprintSuccess.value = 'Reprint failed: ' + (err?.message || String(err));
  } finally {
    isReprinting.value = false;
  }
}

function formatDateTime(iso: string): string {
  try {
    const d = new Date(iso);
    return d.toLocaleString('en-PK', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  } catch {
    return iso;
  }
}

onMounted(() => {
  loadHistory();
});
</script>

<template>
  <div class="history-container">
    <div class="history-header">
      <div>
        <h2 class="history-title">Local Sales History</h2>
        <p class="history-subtitle">Offline transactional records stored in local SQLite</p>
      </div>

      <div class="summary-stats">
        <div class="stat-pill">
          <span class="pill-label">Total Local Sales:</span>
          <span class="pill-val">{{ sales.length }}</span>
        </div>
        <div class="stat-pill outbox-pill" :class="{ alert: pendingCount > 0 }">
          <span class="pill-label">Pending Outbox:</span>
          <span class="pill-val">{{ pendingCount }}</span>
        </div>
        <button class="refresh-btn" @click="loadHistory" :disabled="isLoading">
          {{ isLoading ? 'Refreshing...' : 'Refresh' }}
        </button>
        <button
          v-if="sales.length > 0"
          @click="handleClearHistory"
          style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.8rem; cursor: pointer; font-weight: 600;"
        >
          🗑️ Clear Local History
        </button>
      </div>
    </div>

    <div class="table-container">
      <table class="sales-table">
        <thead>
          <tr>
            <th>Date / Time</th>
            <th>Sale Number</th>
            <th>Items</th>
            <th>Payment</th>
            <th>Total (PKR)</th>
            <th>FBR Status</th>
            <th>FBR USIN / Provisional</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="sales.length === 0 && !isLoading">
            <td colspan="8" class="empty-cell">No sales recorded yet. Completed sales will appear here.</td>
          </tr>
          <tr
            v-for="s in sales"
            :key="s.id"
            class="sale-row"
            @click="viewSaleDetails(s.id)"
          >
            <td class="date-cell">{{ formatDateTime(s.created_at) }}</td>
            <td class="sale-num-cell">{{ s.sale_number }}</td>
            <td>{{ s.items_count }} items</td>
            <td class="payment-cell">{{ s.payment_method }}</td>
            <td class="total-cell">{{ formatPKR(s.total_minor) }}</td>
            <td>
              <span
                class="status-badge"
                :class="{
                  submitted: s.fbr_status === 'submitted',
                  offline: s.fbr_status === 'offline',
                  failed: s.fbr_status === 'failed' || s.fbr_status === 'rejected',
                }"
              >
                {{ s.fbr_status }}
              </span>
            </td>
            <td class="usin-cell">{{ s.fbr_usin || '-' }}</td>
            <td>
              <button class="action-btn" @click.stop="viewSaleDetails(s.id)">
                View & Reprint
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile Sales Card List (<= 768px) -->
    <div class="mobile-sales-cards">
      <div v-if="sales.length === 0 && !isLoading" class="empty-mobile-card">
        No sales recorded yet. Completed sales will appear here.
      </div>
      <div
        v-for="s in sales"
        :key="'m_' + s.id"
        class="sale-card-mobile"
        @click="viewSaleDetails(s.id)"
      >
        <div class="m-card-top">
          <span class="sale-num-cell">{{ s.sale_number }}</span>
          <span
            class="status-badge"
            :class="{
              submitted: s.fbr_status === 'submitted',
              offline: s.fbr_status === 'offline',
              failed: s.fbr_status === 'failed' || s.fbr_status === 'rejected',
            }"
          >
            {{ s.fbr_status }}
          </span>
        </div>

        <div class="m-card-meta">
          <span>{{ formatDateTime(s.created_at) }}</span>
          <span>{{ s.items_count }} items &bull; <span class="cap">{{ s.payment_method }}</span></span>
          <span v-if="s.fbr_usin" class="usin-cell">USIN: {{ s.fbr_usin }}</span>
        </div>

        <div class="m-card-bottom">
          <span class="total-cell m-total-val">{{ formatPKR(s.total_minor) }}</span>
          <button class="action-btn" @click.stop="viewSaleDetails(s.id)">
            View & Reprint
          </button>
        </div>
      </div>
    </div>

    <!-- Sale Detail & Reprint Modal -->
    <div v-if="selectedSale" class="modal-overlay" @click.self="closeModal">
      <div class="modal-card">
        <div class="modal-header">
          <div>
            <h3 class="modal-title">Sale Details: {{ selectedSale.sale.sale_number }}</h3>
            <span class="modal-timestamp">{{ formatDateTime(selectedSale.sale.created_at) }}</span>
          </div>
          <button class="close-btn" @click="closeModal">&times;</button>
        </div>

        <div class="detail-status-bar">
          <div class="status-item">
            <span class="detail-label">Payment Method:</span>
            <strong class="cap">{{ selectedSale.sale.payment_method }}</strong>
          </div>
          <div class="status-item">
            <span class="detail-label">FBR Status:</span>
            <span
              class="status-badge"
              :class="{
                submitted: selectedSale.sale.fbr_status === 'submitted',
                offline: selectedSale.sale.fbr_status === 'offline',
                failed: selectedSale.sale.fbr_status === 'failed' || selectedSale.sale.fbr_status === 'rejected',
              }"
            >
              {{ selectedSale.sale.fbr_status }}
            </span>
          </div>
          <div v-if="selectedSale.sale.fbr_usin" class="status-item">
            <span class="detail-label">FBR USIN:</span>
            <code class="code-val">{{ selectedSale.sale.fbr_usin }}</code>
          </div>
        </div>

        <!-- Line Items -->
        <div class="items-table-wrapper">
          <table class="items-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>PCT Code</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Tax</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="it in selectedSale.items" :key="it.id">
                <td class="it-name">{{ it.product_name }}</td>
                <td class="pct-col">{{ it.hs_code }}</td>
                <td>{{ it.quantity }}</td>
                <td>{{ formatPKR(it.unit_price_minor) }}</td>
                <td>{{ formatPKR(it.tax_minor) }} ({{ (it.tax_rate_basis_points / 100).toFixed(0) }}%)</td>
                <td class="it-total">{{ formatPKR(it.total_minor) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Totals summary -->
        <div class="modal-totals">
          <div class="tot-row">
            <span>Subtotal:</span>
            <span>{{ formatPKR(selectedSale.sale.subtotal_minor) }}</span>
          </div>
          <div class="tot-row">
            <span>Total Sales Tax (GST):</span>
            <span>{{ formatPKR(selectedSale.sale.tax_minor) }}</span>
          </div>
          <div class="tot-row grand">
            <span>Total Retail Price:</span>
            <strong class="grand-color">{{ formatPKR(selectedSale.sale.total_minor) }}</strong>
          </div>
        </div>

        <p v-if="reprintSuccess" class="toast-message">{{ reprintSuccess }}</p>

        <div class="modal-actions">
          <button class="reprint-btn" :disabled="isReprinting" @click="handleReprint">
            {{ isReprinting ? 'Dispatching Print...' : 'Reprint Receipt' }}
          </button>
          <button class="dismiss-btn" @click="closeModal">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.history-container {
  padding: 1.5rem;
  flex: 1;
  height: 100%;
  min-height: 0;
  width: 100%;
  overflow-y: auto;
  box-sizing: border-box;
  background: #0f172a;
  color: #f8fafc;
}

.history-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #1e293b;
}

.history-title {
  margin: 0 0 0.25rem;
  font-size: 1.5rem;
  font-weight: 700;
  color: #ffffff;
}

.history-subtitle {
  margin: 0;
  font-size: 0.85rem;
  color: #94a3b8;
}

.summary-stats {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.6rem;
}

.stat-pill {
  background: #1e293b;
  border: 1px solid #334155;
  padding: 0.4rem 0.85rem;
  border-radius: 0.5rem;
  font-size: 0.8rem;
  display: flex;
  gap: 0.4rem;
}

.stat-pill.alert {
  border-color: #f59e0b;
  background: rgba(245, 158, 11, 0.1);
  color: #fbbf24;
}

.pill-label {
  color: #94a3b8;
}

.pill-val {
  font-weight: 700;
  color: #ffffff;
}

.refresh-btn {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 0.45rem 1rem;
  border-radius: 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.refresh-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.table-container {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 0.75rem;
  overflow-x: auto;
}

.sales-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.85rem;
}

.sales-table th {
  background: #182234;
  color: #94a3b8;
  font-weight: 600;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #334155;
}

.sales-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #283548;
}

.sale-row {
  cursor: pointer;
  transition: background 0.15s ease;
}

.sale-row:hover {
  background: #253349;
}

.date-cell {
  color: #cbd5e1;
  white-space: nowrap;
}

.sale-num-cell {
  font-family: monospace;
  font-weight: 600;
  color: #38bdf8;
}

.payment-cell {
  text-transform: capitalize;
  color: #94a3b8;
}

.total-cell {
  font-weight: 700;
  color: #10b981;
}

.usin-cell {
  font-family: monospace;
  font-size: 0.8rem;
  color: #94a3b8;
}

.status-badge {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
}

.status-badge.submitted {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-badge.offline {
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-badge.failed {
  background: rgba(239, 68, 68, 0.15);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.action-btn {
  background: #334155;
  color: #e2e8f0;
  border: none;
  padding: 0.3rem 0.6rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.action-btn:hover {
  background: #3b82f6;
  color: #ffffff;
}

.empty-cell {
  text-align: center;
  color: #64748b;
  padding: 3rem;
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
  padding: 1rem;
}

.modal-card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 1rem;
  padding: 1.5rem;
  width: 100%;
  max-width: 620px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.modal-title {
  margin: 0;
  font-size: 1.15rem;
  color: #ffffff;
}

.modal-timestamp {
  font-size: 0.75rem;
  color: #94a3b8;
}

.close-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  font-size: 1.5rem;
  cursor: pointer;
}

.close-btn:hover {
  color: #ffffff;
}

.detail-status-bar {
  display: flex;
  gap: 1.5rem;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
}

.status-item {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.8rem;
}

.detail-label {
  color: #94a3b8;
}

.code-val {
  font-family: monospace;
  color: #38bdf8;
}

.items-table-wrapper {
  max-height: 220px;
  overflow-y: auto;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  margin-bottom: 1rem;
}

.items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8rem;
}

.items-table th {
  background: #0f172a;
  padding: 0.5rem 0.75rem;
  color: #94a3b8;
  border-bottom: 1px solid #334155;
}

.items-table td {
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid #283548;
}

.pct-col {
  font-family: monospace;
  color: #94a3b8;
}

.it-total {
  font-weight: 700;
  color: #ffffff;
}

.modal-totals {
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
}

.tot-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  color: #94a3b8;
  margin-bottom: 0.25rem;
}

.tot-row.grand {
  margin-top: 0.5rem;
  padding-top: 0.5rem;
  border-top: 1px dashed #334155;
  color: #ffffff;
  font-size: 1rem;
}

.grand-color {
  color: #10b981;
}

.toast-message {
  background: rgba(16, 185, 129, 0.15);
  border: 1px solid #10b981;
  color: #34d399;
  padding: 0.5rem;
  border-radius: 0.375rem;
  font-size: 0.8rem;
  margin-bottom: 1rem;
  text-align: center;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.reprint-btn {
  background: #10b981;
  color: #ffffff;
  border: none;
  padding: 0.55rem 1.25rem;
  border-radius: 0.5rem;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
}

.reprint-btn:hover {
  background: #059669;
}

.dismiss-btn {
  background: #334155;
  color: #e2e8f0;
  border: none;
  padding: 0.55rem 1.25rem;
  border-radius: 0.5rem;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
}

.dismiss-btn:hover {
  background: #475569;
}

.cap {
  text-transform: capitalize;
}

.mobile-sales-cards {
  display: none;
}

@media (max-width: 768px) {
  .history-container {
    padding: 0.9rem;
  }

  .history-header {
    margin-bottom: 1rem;
    padding-bottom: 0.85rem;
  }

  .history-title {
    font-size: 1.25rem;
  }

  .summary-stats {
    width: 100%;
  }

  .stat-pill {
    padding: 0.35rem 0.65rem;
    font-size: 0.75rem;
  }

  .table-container {
    display: none;
  }

  .mobile-sales-cards {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
  }

  .empty-mobile-card {
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 0.75rem;
    padding: 2rem 1rem;
    text-align: center;
    color: #64748b;
    font-size: 0.85rem;
  }

  .sale-card-mobile {
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 0.75rem;
    padding: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    cursor: pointer;
  }

  .m-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
  }

  .m-card-meta {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    font-size: 0.78rem;
    color: #94a3b8;
  }

  .m-card-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.45rem;
    border-top: 1px solid #283548;
  }

  .m-total-val {
    font-size: 1.05rem;
  }

  .modal-card {
    max-height: 90dvh;
    overflow-y: auto;
    padding: 1.15rem;
  }

  .detail-status-bar {
    flex-wrap: wrap;
    gap: 0.65rem 1.25rem;
  }

  .items-table-wrapper {
    overflow-x: auto;
  }

  .modal-actions {
    flex-wrap: wrap;
  }

  .reprint-btn,
  .dismiss-btn {
    flex: 1 1 140px;
  }
}
</style>
