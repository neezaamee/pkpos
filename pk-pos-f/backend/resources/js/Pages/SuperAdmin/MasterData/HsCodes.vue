<template>
  <SuperAdminLayout title="Master HS Codes Catalog">
    <!-- Header Summary -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h2 style="font-size: 1.25rem; font-weight: 700;">FBR & Provincial HS Codes Catalog</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          Standard Pakistan Customs Harmonized System (HS) codes repository with default fiscal tax mappings.
        </p>
      </div>
      <div style="display: flex; gap: 0.75rem; align-items: center;">
        <button @click="openImportModal" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.4rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="17 8 12 3 7 8"></polyline>
            <line x1="12" y1="3" x2="12" y2="15"></line>
          </svg>
          Bulk CSV Import
        </button>
        <button @click="openCreateModal" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.4rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="16"></line>
            <line x1="8" y1="12" x2="16" y2="12"></line>
          </svg>
          Add HS Code
        </button>
      </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card-table" style="padding: 1.25rem; margin-bottom: 1.5rem;">
      <form @submit.prevent="applyFilters" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Search HS Code or Keyword</label>
          <input
            v-model="filterForm.search"
            type="text"
            class="form-input"
            placeholder="e.g. 1006.3010 or Basmati Rice..."
          />
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Tax Category</label>
          <select v-model="filterForm.tax_category" class="form-input">
            <option value="">All Categories</option>
            <option value="general_goods">General Goods (18%)</option>
            <option value="tier_1_retail">Tier-1 Retail (18%)</option>
            <option value="reduced_goods">Reduced Goods (1%)</option>
            <option value="services">Services (PRA/SRB)</option>
            <option value="exempt">Exempt (0%)</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Tax Authority</label>
          <select v-model="filterForm.authority" class="form-input">
            <option value="">All Authorities</option>
            <option value="fbr">FBR (Federal)</option>
            <option value="pra">PRA (Punjab)</option>
            <option value="srb">SRB (Sindh)</option>
            <option value="kpra">KPRA (KP)</option>
            <option value="bra">BRA (Balochistan)</option>
          </select>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
          <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.55rem 0.85rem;">
            Filter
          </button>
          <button type="button" @click="resetFilters" class="btn btn-secondary" style="padding: 0.55rem 0.85rem;">
            Reset
          </button>
        </div>
      </form>
    </div>

    <!-- HS Codes Table -->
    <div class="card-table">
      <div class="table-header">
        <h2>Harmonized System Repository</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">Showing {{ hsCodes.from || 0 }} to {{ hsCodes.to || 0 }} of {{ hsCodes.total }} codes</span>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>HS Code</th>
              <th>Description</th>
              <th>Authority</th>
              <th>Tax Category</th>
              <th>Default Rate</th>
              <th>Status</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in hsCodes.data" :key="item.id">
              <td>
                <span style="font-family: monospace; font-size: 0.9rem; font-weight: 700; color: #38bdf8; background: rgba(56, 189, 248, 0.1); padding: 0.2rem 0.5rem; border-radius: 4px;">
                  {{ item.hs_code }}
                </span>
              </td>
              <td>
                <div style="font-weight: 500; max-width: 420px; line-height: 1.4;">{{ item.description }}</div>
              </td>
              <td>
                <span class="badge" :class="getAuthorityBadgeClass(item.authority)">
                  {{ item.authority.toUpperCase() }}
                </span>
              </td>
              <td>
                <span class="badge badge-neutral">
                  {{ formatCategory(item.tax_category) }}
                </span>
              </td>
              <td>
                <span style="font-weight: 600; color: #10b981;">
                  {{ item.default_rate_pct }}%
                </span>
                <span style="font-size: 0.72rem; color: var(--text-muted); margin-left: 0.25rem;">({{ item.default_rate_basis_points }} bps)</span>
              </td>
              <td>
                <span class="badge" :class="item.is_active ? 'badge-success' : 'badge-danger'">
                  {{ item.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td style="text-align: right;">
                <button @click="openEditModal(item)" class="btn btn-secondary" style="padding: 0.3rem 0.65rem; font-size: 0.78rem;">
                  Edit
                </button>
              </td>
            </tr>
            <tr v-if="hsCodes.data.length === 0">
              <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                No Master HS codes found matching the filter criteria.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="hsCodes.links && hsCodes.links.length > 3" style="display: flex; justify-content: flex-end; padding: 1rem; gap: 0.35rem; border-top: 1px solid var(--border-color);">
        <Link
          v-for="(link, i) in hsCodes.links"
          :key="i"
          :href="link.url || '#'"
          class="btn btn-secondary"
          :class="{ 'btn-primary': link.active, disabled: !link.url }"
          style="padding: 0.35rem 0.65rem; font-size: 0.75rem;"
          v-html="link.label"
        />
      </div>
    </div>

    <!-- Create / Edit HS Code Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-container" style="max-width: 540px;">
        <div class="modal-header">
          <h3>{{ editingItem ? 'Edit Master HS Code' : 'Add New Master HS Code' }}</h3>
          <button @click="showModal = false" class="close-btn">&times;</button>
        </div>
        <form @submit.prevent="submitForm">
          <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
            <div class="form-group">
              <label class="form-label">HS Code (e.g. 1006.3010) *</label>
              <input
                v-model="form.hs_code"
                type="text"
                class="form-input"
                placeholder="0000.0000"
                required
              />
              <span v-if="form.errors.hs_code" class="form-error">{{ form.errors.hs_code }}</span>
            </div>

            <div class="form-group">
              <label class="form-label">Commodity Description *</label>
              <textarea
                v-model="form.description"
                class="form-input"
                rows="3"
                placeholder="Full official description of the goods or service..."
                required
              ></textarea>
              <span v-if="form.errors.description" class="form-error">{{ form.errors.description }}</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Tax Category *</label>
                <select v-model="form.tax_category" class="form-input" required>
                  <option value="general_goods">General Goods</option>
                  <option value="tier_1_retail">Tier-1 Retail</option>
                  <option value="reduced_goods">Reduced Goods</option>
                  <option value="services">Services</option>
                  <option value="exempt">Exempt</option>
                </select>
                <span v-if="form.errors.tax_category" class="form-error">{{ form.errors.tax_category }}</span>
              </div>

              <div class="form-group">
                <label class="form-label">Tax Authority *</label>
                <select v-model="form.authority" class="form-input" required>
                  <option value="fbr">FBR (Federal)</option>
                  <option value="pra">PRA (Punjab)</option>
                  <option value="srb">SRB (Sindh)</option>
                  <option value="kpra">KPRA (KP)</option>
                  <option value="bra">BRA (Balochistan)</option>
                </select>
                <span v-if="form.errors.authority" class="form-error">{{ form.errors.authority }}</span>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Default Rate (basis points) *</label>
                <input
                  v-model.number="form.default_rate_basis_points"
                  type="number"
                  class="form-input"
                  min="0"
                  max="10000"
                  placeholder="1800 for 18%"
                  required
                />
                <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem; display: block;">
                  {{ (form.default_rate_basis_points / 100).toFixed(2) }}% tax rate
                </span>
                <span v-if="form.errors.default_rate_basis_points" class="form-error">{{ form.errors.default_rate_basis_points }}</span>
              </div>

              <div class="form-group" style="display: flex; flex-direction: column; justify-content: center;">
                <label class="form-label">Catalog Status</label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; margin-top: 0.5rem;">
                  <input type="checkbox" v-model="form.is_active" />
                  <span style="font-size: 0.85rem;">Active in POS client suggestion</span>
                </label>
              </div>
            </div>
          </div>

          <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--border-color);">
            <button type="button" @click="showModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">
              {{ form.processing ? 'Saving...' : (editingItem ? 'Update HS Code' : 'Save HS Code') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Bulk CSV Import Modal -->
    <div v-if="showImportModal" class="modal-overlay" @click.self="showImportModal = false">
      <div class="modal-container" style="max-width: 620px;">
        <div class="modal-header">
          <h3>Bulk Import HS Codes</h3>
          <button @click="showImportModal = false" class="close-btn">&times;</button>
        </div>
        <form @submit.prevent="submitImport">
          <div class="modal-body" style="display: flex; flex-direction: column; gap: 1.25rem;">
            <p style="font-size: 0.85rem; color: var(--text-muted);">
              Upload a CSV file or paste CSV text containing columns:
              <code style="background: rgba(255,255,255,0.06); padding: 0.2rem 0.4rem; border-radius: 4px; color: #38bdf8;">hs_code,description,tax_category,default_rate_basis_points,authority</code>
            </p>

            <div class="form-group">
              <label class="form-label">Upload CSV File</label>
              <input
                type="file"
                accept=".csv,.txt"
                @change="handleFileUpload"
                class="form-input"
              />
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem; text-align: center;">
              <hr style="flex: 1; border-color: var(--border-color);" />
              <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">OR PASTE CSV DATA</span>
              <hr style="flex: 1; border-color: var(--border-color);" />
            </div>

            <div class="form-group">
              <label class="form-label">Paste Raw CSV</label>
              <textarea
                v-model="importForm.csv_data"
                rows="6"
                class="form-input"
                style="font-family: monospace; font-size: 0.8rem;"
                placeholder="hs_code,description,tax_category,default_rate_basis_points,authority&#10;1006.3010,Basmati rice,general_goods,1800,fbr&#10;0401.2000,Milk and cream,exempt,0,fbr"
              ></textarea>
            </div>
          </div>

          <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--border-color);">
            <button type="button" @click="showImportModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="importForm.processing">
              {{ importForm.processing ? 'Importing...' : 'Start Import' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  hsCodes: Object,
  filters: Object,
});

const filterForm = reactive({
  search: props.filters?.search || '',
  tax_category: props.filters?.tax_category || '',
  authority: props.filters?.authority || '',
});

function applyFilters() {
  router.get('/superadmin/master-data/hs-codes', filterForm, { preserveState: true, replace: true });
}

function resetFilters() {
  filterForm.search = '';
  filterForm.tax_category = '';
  filterForm.authority = '';
  applyFilters();
}

const showModal = ref(false);
const editingItem = ref(null);

const form = useForm({
  hs_code: '',
  description: '',
  tax_category: 'general_goods',
  authority: 'fbr',
  default_rate_basis_points: 1800,
  is_active: true,
});

function openCreateModal() {
  editingItem.value = null;
  form.reset();
  form.clearErrors();
  form.hs_code = '';
  form.description = '';
  form.tax_category = 'general_goods';
  form.authority = 'fbr';
  form.default_rate_basis_points = 1800;
  form.is_active = true;
  showModal.value = true;
}

function openEditModal(item) {
  editingItem.value = item;
  form.clearErrors();
  form.hs_code = item.hs_code;
  form.description = item.description;
  form.tax_category = item.tax_category;
  form.authority = item.authority;
  form.default_rate_basis_points = item.default_rate_basis_points;
  form.is_active = item.is_active;
  showModal.value = true;
}

function submitForm() {
  if (editingItem.value) {
    form.put(`/superadmin/master-data/hs-codes/${editingItem.value.id}`, {
      onSuccess: () => {
        showModal.value = false;
      },
    });
  } else {
    form.post('/superadmin/master-data/hs-codes', {
      onSuccess: () => {
        showModal.value = false;
      },
    });
  }
}

// Bulk Import
const showImportModal = ref(false);
const importForm = useForm({
  csv_file: null,
  csv_data: '',
});

function openImportModal() {
  importForm.reset();
  importForm.clearErrors();
  showImportModal.value = true;
}

function handleFileUpload(event) {
  if (event.target.files.length > 0) {
    importForm.csv_file = event.target.files[0];
  }
}

function submitImport() {
  importForm.post('/superadmin/master-data/hs-codes/import', {
    onSuccess: () => {
      showImportModal.value = false;
    },
  });
}

function formatCategory(cat) {
  const map = {
    general_goods: 'General Goods',
    tier_1_retail: 'Tier-1 Retail',
    reduced_goods: 'Reduced Goods',
    services: 'Services',
    exempt: 'Exempt',
  };
  return map[cat] || cat;
}

function getAuthorityBadgeClass(auth) {
  switch (auth) {
    case 'fbr': return 'badge-success';
    case 'pra': return 'badge-neutral';
    case 'srb': return 'badge-warning';
    case 'kpra': return 'badge-neutral';
    case 'bra': return 'badge-neutral';
    default: return 'badge-neutral';
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 1.5rem;
}

.modal-container {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  width: 100%;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
  animation: modalIn 0.15s ease-out;
}

@keyframes modalIn {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0;
}

.close-btn {
  background: transparent;
  border: none;
  font-size: 1.5rem;
  line-height: 1;
  color: var(--text-muted);
  cursor: pointer;
}

.close-btn:hover {
  color: var(--text-main);
}

.modal-body {
  padding: 1.5rem;
}

.form-error {
  color: #ef4444;
  font-size: 0.75rem;
  margin-top: 0.25rem;
  display: block;
}
</style>

