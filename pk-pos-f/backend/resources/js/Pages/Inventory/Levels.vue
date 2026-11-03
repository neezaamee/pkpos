<template>
  <AdminLayout title="Inventory & Stock Levels">
    <div class="card-table">
      <div class="table-header" style="flex-wrap: wrap; gap: 1rem;">
        <div>
          <h2>Stock Levels Projection</h2>
          <div style="font-size: 0.8rem; color: var(--text-muted);">
            Real-time on-hand inventory across all tenant branch locations
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
          <!-- Filter by Location -->
          <select
            v-model="selectedLocation"
            class="form-input"
            style="padding: 0.45rem 0.85rem; font-size: 0.85rem; width: 170px;"
            @change="filter"
          >
            <option value="">All Locations</option>
            <option v-for="loc in locations" :key="loc.id" :value="loc.id">
              {{ loc.name }}
            </option>
          </select>

          <!-- Search Box -->
          <form @submit.prevent="filter" style="display: flex; gap: 0.5rem;">
            <input
              v-model="searchQuery"
              type="text"
              class="form-input"
              style="padding: 0.45rem 0.85rem; font-size: 0.85rem; width: 200px;"
              placeholder="Search product..."
            />
            <button type="submit" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.85rem;">
              Filter
            </button>
          </form>

          <!-- Actions -->
          <Link href="/inventory/adjust" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
            ± Adjust Stock
          </Link>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Product Name</th>
              <th>HS Code</th>
              <th>Location</th>
              <th>On Hand</th>
              <th>Stock Status</th>
              <th>Last Updated</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="level in levels.data"
              :key="level.id"
              :style="level.quantity_on_hand <= threshold ? 'background: rgba(245, 158, 11, 0.05);' : ''"
            >
              <td style="font-weight: 600; color: var(--text-primary);">
                {{ level.product?.name || 'Unknown Product' }}
                <div v-if="level.product?.sku" style="font-size: 0.725rem; color: var(--text-muted); font-family: var(--font-mono);">
                  SKU: {{ level.product.sku }}
                </div>
              </td>
              <td style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--accent-cyan);">
                {{ level.product?.hs_code || '—' }}
              </td>
              <td>{{ level.location?.name || 'Default Store' }}</td>
              <td style="font-size: 1.1rem; font-weight: 700; font-family: var(--font-mono);" :style="level.quantity_on_hand <= threshold ? 'color: var(--accent-amber);' : 'color: var(--badge-green-text);'">
                {{ level.quantity_on_hand }}
              </td>
              <td>
                <span v-if="level.quantity_on_hand <= 0" class="badge badge-failed">
                  <span class="badge-dot"></span> Out of Stock
                </span>
                <span v-else-if="level.quantity_on_hand <= threshold" class="badge badge-offline">
                  <span class="badge-dot"></span> Low Stock (≤ {{ threshold }})
                </span>
                <span v-else class="badge badge-submitted">
                  <span class="badge-dot"></span> In Stock
                </span>
              </td>
              <td style="font-size: 0.8rem; color: var(--text-muted);">
                {{ formatDate(level.updated_at) }}
              </td>
              <td>
                <Link
                  :href="`/inventory/adjust?product_id=${level.product_id}&location_id=${level.location_id}`"
                  class="btn btn-secondary"
                  style="padding: 0.25rem 0.6rem; font-size: 0.75rem;"
                >
                  Adjust
                </Link>
              </td>
            </tr>
            <tr v-if="levels.data.length === 0">
              <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                No stock levels found matching your criteria.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="levels.links && levels.links.length > 3" class="pagination">
        <Link
          v-for="(link, index) in levels.links"
          :key="index"
          :href="link.url || '#'"
          class="page-btn"
          :class="{ active: link.active, disabled: !link.url }"
          v-html="link.label"
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
  levels: {
    type: Object,
    required: true,
  },
  locations: {
    type: Array,
    default: () => [],
  },
  threshold: {
    type: Number,
    default: 10,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const selectedLocation = ref(props.filters?.location_id || '');
const searchQuery = ref(props.filters?.search || '');

function filter() {
  router.get('/inventory/levels', {
    location_id: selectedLocation.value || undefined,
    search: searchQuery.value || undefined,
  }, { preserveState: true, replace: true });
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>
