<template>
  <AdminLayout title="Purchase Orders & Goods Received Notes (GRN)">
    <!-- Top Procurement Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(139, 92, 246, 0.06) 100%); border: 1px solid rgba(99, 102, 241, 0.25);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #6366f1; letter-spacing: 0.05em;">
              Total Purchase Orders
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ metrics.total_pos }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
              All procurement orders placed
            </div>
          </div>
          <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
              <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
          </div>
        </div>
      </div>

      <div class="card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(217, 119, 6, 0.06) 100%); border: 1px solid rgba(245, 158, 11, 0.25);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #f59e0b; letter-spacing: 0.05em;">
              Pending Delivery &amp; GRN
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ metrics.pending_receipt_count }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
              Orders awaiting physical stock receiving
            </div>
          </div>
          <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="1" y="3" width="15" height="13"></rect>
              <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
              <circle cx="5.5" cy="18.5" r="2.5"></circle>
              <circle cx="18.5" cy="18.5" r="2.5"></circle>
            </svg>
          </div>
        </div>
      </div>

      <div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(5, 150, 105, 0.06) 100%); border: 1px solid rgba(16, 185, 129, 0.25);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #10b981; letter-spacing: 0.05em;">
              Received Procurement
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ formatMoney(metrics.total_purchased_minor) }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
              Total goods received value in inventory
            </div>
          </div>
          <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Table Card -->
    <div class="card-table">
      <div class="table-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
          <h2>Purchase Orders Directory</h2>
          <div style="font-size: 0.825rem; color: var(--text-muted);">
            Manage distributor purchase orders, delivery statuses, carton pack lines, and Goods Received Notes (GRN)
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
          <form @submit.prevent="applyFilters" style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <select v-model="selectedStatus" class="form-control" style="width: 160px; font-size: 0.825rem;" @change="applyFilters">
              <option value="">All Statuses</option>
              <option value="ordered">Ordered (Pending)</option>
              <option value="partial_received">Partially Received</option>
              <option value="received">Fully Received</option>
              <option value="draft">Draft</option>
              <option value="canceled">Canceled</option>
            </select>

            <select v-model="selectedSupplier" class="form-control" style="width: 170px; font-size: 0.825rem;" @change="applyFilters">
              <option value="">All Suppliers</option>
              <option v-for="s in suppliers" :key="s.id" :value="s.id">
                {{ s.name }}
              </option>
            </select>

            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search PO #..."
              class="form-control"
              style="width: 170px; font-size: 0.825rem;"
            />

            <button type="submit" class="btn btn-secondary" style="padding: 0.45rem 0.75rem;">
              Filter
            </button>
            <button
              v-if="filters.q || filters.status || filters.supplier_id"
              type="button"
              class="btn btn-secondary"
              style="padding: 0.45rem 0.75rem;"
              @click="clearFilters"
            >
              Reset
            </button>
          </form>

          <Link href="/purchases/create" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.4rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Create Purchase Order</span>
          </Link>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="table">
          <thead>
            <tr>
              <th>PO Number &amp; Date</th>
              <th>Supplier / Distributor</th>
              <th>Location</th>
              <th style="text-align: center;">Lines</th>
              <th>Expected Date</th>
              <th>Status</th>
              <th style="text-align: right;">Total Value</th>
              <th style="text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="purchaseOrders.data.length === 0">
              <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                <div style="font-size: 1rem; font-weight: 600;">No purchase orders found</div>
                <div style="font-size: 0.825rem; margin-top: 0.25rem;">Create a new PO or adjust your filters</div>
              </td>
            </tr>
            <tr v-for="po in purchaseOrders.data" :key="po.id">
              <td>
                <div style="font-weight: 800; font-family: var(--font-mono); color: var(--text-primary);">
                  {{ po.po_number }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                  {{ formatDate(po.created_at) }}
                </div>
              </td>
              <td>
                <div style="font-weight: 700; color: var(--text-primary);">
                  {{ po.supplier?.name || '—' }}
                </div>
                <div v-if="po.supplier?.phone" style="font-size: 0.75rem; font-family: var(--font-mono); color: var(--text-muted);">
                  {{ po.supplier.phone }}
                </div>
              </td>
              <td>
                <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: #6366f1;">
                  {{ po.location?.name || 'Main Warehouse' }}
                </span>
              </td>
              <td style="text-align: center; font-family: var(--font-mono); font-weight: 700; font-size: 0.85rem;">
                {{ po.items_count || 0 }} items
              </td>
              <td style="font-size: 0.825rem; color: var(--text-secondary);">
                {{ po.expected_delivery_date || '—' }}
              </td>
              <td>
                <span
                  class="badge"
                  :style="getStatusBadgeStyle(po.status)"
                >
                  <span class="badge-dot" :style="{ background: getStatusDotColor(po.status) }"></span>
                  {{ formatStatus(po.status) }}
                </span>
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 800; font-size: 1rem; color: var(--text-primary);">
                {{ formatMoney(po.total_minor) }}
              </td>
              <td style="text-align: center;">
                <Link
                  :href="`/purchases/${po.id}`"
                  class="btn btn-secondary"
                  style="padding: 0.35rem 0.65rem; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 0.35rem;"
                >
                  <span>Inspect &amp; GRN</span>
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
      <div v-if="purchaseOrders.links && purchaseOrders.links.length > 3" style="display: flex; justify-content: flex-end; padding: 1rem; gap: 0.35rem; border-top: 1px solid var(--border-color);">
        <Link
          v-for="(link, idx) in purchaseOrders.links"
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

interface SupplierOption {
  id: string
  name: string
}

interface PurchaseOrderItem {
  id: string
  po_number: string
  status: string
  created_at: string
  expected_delivery_date: string | null
  total_minor: number
  items_count: number
  supplier?: {
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
  purchaseOrders: {
    data: PurchaseOrderItem[]
    links: PaginationLink[]
  }
  filters: {
    status?: string
    supplier_id?: string
    q?: string
  }
  metrics: {
    total_pos: number
    pending_receipt_count: number
    total_purchased_minor: number
  }
  suppliers: SupplierOption[]
}>()

const searchQuery = ref(props.filters.q || '')
const selectedStatus = ref(props.filters.status || '')
const selectedSupplier = ref(props.filters.supplier_id || '')

function applyFilters() {
  router.get('/purchases', {
    q: searchQuery.value || undefined,
    status: selectedStatus.value || undefined,
    supplier_id: selectedSupplier.value || undefined,
  }, { preserveState: true, replace: true })
}

function clearFilters() {
  searchQuery.value = ''
  selectedStatus.value = ''
  selectedSupplier.value = ''
  router.get('/purchases', {}, { preserveState: true, replace: true })
}

function formatMoney(amountMinor: number): string {
  const rs = (amountMinor / 100).toLocaleString('en-PK', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  })
  return `Rs. ${rs}`
}

function formatDate(isoStr: string | null): string {
  if (!isoStr) return '—'
  const d = new Date(isoStr)
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatStatus(status: string): string {
  switch (status) {
    case 'draft': return 'Draft'
    case 'ordered': return 'Ordered (Pending)'
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
