<template>
  <AdminLayout title="Sales Returns & FBR Credit Notes">
    <!-- Top KPI Metrics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(244, 63, 94, 0.06) 100%); border: 1px solid rgba(239, 68, 68, 0.25);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #ef4444; letter-spacing: 0.05em;">
              Total Refunds Issued
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ formatMoney(metrics.total_refunded_minor) }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
              Total monetary value refunded to customers
            </div>
          </div>
          <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(239, 68, 68, 0.15); color: #ef4444; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="1 4 1 10 7 10"></polyline>
              <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
            </svg>
          </div>
        </div>
      </div>

      <div class="card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(139, 92, 246, 0.06) 100%); border: 1px solid rgba(99, 102, 241, 0.25);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #6366f1; letter-spacing: 0.05em;">
              Returns Logged
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ metrics.total_returns_count }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
              Total return events processed across all terminals
            </div>
          </div>
          <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="4" width="20" height="16" rx="2"></rect>
              <line x1="2" y1="10" x2="22" y2="10"></line>
            </svg>
          </div>
        </div>
      </div>

      <div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(5, 150, 105, 0.06) 100%); border: 1px solid rgba(16, 185, 129, 0.25);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #10b981; letter-spacing: 0.05em;">
              FBR Credit Notes (Type 2)
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ metrics.fbr_credit_notes_count }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
              Statutory credit notes transmitted to FBR IRIS
            </div>
          </div>
          <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <polyline points="9 15 12 18 15 15"></polyline>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Card -->
    <div class="card-table">
      <div class="table-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
          <h2>Sales Returns &amp; Credit Note Journal</h2>
          <div style="font-size: 0.825rem; color: var(--text-muted);">
            Manage item returns, physical shelf restocking, drawer cash refunds, and FBR Credit Note compliance
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
          <form @submit.prevent="applyFilters" style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <select v-model="selectedRefundMethod" class="form-control" style="width: 170px; font-size: 0.825rem;" @change="applyFilters">
              <option value="">All Refund Methods</option>
              <option value="cash">Cash Refund</option>
              <option value="credit_note_khata">Customer Khata Credit</option>
              <option value="original_payment">Original Payment</option>
            </select>

            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search Return #, Invoice #..."
              class="form-control"
              style="width: 200px; font-size: 0.825rem;"
            />

            <button type="submit" class="btn btn-secondary" style="padding: 0.45rem 0.75rem;">
              Filter
            </button>
            <button
              v-if="filters.q || filters.refund_method"
              type="button"
              class="btn btn-secondary"
              style="padding: 0.45rem 0.75rem;"
              @click="clearFilters"
            >
              Reset
            </button>
          </form>

          <Link href="/returns/create" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.4rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Process Return</span>
          </Link>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="table">
          <thead>
            <tr>
              <th>Return # &amp; Date</th>
              <th>Original Invoice</th>
              <th>Customer</th>
              <th>Location</th>
              <th>Reason</th>
              <th>Refund Method</th>
              <th style="text-align: right;">Refund Amount</th>
              <th>FBR Credit Note</th>
              <th style="text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="returns.data.length === 0">
              <td colspan="9" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                <div style="font-size: 1rem; font-weight: 600;">No sales returns found</div>
                <div style="font-size: 0.825rem; margin-top: 0.25rem;">Process a new return or adjust search filters</div>
              </td>
            </tr>
            <tr v-for="ret in returns.data" :key="ret.id">
              <td>
                <div style="font-weight: 800; font-family: var(--font-mono); color: var(--text-primary);">
                  {{ ret.return_number }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                  {{ formatDateTime(ret.created_at) }}
                </div>
              </td>
              <td>
                <div style="font-weight: 700; font-family: var(--font-mono); color: #6366f1;">
                  {{ ret.sale?.sale_number }}
                </div>
                <div v-if="ret.sale?.fbr_usin" style="font-size: 0.72rem; color: var(--text-muted);">
                  USIN: {{ ret.sale.fbr_usin }}
                </div>
              </td>
              <td>
                <div v-if="ret.customer" style="font-weight: 600; color: var(--text-primary);">
                  {{ ret.customer.name }}
                </div>
                <div v-else style="font-size: 0.78rem; color: var(--text-muted);">
                  Walk-in Retail
                </div>
              </td>
              <td>
                <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: #6366f1;">
                  {{ ret.location?.name || 'Main Location' }}
                </span>
              </td>
              <td>
                <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; text-transform: capitalize;">
                  {{ ret.reason.replace(/_/g, ' ') }}
                </span>
              </td>
              <td>
                <span
                  class="badge"
                  :style="ret.refund_method === 'cash' ? { background: 'rgba(16, 185, 129, 0.15)', color: '#10b981' } : { background: 'rgba(99, 102, 241, 0.15)', color: '#6366f1' }"
                >
                  {{ ret.refund_method === 'cash' ? 'Cash Refund' : (ret.refund_method === 'credit_note_khata' ? 'Khata Credit' : ret.refund_method) }}
                </span>
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 800; font-size: 0.95rem; color: #ef4444;">
                -{{ formatMoney(ret.total_refund_minor) }}
              </td>
              <td>
                <div v-if="ret.fbr_credit_note_number" style="font-family: var(--font-mono); font-size: 0.75rem; font-weight: 700; color: #10b981;">
                  {{ ret.fbr_credit_note_number }}
                </div>
                <span v-else class="badge" style="background: rgba(100, 116, 139, 0.15); color: #94a3b8;">
                  Exempt / Offline
                </span>
              </td>
              <td style="text-align: center;">
                <Link
                  :href="`/returns/${ret.id}`"
                  class="btn btn-secondary"
                  style="padding: 0.35rem 0.65rem; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 0.35rem;"
                >
                  <span>Credit Slip</span>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="returns.links && returns.links.length > 3" style="display: flex; justify-content: flex-end; padding: 1rem; gap: 0.35rem; border-top: 1px solid var(--border-color);">
        <Link
          v-for="(link, idx) in returns.links"
          :key="idx"
          :href="link.url || '#'"
          class="btn"
          :class="{ 'btn-primary': link.active, 'btn-secondary': !link.active }"
          :style="{ pointerEvents: !link.url ? 'none' : 'auto', opacity: !link.url ? 0.5 : 1, padding: '0.35rem 0.7rem', fontSize: '0.8rem' }"
          v-html="link.label"
        />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

interface SaleReturnSummary {
  id: string
  return_number: string
  reason: string
  refund_method: string
  total_refund_minor: number
  fbr_status: string
  fbr_credit_note_number: string | null
  created_at: string
  items_count: number
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
  location?: {
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
  returns: {
    data: SaleReturnSummary[]
    links: PaginationLink[]
  }
  filters: {
    q?: string
    refund_method?: string
  }
  metrics: {
    total_returns_count: number
    total_refunded_minor: number
    fbr_credit_notes_count: number
  }
}>()

const searchQuery = ref(props.filters.q || '')
const selectedRefundMethod = ref(props.filters.refund_method || '')

function applyFilters() {
  router.get('/returns', {
    q: searchQuery.value || undefined,
    refund_method: selectedRefundMethod.value || undefined,
  }, { preserveState: true, replace: true })
}

function clearFilters() {
  searchQuery.value = ''
  selectedRefundMethod.value = ''
  router.get('/returns', {}, { preserveState: true, replace: true })
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
