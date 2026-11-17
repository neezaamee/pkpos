<template>
  <SuperAdminLayout title="Starter Industry Catalogs">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin: 0 0 0.35rem 0;">
          Starter Industry Catalogs
        </h1>
        <p style="color: var(--text-muted); margin: 0; font-size: 0.9rem;">
          Pre-packaged retail catalogs with Pakistani EAN-13 barcodes, FBR HS codes, and standard price tiers.
        </p>
      </div>
    </div>

    <!-- Catalog Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(460px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
      <div v-for="cat in catalogs" :key="cat.key" class="catalog-card">
        <div class="catalog-header">
          <div>
            <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.35rem;">
              <h2 style="font-size: 1.2rem; font-weight: 700; color: var(--text-primary); margin: 0;">
                {{ cat.name }}
              </h2>
              <span class="industry-badge">{{ cat.industry }}</span>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0; line-height: 1.4;">
              {{ cat.description }}
            </p>
          </div>
        </div>

        <div class="catalog-body">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);">
              Included SKUs: <span style="color: #818cf8; font-weight: 700;">{{ cat.sku_count }} Products</span>
            </div>

            <button
              type="button"
              @click="openSeedModal(cat)"
              class="btn btn-primary"
              style="padding: 0.45rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.45rem;"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
              </svg>
              Seed Into Store
            </button>
          </div>

          <!-- Product Sample Preview Table -->
          <div class="table-container" style="max-height: 320px; overflow-y: auto;">
            <table class="data-table" style="font-size: 0.825rem;">
              <thead>
                <tr>
                  <th>Product Name</th>
                  <th>Barcode</th>
                  <th>HS Code</th>
                  <th>Retail (Rs.)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in cat.items" :key="item.sku">
                  <td style="font-weight: 600;">
                    <div>{{ item.name }}</div>
                    <div style="font-size: 0.725rem; color: var(--text-muted);">{{ item.category }}</div>
                  </td>
                  <td style="font-family: monospace;">{{ item.barcode }}</td>
                  <td style="font-family: monospace; color: #38bdf8;">{{ item.hs_code }}</td>
                  <td style="font-family: monospace; font-weight: 700;">
                    {{ formatMoney(item.price_minor) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- SEED CATALOG MODAL -->
    <div v-if="showModal" class="modal-backdrop">
      <div class="modal-card">
        <div class="modal-header">
          <h3>Seed '{{ selectedCatalog?.name }}'</h3>
          <button @click="showModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitSeed">
          <div class="modal-body">
            <div style="background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.25rem;">
              <p style="font-size: 0.85rem; color: #c7d2fe; margin: 0; line-height: 1.5;">
                This will insert <strong>{{ selectedCatalog?.sku_count }} standard products</strong> with barcodes, HS codes, retail pricing, and 50 units initial stock into the selected merchant store.
              </p>
            </div>

            <div class="form-group">
              <label class="form-label">Select Destination Tenant *</label>
              <select v-model="form.tenant_id" class="form-input" required>
                <option value="" disabled>-- Select Tenant Account --</option>
                <option v-for="t in tenants" :key="t.id" :value="t.id">
                  {{ t.name }} (NTN: {{ t.ntn || 'N/A' }})
                </option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="showModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing || !form.tenant_id">
              {{ form.processing ? 'Seeding Catalog...' : 'Confirm & Seed Store' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  catalogs: { type: Array, required: true },
  tenants: { type: Array, required: true },
});

const showModal = ref(false);
const selectedCatalog = ref(null);

const form = useForm({
  tenant_id: '',
  catalog_key: '',
});

function openSeedModal(cat) {
  selectedCatalog.value = cat;
  form.catalog_key = cat.key;
  form.tenant_id = props.tenants[0]?.id || '';
  showModal.value = true;
}

function submitSeed() {
  form.post('/superadmin/master-data/catalogs/seed', {
    preserveScroll: true,
    onSuccess: () => {
      showModal.value = false;
    },
  });
}

function formatMoney(minor) {
  return (Number(minor || 0) / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<style scoped>
.catalog-card {
  background: var(--bg-surface);
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  overflow: hidden;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
}

.catalog-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  background: rgba(15, 23, 42, 0.3);
}

.catalog-body {
  padding: 1.25rem 1.5rem;
  flex: 1;
}

.industry-badge {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  background: rgba(99, 102, 241, 0.15);
  color: #818cf8;
  border: 1px solid rgba(99, 102, 241, 0.3);
}

.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 1rem;
}

.modal-card {
  background: #1e293b;
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  width: 100%;
  max-width: 500px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  overflow: hidden;
}

.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-header h3 {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0;
}

.modal-close {
  background: transparent;
  border: none;
  color: var(--text-muted);
  font-size: 1.5rem;
  cursor: pointer;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border-color);
  background: rgba(15, 23, 42, 0.4);
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}
</style>
