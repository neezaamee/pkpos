<template>
  <AdminLayout :title="`Purchase Order: ${purchaseOrder.po_number}`">
    <!-- Top Action & Navigation Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <Link href="/purchases" class="btn btn-outline" style="padding: 0.4rem 0.65rem;" title="Back to Purchase Orders">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
        </Link>
        <div>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <h1 style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); margin: 0; font-family: var(--font-mono);">
              {{ purchaseOrder.po_number }}
            </h1>
            <span
              class="badge"
              :style="getStatusBadgeStyle(purchaseOrder.status)"
            >
              <span class="badge-dot" :style="{ background: getStatusDotColor(purchaseOrder.status) }"></span>
              {{ formatStatus(purchaseOrder.status) }}
            </span>
          </div>
          <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem; display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
            <span>Issued: <strong>{{ formatDateTime(purchaseOrder.created_at) }}</strong></span>
            <span v-if="purchaseOrder.user">&bull; Issued By: <strong>{{ purchaseOrder.user.name }}</strong></span>
            <span v-if="purchaseOrder.received_at">&bull; Last Received: <strong style="color: #10b981;">{{ formatDateTime(purchaseOrder.received_at) }}</strong></span>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <button
          type="button"
          class="btn btn-outline"
          style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.9rem;"
          @click="printPO"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 6 2 18 2 18 9"></polyline>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <rect x="6" y="14" width="12" height="8"></rect>
          </svg>
          <span>Print PO</span>
        </button>

        <button
          v-if="purchaseOrder.status !== 'received' && purchaseOrder.status !== 'canceled'"
          type="button"
          class="btn btn-primary"
          style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 1.1rem; background: #10b981; border-color: #10b981;"
          @click="openGrnModal"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <span>Receive Goods (GRN)</span>
        </button>
      </div>
    </div>

    <!-- Details Strip Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card" style="border-left: 4px solid #6366f1;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
          Supplier &amp; Distributor
        </div>
        <div style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem;">
          <Link :href="`/suppliers/${purchaseOrder.supplier.id}`" style="color: inherit; text-decoration: underline;">
            {{ purchaseOrder.supplier.name }}
          </Link>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Phone: {{ purchaseOrder.supplier.phone }} &bull; NTN: {{ purchaseOrder.supplier.ntn || 'N/A' }}
        </div>
      </div>

      <div class="card" style="border-left: 4px solid #10b981;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
          Destination Warehouse
        </div>
        <div style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem;">
          {{ purchaseOrder.location?.name || 'Main Warehouse' }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Physical stock receiving destination
        </div>
      </div>

      <div class="card" style="border-left: 4px solid #f59e0b;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
          Expected Delivery
        </div>
        <div style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem;">
          {{ purchaseOrder.expected_delivery_date || 'Immediate' }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Target shipment arrival date
        </div>
      </div>

      <div class="card" style="border-left: 4px solid #ef4444;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
          Total PO Value
        </div>
        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
          {{ formatMoney(purchaseOrder.total_minor) }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Total procurement commitment
        </div>
      </div>
    </div>

    <!-- Line Items Table -->
    <div class="card-table" style="margin-bottom: 1.5rem;">
      <div class="table-header" style="display: flex; align-items: center; justify-content: space-between;">
        <div>
          <h2>Order Items &amp; Physical Stock Receiving Progress</h2>
          <div style="font-size: 0.825rem; color: var(--text-muted);">
            Ordered quantities, packaging conversion units, and verified physical quantities received
          </div>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="table">
          <thead>
            <tr>
              <th>Item / Product</th>
              <th>Packaging / UOM</th>
              <th style="text-align: right;">Unit Cost</th>
              <th style="text-align: center;">Ordered</th>
              <th style="text-align: center;">Received</th>
              <th style="text-align: center;">Pending</th>
              <th style="text-align: right;">Line Total</th>
              <th style="text-align: center;">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in purchaseOrder.items" :key="item.id">
              <td>
                <div style="font-weight: 700; color: var(--text-primary);">
                  {{ item.product?.name || 'Product' }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono);">
                  SKU: {{ item.product?.sku || 'N/A' }} &bull; Barcode: {{ item.product?.barcode || 'N/A' }}
                </div>
              </td>
              <td>
                <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: #6366f1;">
                  {{ item.unit_code }} ({{ item.unit_conversion_factor }}x)
                </span>
                <div v-if="item.unit_conversion_factor > 1" style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem;">
                  Base: {{ item.ordered_quantity * item.unit_conversion_factor }} units
                </div>
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 700;">
                {{ formatMoney(item.cost_price_minor) }}
              </td>
              <td style="text-align: center; font-family: var(--font-mono); font-weight: 700;">
                {{ item.ordered_quantity }} {{ item.unit_code }}
              </td>
              <td style="text-align: center; font-family: var(--font-mono); font-weight: 800; color: #10b981;">
                {{ item.received_quantity }} {{ item.unit_code }}
              </td>
              <td style="text-align: center; font-family: var(--font-mono); font-weight: 700;" :style="{ color: (item.ordered_quantity - item.received_quantity) > 0 ? '#ef4444' : '#10b981' }">
                {{ Math.max(0, item.ordered_quantity - item.received_quantity) }} {{ item.unit_code }}
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 800; color: var(--text-primary);">
                {{ formatMoney(item.line_total_minor) }}
              </td>
              <td style="text-align: center;">
                <span
                  v-if="item.received_quantity >= item.ordered_quantity"
                  class="badge"
                  style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700;"
                >
                  Complete
                </span>
                <span
                  v-else-if="item.received_quantity > 0"
                  class="badge"
                  style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; font-weight: 700;"
                >
                  Partial
                </span>
                <span
                  v-else
                  class="badge"
                  style="background: rgba(100, 116, 139, 0.15); color: #94a3b8;"
                >
                  Awaiting
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Linked Accounts Payable Invoices Section -->
    <div v-if="purchaseOrder.transactions && purchaseOrder.transactions.length > 0" class="card-table">
      <div class="table-header">
        <h2>Linked Accounts Payable Bills (Vendor Khata)</h2>
        <div style="font-size: 0.825rem; color: var(--text-muted);">
          Invoices automatically posted to supplier ledger upon Goods Received Notes (GRN)
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="table">
          <thead>
            <tr>
              <th>Invoice / Receipt #</th>
              <th>Date</th>
              <th>Type</th>
              <th style="text-align: right;">Amount Billed</th>
              <th style="text-align: right;">Supplier Balance After</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tx in purchaseOrder.transactions" :key="tx.id">
              <td style="font-family: var(--font-mono); font-weight: 700; color: var(--text-primary);">
                {{ tx.receipt_number }}
              </td>
              <td style="font-size: 0.825rem; color: var(--text-muted);">
                {{ formatDateTime(tx.created_at) }}
              </td>
              <td>
                <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                  Bill Received (+Payable)
                </span>
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 800; color: #ef4444;">
                +{{ formatMoney(tx.amount_minor) }}
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 800; color: var(--text-primary);">
                {{ formatMoney(tx.balance_after_minor) }}
              </td>
              <td style="font-size: 0.8rem; color: var(--text-secondary);">
                {{ tx.notes || '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Goods Received Note (GRN) Modal -->
    <div
      v-if="showGrnModal"
      style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 1rem;"
      @click.self="showGrnModal = false"
    >
      <div class="card" style="width: 100%; max-width: 650px; padding: 2rem; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
          <div>
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin: 0;">
              Process Goods Received Note (GRN)
            </h3>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
              Verify shipment quantities received. Inventory will be restocked automatically.
            </div>
          </div>
          <button
            type="button"
            class="btn btn-secondary"
            style="padding: 0.25rem 0.5rem; font-size: 0.85rem;"
            @click="showGrnModal = false"
          >
            &times;
          </button>
        </div>

        <form @submit.prevent="submitGrn">
          <div style="margin-bottom: 1.25rem; max-height: 280px; overflow-y: auto; padding-right: 0.5rem;">
            <div
              v-for="(item, idx) in grnForm.items"
              :key="item.item_id"
              style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 0.75rem;"
            >
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                <div>
                  <div style="font-weight: 700; font-size: 0.875rem; color: var(--text-primary);">
                    {{ getOriginalItem(item.item_id)?.product?.name }}
                  </div>
                  <div style="font-size: 0.72rem; color: var(--text-muted);">
                    Ordered: {{ getOriginalItem(item.item_id)?.ordered_quantity }} {{ getOriginalItem(item.item_id)?.unit_code }}
                    &bull; Previously Received: {{ getOriginalItem(item.item_id)?.received_quantity }} {{ getOriginalItem(item.item_id)?.unit_code }}
                  </div>
                </div>
                <div style="text-align: right;">
                  <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: #6366f1;">
                    {{ getOriginalItem(item.item_id)?.unit_code }} ({{ getOriginalItem(item.item_id)?.unit_conversion_factor }}x)
                  </span>
                </div>
              </div>

              <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary);">
                  Quantity Arrived Now:
                </label>
                <div style="width: 140px;">
                  <input
                    v-model.number="item.quantity_received"
                    type="number"
                    step="any"
                    min="0"
                    :max="getRemainingQty(item.item_id)"
                    class="form-control"
                    style="font-family: var(--font-mono); font-weight: 700; font-size: 0.95rem; text-align: right;"
                    required
                  />
                </div>
              </div>
            </div>
          </div>

          <div style="margin-bottom: 1.5rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              GRN Inspection Notes &amp; Bilty / Carrier Number
            </label>
            <input
              v-model="grnForm.notes"
              type="text"
              class="form-control"
              placeholder="e.g. Carrier TCS Bilty #49281, 2 cartons inspected and in pristine condition"
            />
          </div>

          <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem;">
            <button
              type="button"
              class="btn btn-secondary"
              @click="showGrnModal = false"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="grnForm.processing"
              style="min-width: 170px; background: #10b981; border-color: #10b981;"
            >
              <span v-if="grnForm.processing">Receiving Goods...</span>
              <span v-else>Confirm GRN Receipt</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

interface PoItem {
  id: string
  product_id: string
  unit_code: string
  unit_conversion_factor: number
  ordered_quantity: number
  received_quantity: number
  cost_price_minor: number
  line_total_minor: number
  product?: {
    id: string
    name: string
    sku: string | null
    barcode: string | null
    unit_of_measure: string | null
  }
}

interface SupplierTransactionSummary {
  id: string
  receipt_number: string
  created_at: string
  amount_minor: number
  balance_after_minor: number
  notes: string | null
}

const props = defineProps<{
  purchaseOrder: {
    id: string
    po_number: string
    status: string
    created_at: string
    received_at: string | null
    expected_delivery_date: string | null
    subtotal_minor: number
    tax_minor: number
    total_minor: number
    notes: string | null
    supplier: {
      id: string
      name: string
      phone: string
      ntn: string | null
    }
    location?: {
      id: string
      name: string
    }
    user?: {
      id: string
      name: string
    }
    items: PoItem[]
    transactions?: SupplierTransactionSummary[]
  }
}>()

const showGrnModal = ref(false)

const grnForm = useForm({
  items: [] as Array<{ item_id: string; quantity_received: number }>,
  notes: '',
})

function getOriginalItem(itemId: string): PoItem | undefined {
  return props.purchaseOrder.items.find(i => i.id === itemId)
}

function getRemainingQty(itemId: string): number {
  const item = getOriginalItem(itemId)
  if (!item) return 0
  return Math.max(0, item.ordered_quantity - item.received_quantity)
}

function openGrnModal() {
  grnForm.items = props.purchaseOrder.items.map(i => ({
    item_id: i.id,
    quantity_received: Math.max(0, i.ordered_quantity - i.received_quantity),
  }))
  showGrnModal.value = true
}

function submitGrn() {
  grnForm.post(`/purchases/${props.purchaseOrder.id}/receive`, {
    onSuccess: () => {
      showGrnModal.value = false
    },
  })
}

function printPO() {
  window.print()
}

function formatMoney(amountMinor: number): string {
  const rs = (amountMinor / 100).toLocaleString('en-PK', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  })
  return `Rs. ${rs}`
}

function formatDateTime(isoStr: string | null): string {
  if (!isoStr) return '—'
  const d = new Date(isoStr)
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
    ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })
}

function formatStatus(status: string): string {
  switch (status) {
    case 'draft': return 'Draft'
    case 'ordered': return 'Ordered (Pending Delivery)'
    case 'partial_received': return 'Partially Received'
    case 'received': return 'Fully Received'
    case 'canceled': return 'Canceled'
    default: return status
  }
}

function getStatusBadgeStyle(status: string) {
  switch (status) {
    case 'received':
      return { background: 'rgba(16, 185, 129, 0.15)', color: '#10b981', fontWeight: '700' }
    case 'partial_received':
      return { background: 'rgba(245, 158, 11, 0.15)', color: '#f59e0b', fontWeight: '700' }
    case 'ordered':
      return { background: 'rgba(99, 102, 241, 0.15)', color: '#6366f1', fontWeight: '700' }
    default:
      return { background: 'rgba(100, 116, 139, 0.15)', color: '#94a3b8', fontWeight: '700' }
  }
}

function getStatusDotColor(status: string): string {
  switch (status) {
    case 'received': return '#10b981'
    case 'partial_received': return '#f59e0b'
    case 'ordered': return '#6366f1'
    default: return '#94a3b8'
  }
}
</script>
