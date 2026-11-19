<template>
  <AdminLayout title="Customer Directory & Khata (Udhaar) Ledger">
    <!-- Top Receivables & CRM Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(249, 115, 22, 0.06) 100%); border: 1px solid rgba(239, 68, 68, 0.25);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #ef4444; letter-spacing: 0.05em;">
              Total Receivables (Khata)
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ formatMoney(metrics.total_receivables_minor) }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
              Outstanding balance owed by customers
            </div>
          </div>
          <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(239, 68, 68, 0.15); color: #ef4444; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="5" width="20" height="14" rx="2"></rect>
              <line x1="2" y1="10" x2="22" y2="10"></line>
            </svg>
          </div>
        </div>
      </div>

      <div class="card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(217, 119, 6, 0.06) 100%); border: 1px solid rgba(245, 158, 11, 0.25);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #f59e0b; letter-spacing: 0.05em;">
              Active Debtors
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ metrics.debtors_count }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
              Accounts with pending credit balance
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

      <div class="card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(139, 92, 246, 0.06) 100%); border: 1px solid rgba(99, 102, 241, 0.25);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #6366f1; letter-spacing: 0.05em;">
              Registered Customers
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ metrics.total_customers }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
              Total retail &amp; wholesale profiles
            </div>
          </div>
          <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Directory Table Card -->
    <div class="card-table">
      <div class="table-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
          <h2>Customer Directory &amp; Khata Accounts</h2>
          <div style="font-size: 0.825rem; color: var(--text-muted);">
            Manage customer profiles, credit ceilings, CNIC/NTN compliance, and open receivables ledgers
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <form @submit.prevent="onSearch" style="position: relative; display: flex; align-items: center;">
            <input
              v-model="searchQuery"
              type="text"
              class="form-control"
              placeholder="Search by name, phone, CNIC..."
              style="padding-left: 2rem; width: 260px; font-size: 0.825rem;"
            />
            <svg
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              style="position: absolute; left: 0.65rem; color: var(--text-muted);"
            >
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </form>

          <Link
            href="/customers/create"
            class="btn btn-primary"
            style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.9rem;"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Register Customer</span>
          </Link>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Customer</th>
              <th>Phone</th>
              <th>City / Location</th>
              <th>CNIC / NTN</th>
              <th style="text-align: right;">Credit Limit</th>
              <th style="text-align: right;">Receivable Balance</th>
              <th style="text-align: center;">Khata Status</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in customers.data" :key="c.id">
              <td>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                  <div style="width: 34px; height: 34px; border-radius: 8px; background: rgba(99, 102, 241, 0.15); color: #818cf8; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                    {{ c.name.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <div style="font-weight: 600; color: var(--text-primary);">
                      {{ c.name }}
                    </div>
                    <div v-if="c.email" style="font-size: 0.75rem; color: var(--text-muted);">
                      {{ c.email }}
                    </div>
                  </div>
                </div>
              </td>
              <td style="font-family: var(--font-mono); font-size: 0.85rem;">
                {{ c.phone }}
              </td>
              <td style="color: var(--text-muted); font-size: 0.85rem;">
                {{ c.city || '—' }}
              </td>
              <td>
                <div v-if="c.cnic" style="display: flex; align-items: center; gap: 0.35rem;">
                  <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-size: 0.7rem;">
                    CNIC
                  </span>
                  <span style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-secondary);">
                    {{ c.cnic }}
                  </span>
                </div>
                <div v-else-if="c.ntn" style="display: flex; align-items: center; gap: 0.35rem;">
                  <span class="badge" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; font-size: 0.7rem;">
                    NTN
                  </span>
                  <span style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-secondary);">
                    {{ c.ntn }}
                  </span>
                </div>
                <span v-else style="color: var(--text-muted); font-size: 0.8rem;">
                  Unregistered
                </span>
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 600;">
                <span v-if="c.credit_limit_minor > 0" style="color: var(--text-secondary);">
                  {{ formatMoney(c.credit_limit_minor) }}
                </span>
                <span v-else style="color: var(--text-muted); font-size: 0.78rem;">
                  Cash Only (0)
                </span>
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 700;">
                <span v-if="c.current_balance_minor > 0" style="color: #ef4444;">
                  {{ formatMoney(c.current_balance_minor) }}
                </span>
                <span v-else style="color: #10b981;">
                  Rs. 0
                </span>
              </td>
              <td style="text-align: center;">
                <span
                  v-if="c.current_balance_minor > 0"
                  class="badge"
                  style="background: rgba(239, 68, 68, 0.15); color: #ef4444; font-weight: 600;"
                >
                  <span class="badge-dot" style="background: #ef4444;"></span>
                  Pending Due
                </span>
                <span
                  v-else
                  class="badge"
                  style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 600;"
                >
                  <span class="badge-dot" style="background: #10b981;"></span>
                  Clear
                </span>
              </td>
              <td style="text-align: right;">
                <Link
                  :href="`/customers/${c.id}`"
                  class="btn btn-outline"
                  style="padding: 0.25rem 0.65rem; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;"
                >
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                  </svg>
                  <span>Khata Ledger</span>
                </Link>
              </td>
            </tr>

            <tr v-if="customers.data.length === 0">
              <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                <div style="font-size: 1rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  No customers found
                </div>
                <div style="font-size: 0.825rem;">
                  Register your retail and wholesale customers to begin tracking credit accounts (Udhaar / Khata).
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="customers.links && customers.links.length > 3" style="padding: 1rem; display: flex; justify-content: flex-end; gap: 0.35rem;">
        <Link
          v-for="(link, i) in customers.links"
          :key="i"
          :href="link.url || '#'"
          v-html="link.label"
          class="btn btn-outline"
          :class="{ 'active': link.active, 'disabled': !link.url }"
          style="padding: 0.25rem 0.65rem; font-size: 0.75rem;"
        />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  customers: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({ q: '' }),
  },
  metrics: {
    type: Object,
    required: true,
  },
});

const searchQuery = ref(props.filters.q || '');

function onSearch() {
  router.get('/customers', { q: searchQuery.value }, { preserveState: true, replace: true });
}

function formatMoney(minor) {
  if (minor === undefined || minor === null) return 'Rs. 0';
  const val = minor / 100;
  return 'Rs. ' + val.toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
</script>
