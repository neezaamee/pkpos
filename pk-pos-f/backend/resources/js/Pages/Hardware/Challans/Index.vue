<template>
  <AdminLayout title="Delivery Challans & Gate Passes (Biltee)">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
          <span>🚛</span> Truck Delivery Challans &amp; Gate Passes
        </h1>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
          Manage dispatch biltees, vehicle numbers, driver contacts, gross weight, and receiver verification.
        </p>
      </div>
    </div>

    <!-- Status Filters Bar -->
    <div class="card" style="margin-bottom: 1.25rem; padding: 0.75rem 1rem;">
      <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
        <button
          @click="filterStatus('')"
          class="btn btn-sm"
          :class="!filters?.status ? 'btn-primary' : 'btn-outline'"
          style="font-size: 0.8rem; padding: 0.35rem 0.75rem;"
        >
          All Challans
        </button>
        <button
          @click="filterStatus('pending')"
          class="btn btn-sm"
          :class="filters?.status === 'pending' ? 'btn-primary' : 'btn-outline'"
          style="font-size: 0.8rem; padding: 0.35rem 0.75rem;"
        >
          ⏳ Pending Loading
        </button>
        <button
          @click="filterStatus('dispatched')"
          class="btn btn-sm"
          :class="filters?.status === 'dispatched' ? 'btn-primary' : 'btn-outline'"
          style="font-size: 0.8rem; padding: 0.35rem 0.75rem;"
        >
          🚚 On the Road (Dispatched)
        </button>
        <button
          @click="filterStatus('delivered')"
          class="btn btn-sm"
          :class="filters?.status === 'delivered' ? 'btn-primary' : 'btn-outline'"
          style="font-size: 0.8rem; padding: 0.35rem 0.75rem;"
        >
          ✅ Delivered at Site
        </button>
      </div>
    </div>

    <!-- Challans List Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
      <div class="table-responsive">
        <table class="table" style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="border-bottom: 1px solid var(--border-color); text-align: left; background: var(--bg-surface-soft);">
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Challan &amp; Gate Pass #</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Site Destination</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Vehicle &amp; Driver</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Freight Terms</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Status</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!challans.data || challans.data.length === 0">
              <td colspan="6" style="padding: 2.5rem; text-align: center; color: var(--text-muted);">
                No delivery challans recorded yet. Delivery challans can be issued directly during checkout in the POS.
              </td>
            </tr>
            <tr v-for="c in challans.data" :key="c.id" style="border-bottom: 1px solid var(--border-color);">
              <td style="padding: 0.75rem 1rem;">
                <div style="font-weight: 800; font-family: var(--font-mono); color: var(--text-primary); font-size: 0.9rem;">
                  {{ c.challan_number }}
                </div>
                <div style="font-size: 0.75rem; font-family: var(--font-mono); color: #f97316;">
                  Pass: {{ c.gate_pass_number }}
                </div>
                <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.2rem;">
                  {{ new Date(c.created_at).toLocaleString('en-PK', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) }}
                </div>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <div style="font-weight: 700; color: var(--text-primary);">
                  {{ c.site_destination || c.contractor_project?.name || 'Local Site' }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                  Contractor: {{ c.contractor_project?.customer?.name || 'Direct Customer' }}
                </div>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <div style="font-weight: 700; font-family: var(--font-mono); color: #3b82f6;">
                  {{ c.vehicle_number || 'N/A' }}
                </div>
                <div style="font-size: 0.8rem;">
                  {{ c.driver_name || 'Driver unassigned' }}
                </div>
                <div v-if="c.driver_phone" style="font-size: 0.75rem; color: var(--text-muted);">
                  {{ c.driver_phone }}
                </div>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <div style="font-size: 0.8rem; font-weight: 600;">
                  {{ c.freight_terms === 'paid_by_store' ? 'Paid by Store' : 'To-Pay (Customer)' }}
                </div>
                <div v-if="c.freight_amount_minor > 0" style="font-size: 0.75rem; font-family: var(--font-mono); color: var(--text-muted);">
                  Rs. {{ (c.freight_amount_minor / 100).toLocaleString('en-PK') }}
                </div>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <span
                  class="badge"
                  :style="{
                    background: c.status === 'delivered' ? 'rgba(16, 185, 129, 0.15)' : c.status === 'dispatched' ? 'rgba(59, 130, 246, 0.15)' : 'rgba(245, 158, 11, 0.15)',
                    color: c.status === 'delivered' ? '#10b981' : c.status === 'dispatched' ? '#3b82f6' : '#f59e0b',
                    padding: '0.2rem 0.5rem',
                    borderRadius: '4px',
                    fontSize: '0.75rem',
                    fontWeight: 700,
                    textTransform: 'uppercase'
                  }"
                >
                  {{ c.status }}
                </span>
              </td>
              <td style="padding: 0.75rem 1rem; text-align: right;">
                <div style="display: flex; justify-content: flex-end; gap: 0.35rem; align-items: center;">
                  <button
                    v-if="c.status === 'dispatched'"
                    @click="updateStatus(c.id, 'delivered')"
                    class="btn btn-sm btn-outline"
                    style="color: #10b981; border-color: rgba(16, 185, 129, 0.3); font-size: 0.75rem; padding: 0.25rem 0.5rem;"
                    title="Mark as delivered at site"
                  >
                    Mark Delivered
                  </button>
                  <Link :href="`/hardware/challans/${c.id}`" class="btn btn-sm btn-primary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">
                    Print Bilty &rarr;
                  </Link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="challans.links && challans.links.length > 3" style="padding: 1rem; display: flex; justify-content: flex-end; gap: 0.35rem;">
        <Link
          v-for="(link, i) in challans.links"
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
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  challans: Object,
  projects: Array,
  filters: Object,
});

function filterStatus(status) {
  router.get('/hardware/challans', { status: status || undefined }, { preserveState: true, replace: true });
}

function updateStatus(id, newStatus) {
  router.post(`/hardware/challans/${id}/status`, { status: newStatus });
}
</script>
