<template>
  <AdminLayout title="Suppliers & Accounts Payable (Khata)">
    <!-- Top Accounts Payable KPI Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(249, 115, 22, 0.06) 100%); border: 1px solid rgba(239, 68, 68, 0.25);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #ef4444; letter-spacing: 0.05em;">
              Total Payables (Vendor Khata)
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ formatMoney(metrics.total_payables_minor) }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
              Outstanding bills owed to distributors &amp; suppliers
            </div>
          </div>
          <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(239, 68, 68, 0.15); color: #ef4444; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"></path>
              <path d="M4 6v12c0 1.1.9 2 2 2h14v-4"></path>
              <path d="M18 12a2 2 0 0 0-2 2c0 1.1.9 2 2 2h4v-4h-4z"></path>
            </svg>
          </div>
        </div>
      </div>

      <div class="card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(217, 119, 6, 0.06) 100%); border: 1px solid rgba(245, 158, 11, 0.25);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #f59e0b; letter-spacing: 0.05em;">
              Active Creditors
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ metrics.creditors_count }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
              Vendors with pending payable balances
            </div>
          </div>
          <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
          </div>
        </div>
      </div>

      <div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(5, 150, 105, 0.06) 100%); border: 1px solid rgba(16, 185, 129, 0.25);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #10b981; letter-spacing: 0.05em;">
              Registered Suppliers
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ metrics.total_suppliers }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
              Total vendor and distributor accounts
            </div>
          </div>
          <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <path d="M16 10a4 4 0 0 1-8 0"></path>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Directory Table Card -->
    <div class="card-table">
      <div class="table-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
          <h2>Supplier Directory &amp; Vendor Accounts</h2>
          <div style="font-size: 0.825rem; color: var(--text-muted);">
            Manage wholesale suppliers, statutory NTN / STRN details, credit terms, and payable balances
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <form @submit.prevent="applySearch" style="display: flex; align-items: center; gap: 0.5rem;">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search name, phone, NTN..."
              class="form-control"
              style="width: 250px; font-size: 0.875rem;"
            />
            <button type="submit" class="btn btn-secondary" style="padding: 0.45rem 0.75rem;">
              Search
            </button>
            <button
              v-if="filters.q"
              type="button"
              class="btn btn-secondary"
              style="padding: 0.45rem 0.75rem;"
              @click="clearSearch"
            >
              Clear
            </button>
          </form>
          <Link href="/suppliers/create" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.4rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Register Supplier</span>
          </Link>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="table">
          <thead>
            <tr>
              <th>Supplier / Company</th>
              <th>Contact Person &amp; Phone</th>
              <th>City / Address</th>
              <th>NTN / STRN</th>
              <th>Payment Terms</th>
              <th style="text-align: right;">Payable Balance</th>
              <th style="text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="suppliers.data.length === 0">
              <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                <div style="font-size: 1rem; font-weight: 600;">No suppliers found</div>
                <div style="font-size: 0.825rem; margin-top: 0.25rem;">Register your first vendor or adjust your search filter</div>
              </td>
            </tr>
            <tr v-for="supplier in suppliers.data" :key="supplier.id">
              <td>
                <div style="font-weight: 700; color: var(--text-primary);">
                  {{ supplier.name }}
                </div>
                <div v-if="supplier.email" style="font-size: 0.75rem; color: var(--text-muted);">
                  {{ supplier.email }}
                </div>
              </td>
              <td>
                <div style="font-weight: 600; font-family: var(--font-mono); color: var(--text-secondary);">
                  {{ supplier.phone }}
                </div>
                <div v-if="supplier.contact_person" style="font-size: 0.75rem; color: var(--text-muted);">
                  {{ supplier.contact_person }}
                </div>
              </td>
              <td>
                <div style="font-size: 0.825rem; color: var(--text-primary);">
                  {{ supplier.city || '—' }}
                </div>
                <div v-if="supplier.address" style="font-size: 0.75rem; color: var(--text-muted); max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                  {{ supplier.address }}
                </div>
              </td>
              <td>
                <div v-if="supplier.ntn" style="font-size: 0.75rem; font-family: var(--font-mono); font-weight: 600; color: var(--text-secondary);">
                  NTN: {{ supplier.ntn }}
                </div>
                <div v-if="supplier.strn" style="font-size: 0.75rem; font-family: var(--font-mono); color: var(--text-muted);">
                  STRN: {{ supplier.strn }}
                </div>
                <span v-if="!supplier.ntn && !supplier.strn" style="font-size: 0.75rem; color: var(--text-muted);">—</span>
              </td>
              <td>
                <span v-if="supplier.payment_terms_days === 0" class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                  Cash / Immediate
                </span>
                <span v-else class="badge" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                  Net {{ supplier.payment_terms_days }} Days
                </span>
              </td>
              <td style="text-align: right;">
                <div
                  style="font-family: var(--font-mono); font-weight: 800; font-size: 1rem;"
                  :style="{ color: supplier.current_balance_minor > 0 ? '#ef4444' : '#10b981' }"
                >
                  {{ formatMoney(supplier.current_balance_minor) }}
                </div>
                <div v-if="supplier.current_balance_minor > 0" style="font-size: 0.72rem; color: #ef4444; font-weight: 600;">
                  Payable
                </div>
                <div v-else style="font-size: 0.72rem; color: #10b981;">
                  Settled
                </div>
              </td>
              <td style="text-align: center;">
                <Link
                  :href="`/suppliers/${supplier.id}`"
                  class="btn btn-secondary"
                  style="padding: 0.35rem 0.65rem; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 0.35rem;"
                >
                  <span>Statement &amp; Khata</span>
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
      <div v-if="suppliers.links && suppliers.links.length > 3" style="display: flex; justify-content: flex-end; padding: 1rem; gap: 0.35rem; border-top: 1px solid var(--border-color);">
        <Link
          v-for="(link, idx) in suppliers.links"
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

interface SupplierItem {
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
  is_active: boolean
}

interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

const props = defineProps<{
  suppliers: {
    data: SupplierItem[]
    links: PaginationLink[]
  }
  filters: {
    q?: string
  }
  metrics: {
    total_payables_minor: number
    creditors_count: number
    total_suppliers: number
  }
}>()

const searchQuery = ref(props.filters.q || '')

function applySearch() {
  router.get('/suppliers', { q: searchQuery.value }, { preserveState: true, replace: true })
}

function clearSearch() {
  searchQuery.value = ''
  router.get('/suppliers', {}, { preserveState: true, replace: true })
}

function formatMoney(amountMinor: number): string {
  const rs = (amountMinor / 100).toLocaleString('en-PK', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  })
  return `Rs. ${rs}`
}
</script>
