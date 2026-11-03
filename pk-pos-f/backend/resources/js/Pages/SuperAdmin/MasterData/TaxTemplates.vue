<template>
  <SuperAdminLayout title="Provincial Tax Rate Templates">
    <!-- Header Summary -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h2 style="font-size: 1.25rem; font-weight: 700;">Provincial & Federal Tax Rule Templates</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          Official baseline tax schedules across FBR, PRA, SRB, KPRA, and BRA. Synchronize changes directly to active merchant tenants.
        </p>
      </div>
      <div style="display: flex; gap: 0.75rem; align-items: center;">
        <button @click="openCreateModal" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.4rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="16"></line>
            <line x1="8" y1="12" x2="16" y2="12"></line>
          </svg>
          New Tax Template
        </button>
      </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card-table" style="padding: 1.25rem; margin-bottom: 1.5rem;">
      <form @submit.prevent="applyFilters" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0; min-width: 240px;">
          <label class="form-label" style="font-size: 0.78rem;">Jurisdiction / Province</label>
          <select v-model="filterForm.province" class="form-input">
            <option value="">All Jurisdictions (Federal & Provinces)</option>
            <option value="federal">Federal (FBR)</option>
            <option value="punjab">Punjab (PRA)</option>
            <option value="sindh">Sindh (SRB)</option>
            <option value="kp">Khyber Pakhtunkhwa (KPRA)</option>
            <option value="balochistan">Balochistan (BRA)</option>
          </select>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
          <button type="submit" class="btn btn-primary" style="padding: 0.55rem 0.85rem;">
            Filter
          </button>
          <button type="button" @click="resetFilters" class="btn btn-secondary" style="padding: 0.55rem 0.85rem;">
            Reset
          </button>
        </div>
      </form>
    </div>

    <!-- Tax Templates Table -->
    <div class="card-table">
      <div class="table-header">
        <h2>Standard Tax Schedules</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">Showing {{ templates.from || 0 }} to {{ templates.to || 0 }} of {{ templates.total }} templates</span>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Template Name</th>
              <th>Province / Authority</th>
              <th>Category</th>
              <th>Payment Instrument</th>
              <th>Tax Rate</th>
              <th>Effective Date</th>
              <th>Status</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in templates.data" :key="item.id">
              <td>
                <div style="font-weight: 600;">{{ item.name }}</div>
                <div v-if="item.description" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem; max-width: 340px;">
                  {{ item.description }}
                </div>
              </td>
              <td>
                <div style="display: flex; align-items: center; gap: 0.4rem;">
                  <span class="badge" :class="getProvinceBadgeClass(item.province)">
                    {{ formatProvince(item.province) }}
                  </span>
                  <span class="badge badge-neutral" style="font-size: 0.68rem; text-transform: uppercase;">
                    {{ item.authority }}
                  </span>
                </div>
              </td>
              <td>
                <span class="badge badge-neutral">
                  {{ formatCategory(item.tax_category) }}
                </span>
              </td>
              <td>
                <span style="font-size: 0.82rem; text-transform: capitalize; font-weight: 500;">
                  {{ item.payment_method }}
                </span>
              </td>
              <td>
                <span style="font-weight: 700; color: #10b981; font-size: 0.95rem;">
                  {{ item.rate_pct }}%
                </span>
                <div style="font-size: 0.7rem; color: var(--text-muted);">{{ item.rate_basis_points }} bps</div>
              </td>
              <td>
                <span style="font-size: 0.8rem; color: var(--text-muted);">
                  {{ item.effective_from || 'Always' }}
                </span>
              </td>
              <td>
                <span class="badge" :class="item.is_active ? 'badge-success' : 'badge-danger'">
                  {{ item.is_active ? 'Active' : 'Disabled' }}
                </span>
              </td>
              <td style="text-align: right;">
                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                  <button @click="openEditModal(item)" class="btn btn-secondary" style="padding: 0.3rem 0.65rem; font-size: 0.78rem;">
                    Edit
                  </button>
                  <button @click="openSyncModal(item)" class="btn btn-primary" style="padding: 0.3rem 0.65rem; font-size: 0.78rem; background: #6366f1; border-color: #6366f1;">
                    Sync to Tenants
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="templates.data.length === 0">
              <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                No tax templates found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="templates.links && templates.links.length > 3" style="display: flex; justify-content: flex-end; padding: 1rem; gap: 0.35rem; border-top: 1px solid var(--border-color);">
        <Link
          v-for="(link, i) in templates.links"
          :key="i"
          :href="link.url || '#'"
          class="btn btn-secondary"
          :class="{ 'btn-primary': link.active, disabled: !link.url }"
          style="padding: 0.35rem 0.65rem; font-size: 0.75rem;"
          v-html="link.label"
        />
      </div>
    </div>

    <!-- Create / Edit Tax Template Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-container" style="max-width: 580px;">
        <div class="modal-header">
          <h3>{{ editingItem ? 'Edit Tax Template' : 'Create Provincial Tax Template' }}</h3>
          <button @click="showModal = false" class="close-btn">&times;</button>
        </div>
        <form @submit.prevent="submitForm">
          <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
            <div class="form-group">
              <label class="form-label">Template Name *</label>
              <input
                v-model="form.name"
                type="text"
                class="form-input"
                placeholder="e.g. Punjab Digital Restaurant Incentive"
                required
              />
              <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Province / Jurisdiction *</label>
                <select v-model="form.province" class="form-input" required @change="onProvinceChange">
                  <option value="federal">Federal</option>
                  <option value="punjab">Punjab</option>
                  <option value="sindh">Sindh</option>
                  <option value="kp">Khyber Pakhtunkhwa</option>
                  <option value="balochistan">Balochistan</option>
                </select>
                <span v-if="form.errors.province" class="form-error">{{ form.errors.province }}</span>
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
                <label class="form-label">Payment Instrument Applicability *</label>
                <select v-model="form.payment_method" class="form-input" required>
                  <option value="any">Any Payment Method</option>
                  <option value="cash">Cash Only</option>
                  <option value="card">Card Only</option>
                  <option value="raast">Raast Only</option>
                  <option value="wallet">Digital Wallet Only</option>
                </select>
                <span v-if="form.errors.payment_method" class="form-error">{{ form.errors.payment_method }}</span>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Tax Rate (basis points) *</label>
                <input
                  v-model.number="form.rate_basis_points"
                  type="number"
                  class="form-input"
                  min="0"
                  max="10000"
                  placeholder="1600 for 16%"
                  required
                />
                <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem; display: block;">
                  {{ (form.rate_basis_points / 100).toFixed(2) }}% effective tax rate
                </span>
                <span v-if="form.errors.rate_basis_points" class="form-error">{{ form.errors.rate_basis_points }}</span>
              </div>

              <div class="form-group">
                <label class="form-label">Effective From *</label>
                <input
                  v-model="form.effective_from"
                  type="date"
                  class="form-input"
                  required
                />
                <span v-if="form.errors.effective_from" class="form-error">{{ form.errors.effective_from }}</span>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Statutory Rule Description</label>
              <textarea
                v-model="form.description"
                rows="2"
                class="form-input"
                placeholder="Statutory citation, SRO reference, or explanation..."
              ></textarea>
              <span v-if="form.errors.description" class="form-error">{{ form.errors.description }}</span>
            </div>

            <div class="form-group">
              <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" v-model="form.is_active" />
                <span style="font-size: 0.85rem;">Active template eligible for tenant distribution</span>
              </label>
            </div>
          </div>

          <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--border-color);">
            <button type="button" @click="showModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">
              {{ form.processing ? 'Saving...' : (editingItem ? 'Update Template' : 'Save Template') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Sync Confirmation Modal -->
    <div v-if="showSyncModal" class="modal-overlay" @click.self="showSyncModal = false">
      <div class="modal-container" style="max-width: 480px;">
        <div class="modal-header">
          <h3>Propagate Tax Rule to Tenants</h3>
          <button @click="showSyncModal = false" class="close-btn">&times;</button>
        </div>
        <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
          <div style="background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: var(--radius-md); padding: 1rem;">
            <div style="font-weight: 700; color: #818cf8; margin-bottom: 0.35rem;">
              {{ syncingItem?.name }}
            </div>
            <div style="font-size: 0.82rem; color: var(--text-main);">
              <strong>Rate:</strong> {{ syncingItem?.rate_pct }}% ({{ syncingItem?.rate_basis_points }} bps) &bull;
              <strong>Target:</strong> {{ syncingItem?.province === 'federal' ? 'All active tenants across Pakistan' : 'Active merchants in ' + formatProvince(syncingItem?.province) }}
            </div>
          </div>

          <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">
            This operation will automatically insert or update the corresponding active <code>TaxRule</code> record across all matching tenants. Existing custom rules for the same category and payment method will be updated with this statutory rate.
          </p>

          <p style="font-size: 0.82rem; color: #f59e0b; background: rgba(245, 158, 11, 0.1); padding: 0.65rem; border-radius: var(--radius-sm); border: 1px solid rgba(245, 158, 11, 0.2);">
            &bull; This platform action will be permanently recorded in the immutable platform audit log.
          </p>
        </div>

        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--border-color);">
          <button type="button" @click="showSyncModal = false" class="btn btn-secondary">Cancel</button>
          <button @click="confirmSync" class="btn btn-primary" :disabled="syncProcessing" style="background: #6366f1; border-color: #6366f1;">
            {{ syncProcessing ? 'Synchronizing...' : 'Confirm & Propagate' }}
          </button>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  templates: Object,
  filters: Object,
});

