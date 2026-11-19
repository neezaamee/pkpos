<template>
  <AdminLayout title="Units of Measure & Packaging Conversions">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
      <div>
        <h1 style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); margin: 0;">
          Units of Measure (UOM) &amp; Packaging Conversions
        </h1>
        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          Configure base measurement units, fractional decimal weighing (Kg, Liters), and multi-pack conversion ratios (Boxes, Cartons)
        </div>
      </div>

      <div style="display: flex; gap: 0.75rem;">
        <button
          type="button"
          class="btn btn-outline"
          style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.9rem;"
          @click="showUomModal = true"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          <span>+ Master Unit</span>
        </button>

        <button
          type="button"
          class="btn btn-primary"
          style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 1rem;"
          @click="showConversionModal = true"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
          </svg>
          <span>+ Pack Conversion (Carton/Box)</span>
        </button>
      </div>
    </div>

    <!-- Master Units of Measure Chips Strip -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
      <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 0.75rem;">
        Active Measurement Units in System
      </div>
      <div style="display: flex; flex-wrap: wrap; gap: 0.65rem;">
        <div
          v-for="u in uoms"
          :key="u.id"
          style="padding: 0.45rem 0.85rem; border-radius: 8px; background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.25); display: flex; align-items: center; gap: 0.65rem;"
        >
          <div>
            <span style="font-weight: 700; font-size: 0.85rem; color: var(--text-primary);">{{ u.name }}</span>
            <span style="font-family: var(--font-mono); font-size: 0.75rem; color: #818cf8; margin-left: 0.35rem;">({{ u.code }})</span>
          </div>
          <span
            v-if="u.allows_decimals"
            class="badge"
            style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-size: 0.65rem; padding: 0.15rem 0.4rem;"
          >
            Fractional (Kg/Mtr)
          </span>
          <span
            v-else
            class="badge"
            style="background: rgba(148, 163, 184, 0.15); color: var(--text-muted); font-size: 0.65rem; padding: 0.15rem 0.4rem;"
          >
            Discrete (Integer)
          </span>
        </div>
      </div>
    </div>

    <!-- Product Packaging Conversions Table -->
    <div class="card-table">
      <div class="table-header">
        <div>
          <h2>Multi-Pack Conversions &amp; Carton Barcodes</h2>
          <div style="font-size: 0.825rem; color: var(--text-muted);">
            Auto-deduct base pieces when cartons or packs are scanned at the register
          </div>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Product (Base Item)</th>
              <th>Packaging Pack</th>
              <th>Pack Barcode</th>
              <th style="text-align: right;">Conversion Ratio</th>
              <th style="text-align: right;">Pack Price (PKR)</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in conversions.data" :key="c.id">
              <td>
                <div style="font-weight: 600; color: var(--text-primary);">
                  {{ c.product?.name || '—' }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono);">
                  Base Unit: {{ c.product?.unit || 'pcs' }} &bull; Base Price: {{ formatMoney(c.product?.price_minor) }}
                </div>
              </td>
              <td>
                <div style="display: flex; align-items: center; gap: 0.4rem;">
                  <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #818cf8; font-weight: 700;">
                    {{ c.unit_name }}
                  </span>
                  <span style="font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono);">
                    ({{ c.unit_code }})
                  </span>
                </div>
              </td>
              <td style="font-family: var(--font-mono); font-size: 0.825rem;">
                <span v-if="c.barcode" style="color: #38bdf8; font-weight: 600;">{{ c.barcode }}</span>
                <span v-else style="color: var(--text-muted);">—</span>
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 700; color: #10b981;">
                1 {{ c.unit_code }} = {{ Number(c.conversion_factor) }} {{ c.product?.unit || 'pcs' }}
              </td>
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 700; color: var(--text-primary);">
                {{ formatMoney(c.price_minor) }}
              </td>
              <td style="text-align: right;">
                <button
                  type="button"
                  class="btn btn-outline"
                  style="padding: 0.25rem 0.6rem; font-size: 0.75rem; color: #ef4444; border-color: rgba(239, 68, 68, 0.3);"
                  @click="deleteConversion(c.id)"
                >
                  Delete
                </button>
              </td>
            </tr>

            <tr v-if="conversions.data.length === 0">
              <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                No packaging conversions configured yet. Add a pack conversion to sell cartons or boxes with automatic inventory deduction.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="conversions.links && conversions.links.length > 3" style="padding: 1rem; display: flex; justify-content: flex-end; gap: 0.35rem;">
        <Link
          v-for="(link, i) in conversions.links"
          :key="i"
          :href="link.url || '#'"
          v-html="link.label"
          class="btn btn-outline"
          :class="{ 'active': link.active, 'disabled': !link.url }"
          style="padding: 0.25rem 0.65rem; font-size: 0.75rem;"
        />
      </div>
    </div>

    <!-- Modal: Add Master UOM -->
    <div
      v-if="showUomModal"
      class="modal-backdrop"
      style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 1rem;"
      @click.self="showUomModal = false"
    >
      <div class="card" style="width: 100%; max-width: 440px; padding: 1.75rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
          <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0;">
            Add Master Unit of Measure
          </h3>
          <button
            type="button"
            class="modal-close"
            style="background: transparent; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;"
            @click="showUomModal = false"
          >
            &times;
          </button>
        </div>

        <form @submit.prevent="submitUom">
          <div style="margin-bottom: 1rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Unit Name <span style="color: #ef4444;">*</span>
            </label>
            <input
              v-model="uomForm.name"
              type="text"
              class="form-control"
              placeholder="e.g. Kilogram, Meter, Box of 10"
              required
            />
          </div>

          <div style="margin-bottom: 1rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Unit Code (Short) <span style="color: #ef4444;">*</span>
            </label>
            <input
              v-model="uomForm.code"
              type="text"
              class="form-control"
              placeholder="e.g. kg, mtr, box"
              required
            />
          </div>

          <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <input
              id="allows_decimals"
              v-model="uomForm.allows_decimals"
              type="checkbox"
              style="width: 16px; height: 16px; cursor: pointer;"
            />
            <label for="allows_decimals" style="font-size: 0.85rem; color: var(--text-primary); cursor: pointer;">
              Allows decimal quantities (e.g. 1.750 kg, 3.25 m)
            </label>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" class="btn btn-outline" @click="showUomModal = false">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="uomForm.processing">
              Save Unit
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Add Pack Conversion -->
    <div
      v-if="showConversionModal"
      class="modal-backdrop"
      style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 1rem;"
      @click.self="showConversionModal = false"
    >
      <div class="card" style="width: 100%; max-width: 520px; padding: 1.75rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
          <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0;">
            Add Packaging Conversion
          </h3>
          <button
            type="button"
            class="modal-close"
            style="background: transparent; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;"
            @click="showConversionModal = false"
          >
            &times;
          </button>
        </div>

        <form @submit.prevent="submitConversion">
          <div style="margin-bottom: 1rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Product (Base Item) <span style="color: #ef4444;">*</span>
            </label>
            <select v-model="convForm.product_id" class="form-control" required>
              <option value="">-- Select Product --</option>
              <option v-for="p in products" :key="p.id" :value="p.id">
                {{ p.name }} (Base: {{ p.unit || 'pcs' }} &bull; Rs. {{ p.price_minor / 100 }})
              </option>
            </select>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Packaging Name <span style="color: #ef4444;">*</span>
              </label>
              <input
                v-model="convForm.unit_name"
                type="text"
                class="form-control"
                placeholder="Carton of 24"
                required
              />
            </div>
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Pack Code (Short) <span style="color: #ef4444;">*</span>
              </label>
              <input
                v-model="convForm.unit_code"
                type="text"
                class="form-control"
                placeholder="ctn"
                required
              />
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Conversion Factor <span style="color: #ef4444;">*</span>
              </label>
              <input
                v-model.number="convForm.conversion_factor"
                type="number"
                step="0.001"
                min="0.001"
                class="form-control"
                placeholder="24"
                required
              />
              <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
                1 pack = how many base units?
              </div>
            </div>
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Pack Selling Price (PKR) <span style="color: #ef4444;">*</span>
              </label>
              <input
                v-model.number="packPricePkr"
                type="number"
                min="0"
                step="1"
                class="form-control"
                placeholder="2400"
                required
              />
            </div>
          </div>

          <div style="margin-bottom: 1.5rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Pack Barcode (Optional)
            </label>
            <input
              v-model="convForm.barcode"
              type="text"
              class="form-control"
              placeholder="Barcode printed on outer carton box"
            />
            <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem;">
              Scanning this barcode at POS will automatically ring 1 pack and deduct the conversion factor from base stock.
            </div>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" class="btn btn-outline" @click="showConversionModal = false">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="convForm.processing">
              Save Packaging Pack
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
  uoms: {
    type: Array,
    required: true,
  },
  conversions: {
    type: Object,
    required: true,
  },
  products: {
    type: Array,
    required: true,
  },
});

const showUomModal = ref(false);
const showConversionModal = ref(false);
const packPricePkr = ref(0);

const uomForm = useForm({
  name: '',
  code: '',
  allows_decimals: false,
});

const convForm = useForm({
  product_id: '',
  unit_name: '',
  unit_code: '',
  barcode: '',
  conversion_factor: 1,
  price_minor: 0,
});

watch(packPricePkr, (val) => {
  convForm.price_minor = Math.round((val || 0) * 100);
});

function submitUom() {
  uomForm.post('/uom', {
    onSuccess: () => {
      showUomModal.value = false;
      uomForm.reset();
    },
  });
}

function submitConversion() {
  convForm.post('/uom/conversions', {
    onSuccess: () => {
      showConversionModal.value = false;
      convForm.reset();
      packPricePkr.value = 0;
    },
  });
}

function deleteConversion(id) {
  if (confirm('Are you sure you want to delete this packaging conversion?')) {
    router.delete(`/uom/conversions/${id}`);
  }
}

function formatMoney(minor) {
  if (minor === undefined || minor === null) return 'Rs. 0';
  const val = minor / 100;
  return 'Rs. ' + val.toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
</script>
