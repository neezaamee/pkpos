<template>
  <AdminLayout title="Add New Product">
    <div style="max-width: 760px; margin: 0 auto;">
      <div class="form-card" style="max-width: 100%;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
          <div>
            <h3 style="font-size: 1.2rem; font-weight: 700;">New Catalog Product</h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);">
              Define pricing, tax category, and 8-digit PCT code
            </span>
          </div>
          <Link href="/products" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">
            ← Back to Catalog
          </Link>
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
              placeholder="e.g. Basmati Rice 5kg"
              required
            />
            <div v-if="form.errors.name" class="invalid-feedback">
              {{ form.errors.name }}
            </div>
          </div>

          <!-- SKU & Barcode Row -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label class="form-label" for="sku">SKU Code</label>
              <input
                id="sku"
                v-model="form.sku"
                type="text"
                class="form-input"
                :class="{ 'is-invalid': form.errors.sku }"
                placeholder="e.g. RICE-BAS-5KG"
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
                placeholder="e.g. 8964000123456"
              />
              <div v-if="form.errors.barcode" class="invalid-feedback">
                {{ form.errors.barcode }}
              </div>
            </div>
          </div>

          <!-- HS Code & Unit Row -->
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
                placeholder="e.g. 10063010"
                required
              />
              <span class="form-hint">Must be exactly 8 digits as per Pakistan Customs Tariff.</span>
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

          <!-- Price & Cost Row -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label class="form-label" for="price_in_pkr">Retail Selling Price (PKR) *</label>
              <input
                id="price_in_pkr"
                v-model="form.price_in_pkr"
                type="number"
                step="0.01"
                min="0"
                class="form-input"
                :class="{ 'is-invalid': form.errors.price_in_pkr }"
                placeholder="e.g. 1850.00"
                required
              />
              <span class="form-hint">Stored securely in minor units (paisa).</span>
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
                placeholder="e.g. 1500.00"
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
              placeholder="e.g. general_goods, services, restaurant, exempt"
              required
            />
            <span class="form-hint">Used by the Tax Rule Engine to dynamically resolve applicable GST/SST rates.</span>
            <div v-if="form.errors.tax_category" class="invalid-feedback">
              {{ form.errors.tax_category }}
            </div>
          </div>

          <!-- Product Variants (Optional) -->
          <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed var(--border-color);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
              <div>
                <span style="font-size: 0.95rem; font-weight: 600;">Product Variants (Optional)</span>
                <span style="display: block; font-size: 0.75rem; color: var(--text-muted);">
                  Size, color, or packaging options with optional price overrides
                </span>
              </div>
              <button
                type="button"
                class="btn btn-secondary"
                style="padding: 0.25rem 0.65rem; font-size: 0.75rem;"
                @click="addVariant"
              >
                + Add Variant
              </button>
            </div>

            <div v-for="(variant, idx) in form.variants" :key="idx" style="display: grid; grid-template-columns: 2fr 2fr 2fr auto; gap: 0.75rem; align-items: center; margin-bottom: 0.5rem;">
              <input
                v-model="variant.name"
                type="text"
                class="form-input"
                placeholder="Variant Name (e.g. Red, XL)"
                required
              />
              <input
                v-model="variant.sku"
                type="text"
                class="form-input"
                placeholder="Variant SKU"
              />
              <input
                v-model="variant.price_override_in_pkr"
                type="number"
                step="0.01"
                min="0"
                class="form-input"
                placeholder="Price (PKR)"
              />
              <button
                type="button"
                class="modal-close"
                style="font-size: 1.25rem; color: var(--accent-rose);"
                @click="removeVariant(idx)"
              >
                &times;
              </button>
            </div>
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
              {{ form.processing ? 'Saving Product...' : 'Create Product' }}
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

const form = useForm({
  name: '',
  sku: '',
  barcode: '',
  hs_code: '',
  unit: 'piece',
  price_in_pkr: '',
  cost_in_pkr: '',
  tax_category: 'general_goods',
  is_active: true,
  variants: [],
});

const hsCodeClientError = computed(() => {
  if (!form.hs_code) return null;
  const regex = /^[0-9]{8}$/;
  if (!regex.test(form.hs_code)) {
    return 'HS code must be exactly 8 numeric digits.';
  }
  return null;
});

function addVariant() {
  form.variants.push({
    name: '',
    sku: '',
    price_override_in_pkr: '',
  });
}

function removeVariant(index) {
  form.variants.splice(index, 1);
}

function submit() {
  form.post('/products');
}
</script>