const filterForm = reactive({
  province: props.filters?.province || '',
});

function applyFilters() {
  router.get('/superadmin/master-data/tax-templates', filterForm, { preserveState: true, replace: true });
}

function resetFilters() {
  filterForm.province = '';
  applyFilters();
}

const showModal = ref(false);
const editingItem = ref(null);

const form = useForm({
  name: '',
  province: 'punjab',
  authority: 'pra',
  tax_category: 'services',
  payment_method: 'any',
  rate_basis_points: 1600,
  effective_from: '2024-07-01',
  description: '',
  is_active: true,
});

function onProvinceChange() {
  const map = {
    federal: 'fbr',
    punjab: 'pra',
    sindh: 'srb',
    kp: 'kpra',
    balochistan: 'bra',
  };
  if (map[form.province]) {
    form.authority = map[form.province];
  }
}

function openCreateModal() {
  editingItem.value = null;
  form.reset();
  form.clearErrors();
  form.name = '';
  form.province = 'punjab';
  form.authority = 'pra';
  form.tax_category = 'services';
  form.payment_method = 'any';
  form.rate_basis_points = 1600;
  form.effective_from = '2024-07-01';
  form.description = '';
  form.is_active = true;
  showModal.value = true;
}

function openEditModal(item) {
  editingItem.value = item;
  form.clearErrors();
  form.name = item.name;
  form.province = item.province;
  form.authority = item.authority;
  form.tax_category = item.tax_category;
  form.payment_method = item.payment_method;
  form.rate_basis_points = item.rate_basis_points;
  form.effective_from = item.effective_from || '2024-07-01';
  form.description = item.description || '';
  form.is_active = item.is_active;
  showModal.value = true;
}

