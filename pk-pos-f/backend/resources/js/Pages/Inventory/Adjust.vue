<template>
  <AdminLayout title="Stock Adjustment">
    <div style="max-width: 650px; margin: 0 auto;">
      <div class="form-card" style="max-width: 100%;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
          <div>
            <h3 style="font-size: 1.2rem; font-weight: 700;">Manual Stock Count Adjustment</h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);">
              Set physical count; creates an audited adjustment movement with calculated delta
            </span>
          </div>
          <Link href="/inventory/levels" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">
            ← Back to Levels
          </Link>
        </div>

        <form @submit.prevent="submit">
          <!-- Location -->
          <div class="form-group">
            <label class="form-label" for="location_id">Store Location *</label>
            <select
              id="location_id"
              v-model="form.location_id"
              class="form-input"
              :class="{ 'is-invalid': form.errors.location_id }"
              required
            >
              <option value="" disabled>Select Location</option>
              <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                {{ loc.name }}
              </option>
            </select>
            <div v-if="form.errors.location_id" class="invalid-feedback">
              {{ form.errors.location_id }}
            </div>
          </div>

          <!-- Product -->
          <div class="form-group">
            <label class="form-label" for="product_id">Catalog Product *</label>
            <select
              id="product_id"
              v-model="form.product_id"
              class="form-input"
              :class="{ 'is-invalid': form.errors.product_id }"
              required
            >
              <option value="" disabled>Select Product</option>
              <option v-for="p in products" :key="p.id" :value="p.id">
                {{ p.name }} (HS: {{ p.hs_code }})
              </option>
            </select>
            <div v-if="form.errors.product_id" class="invalid-feedback">
              {{ form.errors.product_id }}
            </div>
          </div>

          <!-- New Quantity -->
          <div class="form-group">
            <label class="form-label" for="new_quantity">Verified Physical On-Hand Count *</label>
            <input
              id="new_quantity"
              v-model="form.new_quantity"
              type="number"
              class="form-input"
              :class="{ 'is-invalid': form.errors.new_quantity }"
              placeholder="e.g. 150"
              required
            />
            <span class="form-hint">
              The system computes the delta between this count and current stock level.
            </span>
            <div v-if="form.errors.new_quantity" class="invalid-feedback">
              {{ form.errors.new_quantity }}
            </div>
          </div>

          <!-- Note -->
          <div class="form-group">
            <label class="form-label" for="note">Audit / Adjustment Reason</label>
            <input
              id="note"
              v-model="form.note"
              type="text"
              class="form-input"
              :class="{ 'is-invalid': form.errors.note }"
              placeholder="e.g. Month-end audit reconciliation, breakage, spoilage"
            />
            <div v-if="form.errors.note" class="invalid-feedback">
              {{ form.errors.note }}
            </div>
          </div>

          <!-- Submit Buttons -->
          <div style="margin-top: 2rem; display: flex; align-items: center; justify-content: flex-end; gap: 1rem;">
            <Link href="/inventory/levels" class="btn btn-secondary">
              Cancel
            </Link>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="form.processing"
            >
              {{ form.processing ? 'Recording Adjustment...' : 'Confirm Adjustment' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  products: {
    type: Array,
    default: () => [],
  },
  locations: {
    type: Array,
    default: () => [],
  },
});

// URL search params pre-selection support
const params = new URLSearchParams(window.location.search);
const defaultProductId = params.get('product_id') || (props.products[0]?.id || '');
const defaultLocationId = params.get('location_id') || (props.locations[0]?.id || '');

const form = useForm({
  product_id: defaultProductId,
  location_id: defaultLocationId,
  new_quantity: '',
  note: '',
});

function submit() {
  form.post('/inventory/adjust');
}
</script>
