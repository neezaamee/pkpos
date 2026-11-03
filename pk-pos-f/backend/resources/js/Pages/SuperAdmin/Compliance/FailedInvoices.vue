<template>
  <SuperAdminLayout title="FBR Dead-Letter Queue (Failed Invoices)">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <Link href="/superadmin/compliance/fbr" class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem;">
            &larr; Compliance Center
          </Link>
          <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0;">Dead-Letter Queue (DLQ)</h2>
        </div>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.35rem;">
          Invoices rejected by FBR IRIS APIs across all tenant terminals. Reset status to re-queue for transmission.
        </p>
      </div>

      <div style="display: flex; gap: 0.75rem; align-items: center;">
        <button
          v-if="sales.total > 0"
          @click="openBulkRetryModal"
          class="btn btn-primary"
          style="background: linear-gradient(135deg, #10b981, #059669); display: flex; align-items: center; gap: 0.4rem;"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="23 4 23 10 17 10"></polyline>
            <polyline points="1 20 1 14 7 14"></polyline>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
          </svg>
          Bulk Retry All Failed Sales ({{ sales.total }})
        </button>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="card-table" style="padding: 1.25rem; margin-bottom: 1.5rem;">
      <form @submit.prevent="applyFilters" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Search Invoice # or Error</label>
          <input
            v-model="filterForm.search"
            type="text"
            class="form-input"
            placeholder="e.g. SAL-001 or Invalid Token"
          />
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Organization / Tenant</label>
          <select v-model="filterForm.tenant_id" class="form-input">
            <option value="">All Tenants</option>
            <option v-for="t in tenants" :key="t.id" :value="t.id">
              {{ t.name }}
            </option>
          </select>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
          <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.55rem 0.85rem;">
            Filter
          </button>
          <button type="button" @click="resetFilters" class="btn btn-secondary" style="padding: 0.55rem 0.85rem;">
            Reset
          </button>
        </div>
      </form>
    </div>

    <!-- DLQ Table -->
    <div class="card-table">
      <div class="table-header">
        <h2>Failed Sales Awaiting Resolution ({{ sales.total }})</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">Showing {{ sales.from || 0 }} to {{ sales.to || 0 }}</span>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Invoice #</th>
              <th>Tenant</th>
              <th>Total (PKR)</th>
              <th>Payment</th>
              <th>FBR Rejection Reason</th>
              <th>Created</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="sales.data.length === 0">
              <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2.5rem 1rem;">
                No failed sales currently in Dead-Letter Queue. All submissions are healthy!
              </td>
            </tr>
            <tr v-for="sale in sales.data" :key="sale.id">
              <!-- Sale Number -->
              <td style="font-family: monospace; font-weight: 700; font-size: 0.9rem; color: #38bdf8;">
                {{ sale.sale_number }}
              </td>

              <!-- Tenant -->
              <td>
                <div v-if="sale.tenant">
                  <div style="font-weight: 600; font-size: 0.85rem;">{{ sale.tenant.name }}</div>
                  <div style="font-size: 0.75rem; color: var(--text-muted);">NTN: {{ sale.tenant.ntn }}</div>
                </div>
                <span v-else>—</span>
              </td>

              <!-- Total -->
              <td style="font-weight: 600; font-size: 0.88rem;">
                Rs {{ (sale.total_minor / 100).toLocaleString('en-PK', { minimumFractionDigits: 2 }) }}
              </td>

              <!-- Payment Method -->
              <td>
                <span class="badge badge-neutral" style="text-transform: capitalize; font-size: 0.72rem;">
                  {{ sale.payment_method }}
                </span>
              </td>

              <!-- FBR Error -->
              <td>
                <div style="font-family: monospace; font-size: 0.78rem; color: #fb7185; background: rgba(244, 63, 94, 0.1); padding: 0.35rem 0.6rem; border-radius: 0.35rem; border: 1px solid rgba(244, 63, 94, 0.25); max-width: 320px; word-break: break-all;">
                  {{ sale.fbr_error }}
                </div>
              </td>

              <!-- Date -->
              <td style="white-space: nowrap; font-size: 0.8rem; color: var(--text-secondary);">
                {{ sale.created_at }}
              </td>

              <!-- Retry Action -->
              <td style="text-align: right;">
                <button
                  @click="retrySale(sale)"
                  class="btn btn-secondary"
                  style="padding: 0.3rem 0.65rem; font-size: 0.75rem; color: #34d399; border-color: rgba(16, 185, 129, 0.3);"
                  title="Reset to Pending to allow sync worker to re-transmit"
                >
                  Queue Retry
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="sales.links && sales.links.length > 3" class="pagination" style="padding: 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.35rem;">
        <Link
          v-for="(link, i) in sales.links"
          :key="i"
          :href="link.url || '#'"
          class="btn btn-secondary"
          :class="{ active: link.active, disabled: !link.url }"
          style="padding: 0.35rem 0.65rem; font-size: 0.78rem;"
          v-html="link.label"
        />
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  sales: {
    type: Object,
    required: true,
  },
  tenants: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const filterForm = ref({
  search: props.filters.search || '',
  tenant_id: props.filters.tenant_id || '',
});

function applyFilters() {
  router.get('/superadmin/compliance/fbr/failed', filterForm.value, {
    preserveState: true,
    preserveScroll: true,
  });
}

function resetFilters() {
  filterForm.value = {
    search: '',
    tenant_id: '',
  };
  applyFilters();
}

function retrySale(sale) {
  if (confirm(`Queue invoice "${sale.sale_number}" for FBR resubmission?`)) {
    router.post(`/superadmin/compliance/fbr/retry/${sale.id}`, {}, {
      preserveScroll: true,
    });
  }
}

function openBulkRetryModal() {
  const tenantFilter = filterForm.value.tenant_id ? 'selected tenant' : 'all tenants';
  if (confirm(`Are you sure you want to reset all ${props.sales.total} failed invoices for ${tenantFilter} back to Pending? This allows the desktop sync outbox to re-submit them.`)) {
    router.post('/superadmin/compliance/fbr/bulk-retry', {
      tenant_id: filterForm.value.tenant_id || null,
    }, {
      preserveScroll: true,
    });
  }
}
</script>

