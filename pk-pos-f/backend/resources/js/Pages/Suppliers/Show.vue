<template>
  <AdminLayout :title="`Supplier Statement: ${supplier.name}`">
    <!-- Top Action & Navigation Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <Link href="/suppliers" class="btn btn-outline" style="padding: 0.4rem 0.65rem;" title="Back to Supplier Directory">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
        </Link>
        <div>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <h1 style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); margin: 0;">
              {{ supplier.name }}
            </h1>
            <span
              v-if="supplier.current_balance_minor > 0"
              class="badge"
              style="background: rgba(239, 68, 68, 0.15); color: #ef4444; font-weight: 700;"
            >
              <span class="badge-dot" style="background: #ef4444;"></span>
              Payable Pending
            </span>
            <span
              v-else
              class="badge"
              style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700;"
            >
              <span class="badge-dot" style="background: #10b981;"></span>
              Account Settled
            </span>
          </div>
          <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem; display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
            <span>Phone: <strong style="color: var(--text-secondary); font-family: var(--font-mono);">{{ supplier.phone }}</strong></span>
            <span v-if="supplier.contact_person">&bull; Contact: <strong>{{ supplier.contact_person }}</strong></span>
            <span v-if="supplier.city">&bull; City: <strong>{{ supplier.city }}</strong></span>
            <span v-if="supplier.ntn">&bull; NTN: <strong style="font-family: var(--font-mono);">{{ supplier.ntn }}</strong></span>
            <span v-if="supplier.strn">&bull; STRN: <strong style="font-family: var(--font-mono);">{{ supplier.strn }}</strong></span>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <button
          type="button"
          class="btn btn-outline"
          style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.9rem;"
          @click="printStatement"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 6 2 18 2 18 9"></polyline>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <rect x="6" y="14" width="12" height="8"></rect>
          </svg>
          <span>Print Statement</span>
        </button>

        <Link
          href="/purchases/create"
          class="btn btn-outline"
          style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.9rem;"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          <span>New Purchase Order</span>
        </Link>

        <button
          type="button"
          class="btn btn-primary"
          style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 1.1rem; background: #10b981; border-color: #10b981;"
          @click="showPaymentModal = true"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <span>Record Vendor Payment</span>
        </button>
      </div>
    </div>

    <!-- Financial Metrics Strip -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card" style="border-left: 4px solid #ef4444;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
          Current Accounts Payable
        </div>
        <div style="font-size: 1.75rem; font-weight: 800; color: #ef4444; margin-top: 0.25rem; font-family: var(--font-mono);">
          {{ formatMoney(supplier.current_balance_minor) }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Outstanding store liability owed to this supplier
        </div>
      </div>

      <div class="card" style="border-left: 4px solid #6366f1;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
          Credit Payment Terms
        </div>
        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem;">
          {{ supplier.payment_terms_days === 0 ? 'Cash on Delivery' : `Net ${supplier.payment_terms_days} Days` }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Agreed repayment period after shipment receipt
        </div>
      </div>

      <div class="card" style="border-left: 4px solid #10b981;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
          Warehouse Address
        </div>
        <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin-top: 0.35rem; line-height: 1.35;">
          {{ supplier.address || 'No physical address configured' }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          {{ supplier.city ? `${supplier.city}, Pakistan` : 'Pakistan' }}
        </div>
      </div>
    </div>

    <!-- Tabs: Ledger vs Recent Purchase Orders -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
      <button
        type="button"
        class="btn"
        :class="activeTab === 'ledger' ? 'btn-primary' : 'btn-secondary'"
        style="padding: 0.4rem 1rem; font-size: 0.85rem;"
        @click="activeTab = 'ledger'"
      >
        Accounts Payable Ledger ({{ transactions.total || transactions.data?.length || 0 }})
      </button>
      <button
        type="button"
        class="btn"
        :class="activeTab === 'pos' ? 'btn-primary' : 'btn-secondary'"
        style="padding: 0.4rem 1rem; font-size: 0.85rem;"
        @click="activeTab = 'pos'"
      >
        Recent Purchase Orders ({{ purchaseOrders?.length || 0 }})
      </button>
    </div>

    <!-- Tab 1: Ledger Statement -->
    <div v-if="activeTab === 'ledger'" class="card-table">
      <div class="table-header">
        <h2>Vendor Statement &amp; Transaction Journal</h2>
        <div style="font-size: 0.825rem; color: var(--text-muted);">
          Chronological record of goods invoices received and supplier payments disbursed
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="table">
          <thead>
            <tr>
              <th>Date &amp; Time</th>
              <th>Transaction / Bill #</th>
              <th>Type</th>
              <th>Method &amp; Ref</th>
              <th style="text-align: right;">Amount</th>
              <th style="text-align: right;">Balance After</th>
              <th>Recorded By</th>
              <th>Notes / Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!transactions.data || transactions.data.length === 0">
              <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                <div style="font-size: 1rem; font-weight: 600;">No transactions recorded</div>
                <div style="font-size: 0.825rem; margin-top: 0.25rem;">Shipment receipts or vendor payment disbursements will appear here</div>
              </td>
            </tr>
            <tr v-for="tx in transactions.data" :key="tx.id">
              <td style="font-size: 0.825rem; font-family: var(--font-mono); color: var(--text-muted); white-space: nowrap;">
                {{ formatDateTime(tx.created_at) }}
              </td>
              <td>
                <div style="font-family: var(--font-mono); font-weight: 700; color: var(--text-primary);">
                  {{ tx.receipt_number || '—' }}
                </div>
                <div v-if="tx.purchase_order" style="font-size: 0.72rem; color: #6366f1;">
                  <Link :href="`/purchases/${tx.purchase_order.id}`" style="color: inherit; text-decoration: underline;">
                    {{ tx.purchase_order.po_number }}
                  </Link>
                </div>
              </td>
              <td>
                <span
                  v-if="tx.type === 'bill_received'"
                  class="badge"
                  style="background: rgba(239, 68, 68, 0.15); color: #ef4444;"
                >
                  Bill Received (+Payable)
                </span>
                <span
                  v-else-if="tx.type === 'payment_made'"
                  class="badge"
                  style="background: rgba(16, 185, 129, 0.15); color: #10b981;"
                >
                  Payment Made (-Payable)
                </span>
                <span v-else class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                  Adjustment
                </span>
              </td>
              <td>
                <div v-if="tx.payment_method" style="font-size: 0.8rem; text-transform: capitalize; color: var(--text-primary); font-weight: 600;">
                  {{ tx.payment_method.replace('_', ' ') }}
                </div>
                <div v-if="tx.reference_number" style="font-size: 0.72rem; font-family: var(--font-mono); color: var(--text-muted);">
                  Ref: {{ tx.reference_number }}
                </div>
                <span v-if="!tx.payment_method && !tx.reference_number" style="font-size: 0.75rem; color: var(--text-muted);">—</span>
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 800; font-size: 0.95rem;">
                <span :style="{ color: tx.type === 'bill_received' ? '#ef4444' : '#10b981' }">
                  {{ tx.type === 'bill_received' ? '+' : '-' }}{{ formatMoney(tx.amount_minor) }}
                </span>
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 800; font-size: 0.95rem; color: var(--text-primary);">
                {{ formatMoney(tx.balance_after_minor) }}
              </td>
              <td style="font-size: 0.8rem; color: var(--text-muted);">
                {{ tx.recorded_by?.name || 'System' }}
              </td>
              <td style="font-size: 0.8rem; color: var(--text-secondary); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                {{ tx.notes || '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="transactions.links && transactions.links.length > 3" style="display: flex; justify-content: flex-end; padding: 1rem; gap: 0.35rem; border-top: 1px solid var(--border-color);">
        <Link
          v-for="(link, idx) in transactions.links"
          :key="idx"
          :href="link.url || '#'"
          class="btn"
          :class="{ 'btn-primary': link.active, 'btn-secondary': !link.active }"
          :style="{ pointerEvents: !link.url ? 'none' : 'auto', opacity: !link.url ? 0.5 : 1, padding: '0.35rem 0.7rem', fontSize: '0.8rem' }"
          v-html="link.label"
        />
      </div>
    </div>

    <!-- Tab 2: Recent Purchase Orders -->
    <div v-if="activeTab === 'pos'" class="card-table">
      <div class="table-header" style="display: flex; align-items: center; justify-content: space-between;">
        <div>
          <h2>Purchase Orders Linked to Supplier</h2>
          <div style="font-size: 0.825rem; color: var(--text-muted);">
            Shipments and purchase receipts from this distributor
          </div>
        </div>
        <Link href="/purchases/create" class="btn btn-outline" style="padding: 0.4rem 0.8rem;">
          + Create Purchase Order
        </Link>
      </div>

      <div style="overflow-x: auto;">
        <table class="table">
          <thead>
            <tr>
              <th>PO Number</th>
              <th>Destination Location</th>
              <th>Status</th>
              <th>Expected Date</th>
              <th style="text-align: right;">Total Amount</th>
              <th style="text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!purchaseOrders || purchaseOrders.length === 0">
              <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                <div style="font-size: 1rem; font-weight: 600;">No purchase orders on record</div>
                <div style="font-size: 0.825rem; margin-top: 0.25rem;">Create a purchase order to initiate replenishment</div>
              </td>
            </tr>
            <tr v-for="po in purchaseOrders" :key="po.id">
              <td>
                <div style="font-family: var(--font-mono); font-weight: 800; color: var(--text-primary);">
                  {{ po.po_number }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                  {{ formatDateTime(po.created_at) }}
                </div>
              </td>
              <td>
                <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: #6366f1;">
                  {{ po.location?.name || 'Main Warehouse' }}
                </span>
              </td>
              <td>
                <span
                  class="badge"
                  :style="getStatusBadgeStyle(po.status)"
                >
                  {{ formatStatus(po.status) }}
                </span>
              </td>
              <td style="font-size: 0.825rem; color: var(--text-muted);">
                {{ po.expected_delivery_date || '—' }}
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 800; font-size: 0.95rem; color: var(--text-primary);">
                {{ formatMoney(po.total_minor) }}
              </td>
              <td style="text-align: center;">
                <Link
                  :href="`/purchases/${po.id}`"
                  class="btn btn-secondary"
                  style="padding: 0.35rem 0.65rem; font-size: 0.78rem;"
                >
                  View Details &amp; GRN
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Vendor Payment Modal -->
    <div
      v-if="showPaymentModal"
      style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 1rem;"
      @click.self="showPaymentModal = false"
    >
      <div class="card" style="width: 100%; max-width: 500px; padding: 2rem; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
          <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin: 0;">
            Record Vendor Disbursement
          </h3>
          <button
            type="button"
            class="btn btn-secondary"
            style="padding: 0.25rem 0.5rem; font-size: 0.85rem;"
            @click="showPaymentModal = false"
          >
            &times;
          </button>
        </div>

        <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1.25rem;">
          <div style="font-size: 0.75rem; color: #ef4444; font-weight: 700; text-transform: uppercase;">
            Outstanding Payable Balance
          </div>
          <div style="font-size: 1.35rem; font-weight: 800; font-family: var(--font-mono); color: #ef4444;">
            {{ formatMoney(supplier.current_balance_minor) }}
          </div>
        </div>

        <form @submit.prevent="submitPayment">
          <div style="margin-bottom: 1rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Payment Amount (PKR) <span style="color: #ef4444;">*</span>
            </label>
            <div style="position: relative;">
              <span style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--text-muted); font-size: 0.9rem;">
                Rs.
              </span>
              <input
                v-model.number="paymentPkr"
                type="number"
                min="1"
                step="any"
                class="form-control"
                style="padding-left: 2.75rem; font-family: var(--font-mono); font-size: 1.1rem; font-weight: 800;"
                placeholder="0"
                required
              />
            </div>
            <div style="display: flex; gap: 0.5rem; margin-top: 0.4rem;">
              <button
                type="button"
                class="btn btn-secondary"
                style="font-size: 0.72rem; padding: 0.2rem 0.5rem;"
                @click="paymentPkr = Math.round(supplier.current_balance_minor / 100)"
              >
                Full Settle ({{ formatMoney(supplier.current_balance_minor) }})
              </button>
            </div>
          </div>

          <div style="margin-bottom: 1rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Disbursement Method <span style="color: #ef4444;">*</span>
            </label>
            <select v-model="paymentForm.payment_method" class="form-control" required>
              <option value="bank_transfer">Bank Transfer (Direct / Online)</option>
              <option value="cheque">Crossed Cheque</option>
              <option value="raast">Raast Instant P2P / P2M</option>
              <option value="cash">Cash Payment</option>
            </select>
          </div>

          <div style="margin-bottom: 1rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Cheque # / Bank Reference ID
            </label>
            <input
              v-model="paymentForm.reference_number"
              type="text"
              class="form-control"
              placeholder="e.g. CHQ-882910 or HBL-TX-9901"
            />
          </div>

          <div style="margin-bottom: 1.5rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Notes / Receipt Remarks
            </label>
            <input
              v-model="paymentForm.notes"
              type="text"
              class="form-control"
              placeholder="e.g. Settle Invoice for October stock replenishment"
            />
          </div>

          <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem;">
            <button
              type="button"
              class="btn btn-secondary"
              @click="showPaymentModal = false"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="paymentForm.processing || !paymentPkr || paymentPkr <= 0"
              style="min-width: 150px; background: #10b981; border-color: #10b981;"
            >
              <span v-if="paymentForm.processing">Recording...</span>
              <span v-else>Confirm Payment</span>
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

interface PurchaseOrderSummary {
  id: string
  po_number: string
  status: string
  created_at: string
  expected_delivery_date: string | null
  total_minor: number
  location?: {
    id: string
    name: string
  }
}

interface TransactionItem {
  id: string
  type: string
  amount_minor: number
  balance_after_minor: number
  payment_method: string | null
  receipt_number: string | null
  reference_number: string | null
  created_at: string
  notes: string | null
  purchase_order?: {
    id: string
    po_number: string
    total_minor: number
  }
  recorded_by?: {
    id: string
    name: string
  }
}

interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

const props = defineProps<{
  supplier: {
    id: string
    name: string
    contact_person: string | null
    phone: string
    email: string | null
    cnic: string | null
    ntn: string | null
    strn: string | null
    address: string | null
    city: string | null
    payment_terms_days: number
    current_balance_minor: number
  }
  transactions: {
    data: TransactionItem[]
    links: PaginationLink[]
    total: number
  }
  purchaseOrders: PurchaseOrderSummary[]
}>()

const activeTab = ref<'ledger' | 'pos'>('ledger')
const showPaymentModal = ref(false)
const paymentPkr = ref<number>(0)

const paymentForm = useForm({
  amount_minor: 0,
  payment_method: 'bank_transfer',
  reference_number: '',
  notes: '',
})

function submitPayment() {
  paymentForm.amount_minor = Math.round((paymentPkr.value || 0) * 100)
  paymentForm.post(`/suppliers/${props.supplier.id}/payments`, {
    onSuccess: () => {
      showPaymentModal.value = false
      paymentPkr.value = 0
      paymentForm.reset('notes', 'reference_number')
    },
  })
}

function printStatement() {
  window.print()
}

function formatMoney(minor: number): string {
  if (minor === undefined || minor === null) return 'Rs. 0'
  const val = minor / 100
  return 'Rs. ' + val.toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
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
</script>
