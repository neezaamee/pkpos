<template>
  <SuperAdminLayout title="24-Hour Provisional Receipt Compliance Tracker">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <Link href="/superadmin/compliance/fbr" class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem;">
            &larr; Compliance Center
          </Link>
          <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0;">24-Hour Provisional Receipt Tracker</h2>
        </div>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.35rem;">
          Under FBR Digital Invoicing Statutory Rules, offline provisional receipts must receive a confirmed FBR USIN within 24 hours.
        </p>
      </div>

      <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <!-- Filter Pills for Quick Switching -->
        <button
          @click="setUrgencyFilter('all')"
          class="btn"
          :class="currentUrgency === 'all' ? 'btn-primary' : 'btn-secondary'"
          style="padding: 0.4rem 0.75rem; font-size: 0.78rem;"
        >
          All Pending
        </button>
        <button
          @click="setUrgencyFilter('overdue')"
          class="btn"
          :class="currentUrgency === 'overdue' ? 'btn-primary' : 'btn-secondary'"
          style="padding: 0.4rem 0.75rem; font-size: 0.78rem; color: #fb7185; border-color: rgba(244, 63, 94, 0.4);"
        >
          Overdue (&gt;24h)
        </button>
        <button
          @click="setUrgencyFilter('urgent')"
          class="btn"
          :class="currentUrgency === 'urgent' ? 'btn-primary' : 'btn-secondary'"
          style="padding: 0.4rem 0.75rem; font-size: 0.78rem; color: #fbbf24; border-color: rgba(245, 158, 11, 0.4);"
        >
          Expiring Soon (18-24h)
        </button>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="card-table" style="padding: 1.25rem; margin-bottom: 1.5rem;">
      <form @submit.prevent="applyFilters" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Search Sale #</label>
          <input
            v-model="filterForm.search"
            type="text"
            class="form-input"
            placeholder="e.g. SAL-1004"
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

    <!-- Provisional Sales Table -->
    <div class="card-table">
      <div class="table-header">
        <h2>Unfiscalized Sales Tracker ({{ sales.total }})</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">Showing {{ sales.from || 0 }} to {{ sales.to || 0 }}</span>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Invoice #</th>
              <th>Tenant / Branch</th>
              <th>Total (PKR)</th>
              <th>Status</th>
              <th>Age (Hours)</th>
              <th>FBR Legal Deadline</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="sales.data.length === 0">
              <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2.5rem 1rem;">
                No pending or overdue provisional receipts found. Outstanding fiscalization is 100% compliant!
              </td>
            </tr>
            <tr v-for="sale in sales.data" :key="sale.id">
              <!-- Sale Number -->
              <td style="font-family: monospace; font-weight: 700; font-size: 0.9rem; color: #38bdf8;">
                {{ sale.sale_number }}
              </td>

              <!-- Tenant & Branch -->
              <td>
                <div v-if="sale.tenant">
                  <div style="font-weight: 600; font-size: 0.85rem;">{{ sale.tenant.name }}</div>
                  <div style="font-size: 0.75rem; color: var(--text-muted);">
                    {{ sale.location?.name || 'Main Branch' }} • {{ sale.tenant.province.toUpperCase() }}
                  </div>
                </div>
                <span v-else>—</span>
              </td>

              <!-- Total -->
              <td style="font-weight: 600; font-size: 0.88rem;">
                Rs {{ (sale.total_minor / 100).toLocaleString('en-PK', { minimumFractionDigits: 2 }) }}
              </td>

              <!-- Status -->
              <td>
                <span
                  class="badge"
                  :style="sale.fbr_status === 'failed' ? 'background: rgba(244, 63, 94, 0.15); color: #fb7185;' : 'background: rgba(245, 158, 11, 0.15); color: #fbbf24;'"
                  style="text-transform: capitalize; font-size: 0.72rem;"
                >
                  {{ sale.fbr_status }}
                </span>
              </td>

              <!-- Age -->
              <td style="font-size: 0.85rem; font-weight: 600;">
                {{ sale.hours_age }} hrs
              </td>

              <!-- Legal Deadline Badge -->
              <td>
                <span
                  v-if="sale.urgency_status === 'overdue'"
                  class="badge"
                  style="background: rgba(225, 29, 72, 0.2); color: #f43f5e; border: 1px solid rgba(225, 29, 72, 0.4); font-size: 0.75rem; font-weight: 700;"
                >
                  OVERDUE ({{ sale.hours_overdue }}h over limit)
                </span>
                <span
                  v-else-if="sale.urgency_status === 'urgent'"
                  class="badge"
                  style="background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); font-size: 0.75rem; font-weight: 700;"
                >
                  URGENT ({{ sale.hours_remaining }}h remaining)
                </span>
                <span
                  v-else
                  class="badge"
                  style="background: rgba(6, 182, 212, 0.15); color: #22d3ee; font-size: 0.75rem;"
                >
                  {{ sale.hours_remaining }}h remaining
                </span>
              </td>

              <!-- Action -->
              <td style="text-align: right;">
                <Link
                  v-if="sale.tenant"
                  :href="`/superadmin/tenants/${sale.tenant.id}`"
                  class="btn btn-secondary"
                  style="padding: 0.25rem 0.55rem; font-size: 0.72rem;"
                >
                  Tenant Info
                </Link>
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

const currentUrgency = ref(props.filters.urgency || 'all');
const filterForm = ref({
  search: props.filters.search || '',
  tenant_id: props.filters.tenant_id || '',
  urgency: props.filters.urgency || 'all',
});

function applyFilters() {
  filterForm.value.urgency = currentUrgency.value;
  router.get('/superadmin/compliance/fbr/provisional', filterForm.value, {
    preserveState: true,
    preserveScroll: true,
  });
}

function setUrgencyFilter(val) {
  currentUrgency.value = val;
  applyFilters();
}

function resetFilters() {
  currentUrgency.value = 'all';
  filterForm.value = {
    search: '',
    tenant_id: '',
    urgency: 'all',
  };
  applyFilters();
}
</script>

