<template>
  <AdminLayout title="Sales & Invoices">
    <div class="card-table">
      <div class="table-header">
        <h2>Invoices & Transactions</h2>
        <div style="font-size: 0.825rem; color: var(--text-muted);">
          Click any row to inspect line items & tax breakdown
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Sale Number</th>
              <th>Date</th>
              <th>Total Amount</th>
              <th>Payment</th>
              <th>FBR Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="sale in sales.data"
              :key="sale.id"
              class="clickable"
              @click="openDetail(sale)"
            >
              <td style="font-weight: 600; font-family: var(--font-mono); color: var(--text-primary);">
                {{ sale.sale_number }}
              </td>
              <td>{{ formatDate(sale.created_at) }}</td>
              <td style="font-weight: 700; color: var(--text-primary);">
                {{ formatMoney(sale.total_minor) }}
              </td>
              <td style="text-transform: capitalize;">
                {{ sale.payment_method }}
              </td>
              <td>
                <span :class="['badge', `badge-${sale.fbr_status}`]">
                  <span class="badge-dot"></span>
                  {{ sale.fbr_status }}
                </span>
              </td>
              <td>
                <button
                  type="button"
                  class="btn btn-secondary"
                  style="padding: 0.25rem 0.6rem; font-size: 0.75rem;"
                  @click.stop="openDetail(sale)"
                >
                  View Details
                </button>
              </td>
            </tr>
            <tr v-if="sales.data.length === 0">
              <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                No sales records found for this tenant.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Links -->
      <div v-if="sales.links && sales.links.length > 3" class="pagination">
        <Link
          v-for="(link, index) in sales.links"
          :key="index"
          :href="link.url || '#'"
          class="page-btn"
          :class="{ active: link.active, disabled: !link.url }"
          v-html="link.label"
        />
      </div>
    </div>

    <!-- Sale Detail Modal -->
    <div v-if="selectedSale" class="modal-backdrop" @click="closeDetail">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <div>
            <h3>Sale Detail: {{ selectedSale.sale_number }}</h3>
            <div style="font-size: 0.775rem; color: var(--text-muted); font-family: var(--font-mono); margin-top: 0.2rem;">
              UUID: {{ selectedSale.id }}
            </div>
          </div>
          <button type="button" class="modal-close" @click="closeDetail">&times;</button>
        </div>

        <div class="modal-body">
          <!-- Sale Meta Grid -->
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; background: rgba(0,0,0,0.25); padding: 1rem; border-radius: 8px;">
            <div>
              <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">Date & Time</span>
              <span style="font-size: 0.875rem; font-weight: 600;">{{ formatDate(selectedSale.created_at) }}</span>
            </div>
            <div>
              <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">Payment Method</span>
              <span style="font-size: 0.875rem; font-weight: 600; text-transform: capitalize;">{{ selectedSale.payment_method }}</span>
            </div>
            <div>
              <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">FBR USIN</span>
              <span style="font-size: 0.875rem; font-weight: 600; font-family: var(--font-mono);">
                {{ selectedSale.fbr_usin || 'None (Provisional/Pending)' }}
              </span>
            </div>
            <div>
              <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">FBR Status</span>
              <span :class="['badge', `badge-${selectedSale.fbr_status}`]" style="margin-top: 0.2rem;">
                <span class="badge-dot"></span>
                {{ selectedSale.fbr_status }}
              </span>
            </div>
          </div>

          <div v-if="selectedSale.fbr_error" class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <strong>FBR Error:</strong> {{ selectedSale.fbr_error }}
          </div>

          <!-- Line Items Table -->
          <h4 style="font-size: 0.95rem; font-weight: 600; margin-bottom: 0.75rem;">Line Items</h4>
          <table class="data-table" style="background: rgba(0,0,0,0.15); border-radius: 8px; overflow: hidden;">
            <thead>
              <tr>
                <th>Product</th>
                <th>HS Code</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Tax</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in selectedSale.items" :key="item.id">
                <td style="font-weight: 600; color: var(--text-primary);">{{ item.product_name }}</td>
                <td style="font-family: var(--font-mono); font-size: 0.8rem;">{{ item.hs_code }}</td>
                <td>{{ item.quantity }}</td>
                <td>{{ formatMoney(item.unit_price_minor) }}</td>
                <td>{{ formatMoney(item.tax_amount_minor) }}</td>
                <td style="font-weight: 700; color: var(--text-primary);">{{ formatMoney(item.line_total_minor) }}</td>
              </tr>
              <tr v-if="!selectedSale.items || selectedSale.items.length === 0">
                <td colspan="6" style="text-align: center; color: var(--text-muted);">No items recorded.</td>
              </tr>
            </tbody>
          </table>

          <!-- Financial Summary -->
          <div style="margin-top: 1.5rem; display: flex; flex-direction: column; align-items: flex-end; gap: 0.35rem; font-size: 0.9rem;">
            <div style="display: flex; gap: 2rem;">
              <span style="color: var(--text-muted);">Subtotal:</span>
              <span>{{ formatMoney(selectedSale.subtotal_minor) }}</span>
            </div>
            <div style="display: flex; gap: 2rem;">
              <span style="color: var(--text-muted);">Tax:</span>
              <span>{{ formatMoney(selectedSale.tax_minor) }}</span>
            </div>
            <div style="display: flex; gap: 2rem; font-size: 1.15rem; font-weight: 800; color: var(--text-primary); border-top: 1px solid var(--border-color); padding-top: 0.5rem; margin-top: 0.25rem;">
              <span>Total:</span>
              <span style="color: var(--badge-green-text);">{{ formatMoney(selectedSale.total_minor) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
  sales: {
    type: Object,
    required: true,
  },
});

const selectedSale = ref(null);

function openDetail(sale) {
  selectedSale.value = sale;
}

function closeDetail() {
  selectedSale.value = null;
}

function formatMoney(minor) {
  if (minor === undefined || minor === null) return 'PKR 0.00';
  const val = (minor / 100).toLocaleString('en-PK', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return `PKR ${val}`;
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>
