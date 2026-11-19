<template>
  <AdminLayout :title="`Credit Note: ${saleReturn.return_number}`">
    <!-- Action Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <Link href="/returns" class="btn btn-outline" style="padding: 0.4rem 0.65rem;" title="Back to Returns Directory">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
        </Link>
        <div>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <h1 style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); margin: 0; font-family: var(--font-mono);">
              {{ saleReturn.return_number }}
            </h1>
            <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700;">
              FBR Credit Note (Type 2)
            </span>
          </div>
          <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem; display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
            <span>Processed: <strong>{{ formatDateTime(saleReturn.created_at) }}</strong></span>
            <span v-if="saleReturn.user">&bull; Cashier: <strong>{{ saleReturn.user.name }}</strong></span>
            <span v-if="saleReturn.location">&bull; Location: <strong>{{ saleReturn.location.name }}</strong></span>
          </div>
        </div>
      </div>

      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <button
          type="button"
          class="btn btn-primary"
          style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 1rem;"
          @click="printSlip"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 6 2 18 2 18 9"></polyline>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <rect x="6" y="14" width="12" height="8"></rect>
          </svg>
          <span>Print Credit Note Slip</span>
        </button>
      </div>
    </div>

    <!-- Details Strip Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card" style="border-left: 4px solid #6366f1;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
          Original Sale Invoice
        </div>
        <div style="font-size: 1.25rem; font-weight: 800; color: #6366f1; margin-top: 0.25rem; font-family: var(--font-mono);">
          {{ saleReturn.sale?.sale_number }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Total Sold: {{ formatMoney(saleReturn.sale?.total_minor || 0) }}
        </div>
      </div>

      <div class="card" style="border-left: 4px solid #f59e0b;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
          Reason for Return
        </div>
        <div style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; text-transform: capitalize;">
          {{ saleReturn.reason.replace(/_/g, ' ') }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          {{ saleReturn.notes || 'No customer remarks logged' }}
        </div>
      </div>

      <div class="card" style="border-left: 4px solid #10b981;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
          Refund Settlement
        </div>
        <div style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem;">
          {{ saleReturn.refund_method === 'cash' ? 'Cash Refund' : (saleReturn.refund_method === 'credit_note_khata' ? 'Customer Khata Credit' : saleReturn.refund_method) }}
        </div>
        <div v-if="saleReturn.customer" style="font-size: 0.75rem; color: #6366f1; margin-top: 0.2rem;">
          Credited to: {{ saleReturn.customer.name }}
        </div>
        <div v-else style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Cash Drawer Float Payout
        </div>
      </div>

      <div class="card" style="border-left: 4px solid #ef4444;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
          Total Refund Value
        </div>
        <div style="font-size: 1.65rem; font-weight: 800; color: #ef4444; margin-top: 0.25rem; font-family: var(--font-mono);">
          -{{ formatMoney(saleReturn.total_refund_minor) }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Subtotal: {{ formatMoney(saleReturn.subtotal_minor) }} &bull; Tax: {{ formatMoney(saleReturn.tax_minor) }}
        </div>
      </div>
    </div>

    <!-- Items Returned Table -->
    <div class="card-table" style="margin-bottom: 1.5rem;">
      <div class="table-header">
        <h2>Items Returned &amp; Restock Policy</h2>
        <div style="font-size: 0.825rem; color: var(--text-muted);">
          Line-by-line verification of returned products and shelf inventory condition
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="table">
          <thead>
            <tr>
              <th>Product / SKU</th>
              <th style="text-align: center;">Packaging / Unit</th>
              <th style="text-align: center;">Quantity Returned</th>
              <th>Restock Action</th>
              <th style="text-align: right;">Unit Price</th>
              <th style="text-align: right;">Tax Refund</th>
              <th style="text-align: right;">Line Refund Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in saleReturn.items" :key="item.id">
              <td>
                <div style="font-weight: 700; color: var(--text-primary);">
                  {{ item.product?.name || 'Product' }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono);">
                  SKU: {{ item.product?.sku || 'N/A' }} &bull; Barcode: {{ item.product?.barcode || 'N/A' }}
                </div>
              </td>
              <td style="text-align: center;">
                <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: #6366f1;">
                  {{ item.unit_code }} ({{ item.unit_conversion_factor }}x)
                </span>
              </td>
              <td style="text-align: center; font-family: var(--font-mono); font-weight: 800; color: #ef4444;">
                {{ item.quantity_returned }} {{ item.unit_code }}
                <div v-if="item.unit_conversion_factor > 1" style="font-size: 0.72rem; color: var(--text-muted); font-weight: normal;">
                  = {{ item.quantity_returned * item.unit_conversion_factor }} base units
                </div>
              </td>
              <td>
                <span
                  v-if="item.restock_condition === 'restock_shelf'"
                  class="badge"
                  style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700;"
                >
                  ✓ Restocked to Shelf
                </span>
                <span
                  v-else-if="item.restock_condition === 'damaged_quarantine'"
                  class="badge"
                  style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; font-weight: 700;"
                >
                  ⚠ Damaged (Quarantined)
                </span>
                <span v-else class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                  ✕ Discarded
                </span>
              </td>
              <td style="text-align: right; font-family: var(--font-mono);">
                {{ formatMoney(item.unit_price_minor) }}
              </td>
              <td style="text-align: right; font-family: var(--font-mono); color: var(--text-muted);">
                {{ formatMoney(item.tax_minor) }}
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 800; color: #ef4444;">
                -{{ formatMoney(item.refund_amount_minor) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Statutory FBR Fiscal Credit Note Card -->
    <div class="card" style="padding: 1.5rem; background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(5, 150, 105, 0.03) 100%); border: 1px solid rgba(16, 185, 129, 0.3);">
      <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
          <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #10b981; letter-spacing: 0.05em;">
            Statutory FBR Fiscal Credit Note Compliance
          </div>
          <div style="font-size: 1.25rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-primary); margin-top: 0.25rem;">
            CN No: {{ saleReturn.fbr_credit_note_number || 'N/A' }}
          </div>
          <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
            Referenced Original Sale: <strong>{{ saleReturn.sale?.sale_number }}</strong>
            &bull; Original FBR USIN: <strong>{{ saleReturn.sale?.fbr_usin || 'N/A' }}</strong>
            &bull; FBR InvoiceType: <strong>2 (Credit Note / Sales Return)</strong>
          </div>
        </div>

        <div style="text-align: right;">
          <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 800; font-size: 0.85rem; padding: 0.35rem 0.75rem;">
            ✓ FBR Fiscal Synchronized
          </span>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

interface ReturnItem {
  id: string
  product_id: string
  unit_code: string
  unit_conversion_factor: number
  quantity_returned: number
  unit_price_minor: number
  tax_minor: number
  refund_amount_minor: number
  restock_condition: string
  product?: {
    id: string
    name: string
    sku: string | null
    barcode: string | null
  }
}

const props = defineProps<{
  saleReturn: {
    id: string
    return_number: string
    reason: string
    refund_method: string
    subtotal_minor: number
    tax_minor: number
    total_refund_minor: number
    fbr_status: string
    fbr_credit_note_number: string | null
    fbr_credit_note_qr: string | null
    notes: string | null
    created_at: string
    sale?: {
      id: string
      sale_number: string
      total_minor: number
      fbr_usin: string | null
    }
    customer?: {
      id: string
      name: string
      phone: string
    }
    user?: {
      id: string
      name: string
    }
    location?: {
      id: string
      name: string
    }
    items: ReturnItem[]
  }
}>()

function printSlip() {
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
</script>
