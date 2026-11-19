<template>
  <AdminLayout title="Process Sales Return & FBR Credit Note">
    <div style="max-width: 900px; margin: 0 auto;">
      <!-- Header -->
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
        <div>
          <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-primary); margin: 0;">
            Process Sales Return &amp; Credit Note
          </h2>
          <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
            Lookup original customer receipt, verify returnable quantities, restock shelf, and issue statutory FBR Credit Note
          </div>
        </div>
        <Link href="/returns" class="btn btn-outline" style="padding: 0.4rem 0.85rem;">
          &larr; Back to Returns
        </Link>
      </div>

      <!-- Step 1: Search / Scan Invoice -->
      <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
        <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 0.75rem;">
          1. Verify Original Invoice Number or Barcode
        </div>

        <form @submit.prevent="lookupInvoice" style="display: flex; gap: 0.75rem; align-items: center;">
          <div style="flex: 1; position: relative;">
            <input
              v-model="invoiceQuery"
              type="text"
              class="form-control"
              placeholder="Enter Sale # (e.g. INV-20261122-0001) or scan receipt barcode..."
              style="font-family: var(--font-mono); font-size: 0.95rem; font-weight: 700;"
              required
            />
          </div>
          <button type="submit" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.25rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <span>Lookup Invoice</span>
          </button>
        </form>

        <div v-if="invoiceQuery && !matched_sale && hasSearched" style="color: #ef4444; font-size: 0.825rem; margin-top: 0.75rem; font-weight: 600;">
          No completed invoice found matching "{{ invoiceQuery }}". Please verify the receipt number.
        </div>
      </div>

      <!-- Step 2: Matched Sale & Return Items Selection -->
      <div v-if="matched_sale" class="card" style="padding: 1.75rem; margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.25rem;">
          <div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <span style="font-size: 1.15rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-primary);">
                Invoice #{{ matched_sale.sale_number }}
              </span>
              <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700;">
                Original Verified
              </span>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
              Location: <strong>{{ matched_sale.location?.name || 'Main Location' }}</strong>
              &bull; Original Total: <strong style="color: var(--text-primary); font-family: var(--font-mono);">{{ formatMoney(matched_sale.total_minor) }}</strong>
              &bull; Method: <strong style="text-transform: capitalize;">{{ matched_sale.payment_method }}</strong>
            </div>
          </div>

          <div v-if="matched_sale.customer" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.5rem 0.85rem;">
            <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">
              Customer (Khata)
            </div>
            <div style="font-weight: 700; font-size: 0.875rem; color: var(--text-primary);">
              {{ matched_sale.customer.name }} ({{ matched_sale.customer.phone }})
            </div>
          </div>
        </div>

        <form @submit.prevent="submitReturn">
          <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 1rem;">
            2. Select Return Quantities &amp; Restock Condition
          </div>

          <div style="overflow-x: auto; margin-bottom: 1.5rem;">
            <table class="table" style="font-size: 0.85rem;">
              <thead>
                <tr>
                  <th>Product</th>
                  <th style="text-align: center;">Sold</th>
                  <th style="text-align: center;">Returned</th>
                  <th style="text-align: center;">Returnable</th>
                  <th style="width: 110px; text-align: center;">Return Qty</th>
                  <th style="min-width: 170px;">Stock Policy</th>
                  <th style="text-align: right;">Unit Price</th>
                  <th style="text-align: right;">Line Refund</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in returnItemsState" :key="item.sale_item_id">
                  <td>
                    <div style="font-weight: 700; color: var(--text-primary);">
                      {{ item.product_name }}
                    </div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">
                      Unit: {{ item.unit_code }}
                    </div>
                  </td>
                  <td style="text-align: center; font-family: var(--font-mono); font-weight: 600;">
                    {{ item.sold_quantity }}
                  </td>
                  <td style="text-align: center; font-family: var(--font-mono); color: var(--text-muted);">
                    {{ item.already_returned_quantity }}
                  </td>
                  <td style="text-align: center; font-family: var(--font-mono); font-weight: 700; color: #10b981;">
                    {{ item.returnable_quantity }}
                  </td>
                  <td>
                    <input
                      v-model.number="item.quantity_returned"
                      type="number"
                      step="any"
                      min="0"
                      :max="item.returnable_quantity"
                      class="form-control"
                      style="font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; text-align: center;"
                      placeholder="0"
                      :disabled="item.returnable_quantity <= 0"
                    />
                  </td>
                  <td>
                    <select
                      v-model="item.restock_condition"
                      class="form-control"
                      style="font-size: 0.8rem;"
                      :disabled="item.quantity_returned <= 0"
                    >
                      <option value="restock_shelf">Restock to Shelf</option>
                      <option value="damaged_quarantine">Damaged (Quarantine)</option>
                      <option value="discard">Discard / Expired</option>
                    </select>
                  </td>
                  <td style="text-align: right; font-family: var(--font-mono);">
                    {{ formatMoney(item.unit_price_minor) }}
                  </td>
                  <td style="text-align: right; font-family: var(--font-mono); font-weight: 800; color: #ef4444;">
                    {{ formatMoney(computeLineRefund(item)) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Step 3: Refund Settlement Details -->
          <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 1rem;">
            3. Return Reason &amp; Refund Payout Method
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Reason for Return <span style="color: #ef4444;">*</span>
              </label>
              <select v-model="form.reason" class="form-control" required>
                <option value="customer_change_of_mind">Customer Change of Mind</option>
                <option value="defective">Defective / Damaged Item</option>
                <option value="wrong_item">Wrong Item Dispensed</option>
                <option value="expired">Expired / Past Date</option>
                <option value="other">Other / Custom</option>
              </select>
            </div>

            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Refund Settlement Mode <span style="color: #ef4444;">*</span>
              </label>
              <select v-model="form.refund_method" class="form-control" required>
                <option value="cash">Cash Refund (Drawer Cash Payout)</option>
                <option v-if="matched_sale.customer_id" value="credit_note_khata">
                  Customer Khata Credit (Reduce Udhaar)
                </option>
                <option value="original_payment">Original Payment Method</option>
              </select>
            </div>
          </div>

          <div style="margin-bottom: 1.5rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Internal Audit Notes
            </label>
            <input
              v-model="form.notes"
              type="text"
              class="form-control"
              placeholder="e.g. Customer brought receipt, packaging undamaged, approved by manager"
            />
          </div>

          <!-- Refund Summary Strip -->
          <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div>
              <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: #ef4444; letter-spacing: 0.05em;">
                Total Refund Payable
              </div>
              <div style="font-size: 1.65rem; font-weight: 800; color: #ef4444; font-family: var(--font-mono); margin-top: 0.25rem;">
                -{{ formatMoney(totalRefundMinor) }}
              </div>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
              <Link href="/returns" class="btn btn-secondary">
                Cancel
              </Link>
              <button
                type="submit"
                class="btn btn-primary"
                :disabled="form.processing || totalRefundMinor <= 0"
                style="min-width: 200px; background: #ef4444; border-color: #ef4444;"
              >
                <span v-if="form.processing">Processing...</span>
                <span v-else>Confirm Return &amp; FBR Credit Note</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

interface ReturnableItem {
  sale_item_id: string
  product_id: string
  product_name: string
  unit_code: string
  unit_conversion_factor: number
  sold_quantity: number
  already_returned_quantity: number
  returnable_quantity: number
  unit_price_minor: number
  tax_rate_basis_points: number
}

interface ReturnItemState extends ReturnableItem {
  quantity_returned: number
  restock_condition: 'restock_shelf' | 'damaged_quarantine' | 'discard'
}

const props = defineProps<{
  invoice_number?: string
  matched_sale?: {
    id: string
    sale_number: string
    total_minor: number
    payment_method: string
    customer_id: string | null
    customer?: {
      id: string
      name: string
      phone: string
      current_balance_minor: number
    }
    location?: {
      id: string
      name: string
    }
  } | null
  returnable_items?: ReturnableItem[]
}>()

const invoiceQuery = ref(props.invoice_number || '')
const hasSearched = ref(Boolean(props.invoice_number))

function lookupInvoice() {
  hasSearched.value = true
  router.get('/returns/create', { invoice_number: invoiceQuery.value }, { preserveState: false })
}

const returnItemsState = ref<ReturnItemState[]>(
  (props.returnable_items || []).map(item => ({
    ...item,
    quantity_returned: 0,
    restock_condition: 'restock_shelf',
  }))
)

function computeLineRefund(item: ReturnItemState): number {
  const qty = Number(item.quantity_returned) || 0
  const subtotal = Math.round(qty * item.unit_price_minor)
  const tax = Math.round((subtotal * (item.tax_rate_basis_points || 0)) / 10000)
  return subtotal + tax
}

const totalRefundMinor = computed(() => {
  return returnItemsState.value.reduce((sum, item) => sum + computeLineRefund(item), 0)
})

const form = useForm({
  sale_id: props.matched_sale?.id || '',
  reason: 'customer_change_of_mind',
  refund_method: props.matched_sale?.customer_id ? 'credit_note_khata' : 'cash',
  notes: '',
  items: [] as Array<{
    sale_item_id: string
    quantity_returned: number
    restock_condition: string
  }>,
})

function submitReturn() {
  form.sale_id = props.matched_sale?.id || ''
  form.items = returnItemsState.value
    .filter(i => (Number(i.quantity_returned) || 0) > 0)
    .map(i => ({
      sale_item_id: i.sale_item_id,
      quantity_returned: Number(i.quantity_returned),
      restock_condition: i.restock_condition,
    }))

  form.post('/returns')
}

function formatMoney(amountMinor: number): string {
  const rs = (amountMinor / 100).toLocaleString('en-PK', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  })
  return `Rs. ${rs}`
}
</script>
