<template>
  <AdminLayout title="Tiered Price Lists">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
      <div>
        <h1 style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); margin: 0;">
          Tiered Price Lists &amp; Wholesale Rates
        </h1>
        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          Configure dynamic pricing schedules for Retail, Wholesale, B2B Distributors, and bulk quantity tiers
        </div>
      </div>

      <Link
        href="/price-lists/create"
        class="btn btn-primary"
        style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 1rem;"
      >
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        <span>+ Create Price List</span>
      </Link>
    </div>

    <div class="card-table">
      <div class="table-header">
        <div>
          <h2>Active Pricing Schedules</h2>
          <div style="font-size: 0.825rem; color: var(--text-muted);">
            Price lists can be assigned directly to customer profiles or selected during POS sale
          </div>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Price List Name</th>
              <th>Code</th>
              <th>Description</th>
              <th style="text-align: center;">Tiers &amp; Rules</th>
              <th style="text-align: center;">Default Retail</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="pl in priceLists.data" :key="pl.id">
              <td>
                <div style="font-weight: 700; color: var(--text-primary);">
                  {{ pl.name }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                  Currency: {{ pl.currency || 'PKR' }}
                </div>
              </td>
              <td style="font-family: var(--font-mono); font-size: 0.85rem; color: #818cf8;">
                {{ pl.code }}
              </td>
              <td style="color: var(--text-muted); font-size: 0.85rem;">
                {{ pl.description || '—' }}
              </td>
              <td style="text-align: center;">
                <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #818cf8; font-weight: 700;">
                  {{ pl.items_count }} products
                </span>
              </td>
              <td style="text-align: center;">
                <span
                  v-if="pl.is_default"
                  class="badge"
                  style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700;"
                >
                  <span class="badge-dot" style="background: #10b981;"></span>
                  Default Active
                </span>
                <span v-else style="color: var(--text-muted); font-size: 0.8rem;">
                  Special Tier
                </span>
              </td>
              <td style="text-align: right;">
                <Link
                  :href="`/price-lists/${pl.id}`"
                  class="btn btn-outline"
                  style="padding: 0.25rem 0.65rem; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;"
                >
                  <span>Manage Tiers</span>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
                </Link>
              </td>
            </tr>

            <tr v-if="priceLists.data.length === 0">
              <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                No custom price lists created yet. Click "+ Create Price List" to establish Wholesale or VIP pricing.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="priceLists.links && priceLists.links.length > 3" style="padding: 1rem; display: flex; justify-content: flex-end; gap: 0.35rem;">
        <Link
          v-for="(link, i) in priceLists.links"
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
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  priceLists: {
    type: Object,
    required: true,
  },
});
</script>
