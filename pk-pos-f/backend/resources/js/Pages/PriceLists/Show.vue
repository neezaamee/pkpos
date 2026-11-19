<template>
  <AdminLayout :title="`Price List: ${priceList.name}`">
    <!-- Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <Link href="/price-lists" class="btn btn-outline" style="padding: 0.4rem 0.65rem;" title="Back to Price Lists">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
        </Link>
        <div>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <h1 style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); margin: 0;">
              {{ priceList.name }}
            </h1>
            <span
              v-if="priceList.is_default"
              class="badge"
              style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700;"
            >
              Default Retail
            </span>
          </div>
          <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
            Code: <strong style="font-family: var(--font-mono); color: #818cf8;">{{ priceList.code }}</strong>
            <span v-if="priceList.description">&bull; {{ priceList.description }}</span>
          </div>
        </div>
      </div>

      <button
        type="button"
        class="btn btn-primary"
        style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 1rem;"
        @click="showAddModal = true"
      >
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        <span>+ Add Tier Rule</span>
      </button>
    </div>

    <!-- Tiers Table Card -->
    <div class="card-table">
      <div class="table-header">
        <div>
          <h2>Product Volume Tiers &amp; Custom Rates</h2>
          <div style="font-size: 0.825rem; color: var(--text-muted);">
            Products qualify for these rates when purchased by customers assigned to this list or exceeding volume thresholds
          </div>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Product</th>
              <th style="text-align: right;">Base Retail Price</th>
              <th style="text-align: center;">Min. Volume Threshold</th>
              <th style="text-align: right;">Tier Price (PKR)</th>
              <th style="text-align: right;">Customer Savings</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items.data" :key="item.id">
              <td>
                <div style="font-weight: 600; color: var(--text-primary);">
                  {{ item.product?.name }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono);">
                  SKU: {{ item.product?.sku || '—' }} &bull; Unit: {{ item.product?.unit || 'pcs' }}
                </div>
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-muted);">
                {{ formatMoney(item.product?.price_minor) }}
              </td>
              <td style="text-align: center;">
                <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: #818cf8; font-weight: 700;">
                  &ge; {{ Number(item.min_quantity) }} {{ item.product?.unit || 'pcs' }}
                </span>
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 700; color: #10b981; font-size: 0.95rem;">
                {{ formatMoney(item.price_minor) }}
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-size: 0.825rem;">
                <span v-if="item.product && item.price_minor < item.product.price_minor" style="color: #34d399; font-weight: 600;">
                  -{{ formatMoney(item.product.price_minor - item.price_minor) }}
                  ({{ Math.round(((item.product.price_minor - item.price_minor) / item.product.price_minor) * 100) }}% off)
                </span>
                <span v-else style="color: var(--text-muted);">
                  Standard Rate
                </span>
              </td>
              <td style="text-align: right;">
                <button
                  type="button"
                  class="btn btn-outline"
                  style="padding: 0.25rem 0.6rem; font-size: 0.75rem; color: #ef4444; border-color: rgba(239, 68, 68, 0.3);"
                  @click="deleteItem(item.id)"
                >
                  Remove
                </button>
              </td>
            </tr>

            <tr v-if="items.data.length === 0">
              <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                No product tier rules assigned to this price list yet. Click "+ Add Tier Rule" above to specify custom rates.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="items.links && items.links.length > 3" style="padding: 1rem; display: flex; justify-content: flex-end; gap: 0.35rem;">
        <Link
          v-for="(link, i) in items.links"
          :key="i"
          :href="link.url || '#'"
          v-html="link.label"
          class="btn btn-outline"
          :class="{ 'active': link.active, 'disabled': !link.url }"
          style="padding: 0.25rem 0.65rem; font-size: 0.75rem;"
        />
      </div>
    </div>

    <!-- Modal: Add Product Tier Rule -->
    <div
      v-if="showAddModal"
      class="modal-backdrop"
      style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 1rem;"
      @click.self="showAddModal = false"
    >
      <div class="card" style="width: 100%; max-width: 480px; padding: 1.75rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
          <div>
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0;">
              Add Product Tier Rule
            </h3>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
              Target List: {{ priceList.name }}
            </div>
          </div>
          <button
            type="button"
            class="modal-close"
            style="background: transparent; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;"
            @click="showAddModal = false"
          >
            &times;
          </button>
        </div>

        <form @submit.prevent="submitItem">
          <div style="margin-bottom: 1rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Select Product <span style="color: #ef4444;">*</span>
            </label>
            <select v-model="form.product_id" class="form-control" required @change="onProductSelect">
              <option value="">-- Choose Product --</option>
              <option v-for="p in products" :key="p.id" :value="p.id">
                {{ p.name }} (Retail: Rs. {{ p.price_minor / 100 }})
              </option>
            </select>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.5rem;">
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Min Volume (Units) <span style="color: #ef4444;">*</span>
              </label>
              <input
                v-model.number="form.min_quantity"
                type="number"
                min="0.001"
                step="1"
                class="form-control"
                placeholder="1"
                required
              />
              <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
                e.g. 1 for all sales, or 10+ for bulk wholesale
              </div>
            </div>

            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Tier Price (PKR) <span style="color: #ef4444;">*</span>
              </label>
              <input
                v-model.number="tierPricePkr"
                type="number"
                min="0"
                step="1"
                class="form-control"
                placeholder="0"
                required
              />
              <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
                Special unit price
              </div>
            </div>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" class="btn btn-outline" @click="showAddModal = false">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">
              Save Tier Rule
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  priceList: {
    type: Object,
    required: true,
  },
  items: {
    type: Object,
    required: true,
  },
  products: {
    type: Array,
    required: true,
  },
});

const showAddModal = ref(false);
const tierPricePkr = ref(0);

const form = useForm({
  product_id: '',
  min_quantity: 1,
  price_minor: 0,
});

watch(tierPricePkr, (val) => {
  form.price_minor = Math.round((val || 0) * 100);
});

function onProductSelect() {
  const p = props.products.find((prod) => prod.id === form.product_id);
  if (p && tierPricePkr.value === 0) {
    tierPricePkr.value = p.price_minor / 100;
  }
}

function submitItem() {
  form.post(`/price-lists/${props.priceList.id}/items`, {
    onSuccess: () => {
      showAddModal.value = false;
      form.reset();
      tierPricePkr.value = 0;
    },
  });
}

function deleteItem(itemId) {
  if (confirm('Are you sure you want to remove this tier rule?')) {
    router.delete(`/price-lists/${props.priceList.id}/items/${itemId}`);
  }
}

function formatMoney(minor) {
  if (minor === undefined || minor === null) return 'Rs. 0';
  const val = minor / 100;
  return 'Rs. ' + val.toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
</script>
