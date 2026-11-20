<template>
  <AdminLayout title="Wholesale Price Slabs & Quantity Tiers">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
          <span>⚖️</span> Wholesale Tiered Pricing
        </h1>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
          Configure quantity slab discounts for bulk building materials (e.g. Cement, Steel Saria, Bricks, Tiles).
        </p>
      </div>

      <div style="display: flex; gap: 0.5rem;">
        <button @click="showCreateModal = true" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          <span>Add Price Slab</span>
        </button>
      </div>
    </div>

    <!-- Filter by Product -->
    <div class="card" style="margin-bottom: 1.25rem; padding: 1rem;">
      <div style="max-width: 400px;">
        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">Filter by Product</label>
        <select v-model="selectedProductId" @change="onProductFilter" class="form-control" style="width: 100%;">
          <option value="">All Products</option>
          <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} (Base: Rs. {{ (p.price_minor / 100).toFixed(0) }})</option>
        </select>
      </div>
    </div>

    <!-- Tiers Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
      <div class="table-responsive">
        <table class="table" style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="border-bottom: 1px solid var(--border-color); text-align: left; background: var(--bg-surface-soft);">
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Product</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Quantity Slab Range</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Tier Label</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Wholesale Slab Price</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!tiers.data || tiers.data.length === 0">
              <td colspan="5" style="padding: 2.5rem; text-align: center; color: var(--text-muted);">
                No wholesale pricing slabs configured yet. Click "Add Price Slab" to configure bulk quantity discounts.
              </td>
            </tr>
            <tr v-for="t in tiers.data" :key="t.id" style="border-bottom: 1px solid var(--border-color);">
              <td style="padding: 0.75rem 1rem;">
                <div style="font-weight: 700; color: var(--text-primary);">{{ t.product?.name || '—' }}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                  Standard Price: Rs. {{ ((t.product?.price_minor || 0) / 100).toLocaleString('en-PK') }}
                </div>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <span style="font-family: var(--font-mono); font-weight: 700; color: #f97316;">
                  {{ t.min_quantity }} - {{ t.max_quantity ? t.max_quantity : 'Unlimited' }}
                </span>
                <span v-if="t.product?.uom" style="font-size: 0.75rem; color: var(--text-muted); margin-left: 0.25rem;">
                  ({{ t.product.uom }})
                </span>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <span style="font-size: 0.85rem; font-weight: 600;">{{ t.tier_label || 'Wholesale Slab' }}</span>
              </td>
              <td style="padding: 0.75rem 1rem; font-weight: 700; font-family: var(--font-mono); color: #10b981; font-size: 1rem;">
                Rs. {{ (t.unit_price_minor / 100).toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) }}
              </td>
              <td style="padding: 0.75rem 1rem; text-align: right;">
                <button @click="deleteTier(t.id)" class="btn btn-sm btn-outline" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.3); font-size: 0.75rem;">
                  Remove
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="tiers.links && tiers.links.length > 3" style="padding: 1rem; display: flex; justify-content: flex-end; gap: 0.35rem;">
        <Link
          v-for="(link, i) in tiers.links"
          :key="i"
          :href="link.url || '#'"
          v-html="link.label"
          class="btn btn-outline"
          :class="{ 'active': link.active, 'disabled': !link.url }"
          style="padding: 0.25rem 0.65rem; font-size: 0.75rem;"
        />
      </div>
    </div>

    <!-- Create Tier Modal -->
    <div v-if="showCreateModal" class="modal-overlay" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
      <div class="card" style="width: 100%; max-width: 520px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
          <h2 style="font-size: 1.2rem; font-weight: 800; color: var(--text-primary); margin: 0;">Add Wholesale Price Slab</h2>
          <button @click="showCreateModal = false" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>

        <form @submit.prevent="submitTier">
          <div style="margin-bottom: 1rem;">
            <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-secondary);">Select Product *</label>
            <select v-model="form.product_id" class="form-control" required style="width: 100%;">
              <option value="" disabled>Choose Product</option>
              <option v-for="p in products" :key="p.id" :value="p.id">
                {{ p.name }} (Retail: Rs. {{ (p.price_minor / 100).toFixed(0) }})
              </option>
            </select>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-secondary);">Min Quantity *</label>
              <input v-model.number="form.min_quantity" type="number" step="any" min="0.01" class="form-control" placeholder="e.g. 21" required style="width: 100%;" />
            </div>
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-secondary);">Max Quantity (leave blank for unlimited)</label>
              <input v-model.number="form.max_quantity" type="number" step="any" min="0.01" class="form-control" placeholder="e.g. 100" style="width: 100%;" />
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-secondary);">Slab Unit Price (Rs.) *</label>
              <input v-model.number="priceInRupees" type="number" step="any" min="0" class="form-control" placeholder="e.g. 1350" required style="width: 100%;" />
            </div>
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-secondary);">Tier Label / Description</label>
              <input v-model="form.tier_label" type="text" class="form-control" placeholder="e.g. 21-100 Bags Slab" style="width: 100%;" />
            </div>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.25rem;">
            <button type="button" @click="showCreateModal = false" class="btn btn-outline">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">Save Price Slab</button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  tiers: Object,
  products: Array,
  filters: Object,
});

const showCreateModal = ref(false);
const selectedProductId = ref(props.filters?.product_id || '');
const priceInRupees = ref(0);

const form = useForm({
  product_id: '',
  min_quantity: 1,
  max_quantity: null,
  unit_price_minor: 0,
  tier_label: '',
});

function onProductFilter() {
  router.get('/hardware/tiers', { product_id: selectedProductId.value || undefined }, { preserveState: true, replace: true });
}

function submitTier() {
  form.unit_price_minor = Math.round(Number(priceInRupees.value) * 100);
  form.post('/hardware/tiers', {
    onSuccess: () => {
      showCreateModal.value = false;
      form.reset();
      priceInRupees.value = 0;
    },
  });
}

function deleteTier(id) {
  if (confirm('Are you sure you want to delete this price tier?')) {
    router.delete(`/hardware/tiers/${id}`);
  }
}
</script>