function submitForm() {
  if (editingItem.value) {
    form.put(`/superadmin/master-data/tax-templates/${editingItem.value.id}`, {
      onSuccess: () => {
        showModal.value = false;
      },
    });
  } else {
    form.post('/superadmin/master-data/tax-templates', {
      onSuccess: () => {
        showModal.value = false;
      },
    });
  }
}

// Sync to Tenants
const showSyncModal = ref(false);
const syncingItem = ref(null);
const syncProcessing = ref(false);

function openSyncModal(item) {
  syncingItem.value = item;
  showSyncModal.value = true;
}

function confirmSync() {
  if (!syncingItem.value) return;
  syncProcessing.value = true;
  router.post(`/superadmin/master-data/tax-templates/${syncingItem.value.id}/sync`, {}, {
    onFinish: () => {
      syncProcessing.value = false;
      showSyncModal.value = false;
    },
  });
}

function formatProvince(p) {
  const map = {
    federal: 'Federal (All)',
    punjab: 'Punjab',
    sindh: 'Sindh',
    kp: 'Khyber Pakhtunkhwa',
    balochistan: 'Balochistan',
  };
  return map[p] || p;
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

function getProvinceBadgeClass(prov) {
  switch (prov) {
    case 'federal': return 'badge-success';
    case 'punjab': return 'badge-neutral';
    case 'sindh': return 'badge-warning';
    case 'kp': return 'badge-neutral';
    case 'balochistan': return 'badge-neutral';
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

