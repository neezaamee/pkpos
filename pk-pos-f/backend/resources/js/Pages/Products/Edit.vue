<template>
  <AdminLayout title="Edit Product">
    <div style="max-width: 760px; margin: 0 auto;">
      <div class="form-card" style="max-width: 100%;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
          <div>
            <h3 style="font-size: 1.2rem; font-weight: 700;">Edit Product: {{ product.name }}</h3>
            <span style="font-size: 0.8rem; color: var(--text-muted); font-family: var(--font-mono);">
              ID: {{ product.id }}
            </span>
          </div>
          <Link href="/products" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">
            ← Back to Catalog
          </Link>
        </div>

        <div v-if="hasSales" class="alert alert-warning" style="margin-bottom: 1.5rem;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
          <div>
            <strong>Historical FBR Immutability Guard:</strong> This product is referenced by completed invoices. The HS code cannot be modified.
          </div>
        </div>

        <form @submit.prevent="submit">
          <!-- Name -->
          <div class="form-group">
            <label class="form-label" for="name">Product Name *</label>
            <input
              id="name"
              v-model="form.name"
              type="text"
              class="form-input"
              :class="{ 'is-invalid': form.errors.name }"
              required
            />
            <div v-if="form.errors.name" class="invalid-feedback">
              {{ form.errors.name }}
            </div>
          </div>

          <!-- SKU & Barcode -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label class="form-label" for="sku">SKU Code</label>
              <input
                id="sku"
                v-model="form.sku"
                type="text"
                class="form-input"
                :class="{ 'is-invalid': form.errors.sku }"
              />
              <div v-if="form.errors.sku" class="invalid-feedback">
                {{ form.errors.sku }}
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="barcode">Barcode (UPC / EAN)</label>
              <input
                id="barcode"
                v-model="form.barcode"
                type="text"
                class="form-input"
                :class="{ 'is-invalid': form.errors.barcode }"
              />
              <div v-if="form.errors.barcode" class="invalid-feedback">
                {{ form.errors.barcode }}
              </div>
            </div>
          </div>

          <!-- HS Code & Unit -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label class="form-label" for="hs_code">HS Code (8-digit PCT) *</label>
              <input
                id="hs_code"
                v-model="form.hs_code"
                type="text"
                maxlength="8"
                class="form-input"
                :class="{ 'is-invalid': form.errors.hs_code || hsCodeClientError }"
                :disabled="hasSales"
                required
              />
              <span class="form-hint">
                {{ hasSales ? 'Locked because product is in historical sales.' : 'Must be exactly 8 digits.' }}
              </span>
              <div v-if="hsCodeClientError" class="invalid-feedback">
                {{ hsCodeClientError }}
              </div>
              <div v-if="form.errors.hs_code" class="invalid-feedback">
                {{ form.errors.hs_code }}
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="unit">Sales Unit *</label>
              <select
                id="unit"
                v-model="form.unit"
                class="form-input"
                :class="{ 'is-invalid': form.errors.unit }"
                required
              >
                <option value="piece">Piece (each)</option>
                <option value="kg">Kilogram (kg)</option>
                <option value="gram">Gram (g)</option>
                <option value="litre">Litre (L)</option>
                <option value="ml">Millilitre (ml)</option>
                <option value="hour">Hour (hr)</option>
                <option value="session">Session</option>
              </select>
              <div v-if="form.errors.unit" class="invalid-feedback">
                {{ form.errors.unit }}
              </div>
            </div>
          </div>

          <!-- Price & Cost -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label class="form-label" for="price_in_pkr">Selling Price (PKR) *</label>
              <input
                id="price_in_pkr"
                v-model="form.price_in_pkr"
                type="number"
                step="0.01"
                min="0"
                class="form-input"
                :class="{ 'is-invalid': form.errors.price_in_pkr }"
                required
              />
              <div v-if="form.errors.price_in_pkr" class="invalid-feedback">
                {{ form.errors.price_in_pkr }}
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="cost_in_pkr">Cost Price (PKR)</label>
              <input
                id="cost_in_pkr"
                v-model="form.cost_in_pkr"
                type="number"
                step="0.01"
                min="0"
                class="form-input"
                :class="{ 'is-invalid': form.errors.cost_in_pkr }"
              />
              <div v-if="form.errors.cost_in_pkr" class="invalid-feedback">
                {{ form.errors.cost_in_pkr }}
              </div>
            </div>
          </div>

          <!-- Tax Category -->
          <div class="form-group">
            <label class="form-label" for="tax_category">Tax Category *</label>
            <input
              id="tax_category"
              v-model="form.tax_category"
              type="text"
              class="form-input"
              :class="{ 'is-invalid': form.errors.tax_category }"
              required
            />
            <div v-if="form.errors.tax_category" class="invalid-feedback">
              {{ form.errors.tax_category }}
            </div>
          </div>

          <!-- Is Active Toggle -->
          <div class="form-group" style="display: flex; align-items: center; gap: 0.75rem; margin-top: 1rem;">
            <input
              id="is_active"
              v-model="form.is_active"
              type="checkbox"
              style="accent-color: var(--accent-primary); width: 18px; height: 18px;"
            />
            <label for="is_active" style="font-size: 0.9rem; font-weight: 600; cursor: pointer;">
              Active in POS Catalog
            </label>
          </div>

          <!-- Submit Buttons -->
          <div style="margin-top: 2rem; display: flex; align-items: center; justify-content: flex-end; gap: 1rem;">
            <Link href="/products" class="btn btn-secondary">
              Cancel
            </Link>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="form.processing || !!hsCodeClientError"
            >
              {{ form.processing ? 'Updating...' : 'Save Changes' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  product: {
    type: Object,
    required: true,
  },
  hasSales: {
    type: Boolean,
    default: false,
  },
});

const form = useForm({
  name: props.product.name,
  sku: props.product.sku || '',
  barcode: props.product.barcode || '',
  hs_code: props.product.hs_code,
  unit: props.product.unit,
  price_in_pkr: props.product.price_in_pkr,
  cost_in_pkr: props.product.cost_in_pkr || '',
  tax_category: props.product.tax_category,
  is_active: props.product.is_active,
});

const hsCodeClientError = computed(() => {
  if (!form.hs_code) return null;
  const regex = /^[0-9]{8}$/;
  if (!regex.test(form.hs_code)) {
    return 'HS code must be exactly 8 numeric digits.';
  }
  return null;
});

function submit() {
  form.put(`/products/${props.product.id}`);
}
</script>
