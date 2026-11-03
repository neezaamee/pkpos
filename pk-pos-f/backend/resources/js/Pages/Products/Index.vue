<template>
  <AdminLayout title="Product Catalog">
    <div class="card-table">
      <div class="table-header" style="flex-wrap: wrap; gap: 1rem;">
        <div>
          <h2>Products & Items</h2>
          <div style="font-size: 0.8rem; color: var(--text-muted);">
            Manage store catalog, PCT HS codes, and standard pricing
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <form @submit.prevent="search" style="display: flex; gap: 0.5rem;">
            <input
              v-model="searchQuery"
              type="text"
              class="form-input"
              style="padding: 0.45rem 0.85rem; font-size: 0.85rem; width: 220px;"
              placeholder="Search by name, SKU, barcode..."
            />
            <button type="submit" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.85rem;">
              Search
            </button>
          </form>

          <Link href="/products/create" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
            + New Product
          </Link>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Product Name</th>
              <th>HS Code</th>
              <th>Unit</th>
              <th>Price</th>
              <th>Tax Category</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in products.data" :key="product.id">
              <td style="font-weight: 600; color: var(--text-primary);">
                {{ product.name }}
                <div v-if="product.sku" style="font-size: 0.725rem; color: var(--text-muted); font-family: var(--font-mono);">
                  SKU: {{ product.sku }}
                </div>
              </td>
              <td style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--accent-cyan);">
                {{ product.hs_code }}
              </td>
              <td style="text-transform: capitalize;">
                {{ product.unit }}
              </td>
              <td style="font-weight: 700; color: var(--text-primary);">
                {{ formatMoney(product.price_minor) }}
              </td>
              <td>
                <span class="badge badge-neutral">
                  {{ product.tax_category }}
                </span>
              </td>
              <td>
                <span :class="['badge', product.is_active ? 'badge-submitted' : 'badge-failed']">
                  <span class="badge-dot"></span>
                  {{ product.is_active ? 'Active' : 'Disabled' }}
                </span>
              </td>
              <td>
                <Link
                  :href="`/products/${product.id}/edit`"
                  class="btn btn-secondary"
                  style="padding: 0.3rem 0.7rem; font-size: 0.75rem;"
                >
                  Edit
                </Link>
              </td>
            </tr>
            <tr v-if="products.data.length === 0">
              <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                No products found in catalog. Click "New Product" to add one.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="products.links && products.links.length > 3" class="pagination">
        <Link
          v-for="(link, index) in products.links"
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
  products: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const searchQuery = ref(props.filters?.search || '');

function search() {
  router.get('/products', { search: searchQuery.value }, { preserveState: true, replace: true });
}

function formatMoney(minor) {
  if (minor === undefined || minor === null) return 'Rs 0.00';
  const val = (minor / 100).toLocaleString('en-PK', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return `Rs ${val}`;
}
</script>
