<template>
  <SuperAdminLayout title="Create Tenant">
    <div style="max-width: 800px; margin: 0 auto;">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
        <div>
          <h2 style="font-size: 1.25rem; font-weight: 700;">Register New Tenant Organization</h2>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
            Creates tenant record, primary retail location, and assigns initial owner credentials in one transaction.
          </p>
        </div>
        <Link href="/superadmin/tenants" class="btn btn-secondary">
          Cancel
        </Link>
      </div>

      <div class="form-card" style="max-width: 100%;">
        <form @submit.prevent="submit">
          <!-- Section 1: Tenant Information -->
          <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 1.25rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: #818cf8; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
              </svg>
              Organization Details
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
              <!-- Tenant Name -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="tenant_name">Tenant / Business Name *</label>
                <input
                  id="tenant_name"
                  v-model="form.tenant_name"
                  type="text"
                  class="form-input"
                  :class="{ 'is-invalid': form.errors.tenant_name }"
                  placeholder="e.g. Al-Madina Supermarket"
                  required
                />
                <span v-if="form.errors.tenant_name" class="invalid-feedback">{{ form.errors.tenant_name }}</span>
              </div>

              <!-- Province -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="province">Tax Jurisdiction / Province *</label>
                <select
                  id="province"
                  v-model="form.province"
                  class="form-input"
                  :class="{ 'is-invalid': form.errors.province }"
                  required
                >
                  <option value="punjab">Punjab (PRA)</option>
                  <option value="sindh">Sindh (SRB)</option>
                  <option value="kp">Khyber Pakhtunkhwa (KPRA)</option>
                  <option value="balochistan">Balochistan (BRA)</option>
                </select>
                <span v-if="form.errors.province" class="invalid-feedback">{{ form.errors.province }}</span>
              </div>

              <!-- NTN -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="ntn">NTN (National Tax Number) *</label>
                <input
                  id="ntn"
                  v-model="form.ntn"
                  type="text"
                  class="form-input"
                  :class="{ 'is-invalid': form.errors.ntn }"
                  placeholder="e.g. 1234567-8"
                  required
                />
                <span class="form-hint">7 digits followed by hyphen and check digit</span>
                <span v-if="form.errors.ntn" class="invalid-feedback">{{ form.errors.ntn }}</span>
              </div>

              <!-- STRN -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="strn">STRN (Sales Tax Registration No.)</label>
                <input
                  id="strn"
                  v-model="form.strn"
                  type="text"
                  class="form-input"
                  :class="{ 'is-invalid': form.errors.strn }"
                  placeholder="e.g. 12-34-5678-901-23"
                />
                <span v-if="form.errors.strn" class="invalid-feedback">{{ form.errors.strn }}</span>
              </div>

              <!-- FBR POS ID -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="fbr_pos_id">FBR POS ID (Optional)</label>
                <input
                  id="fbr_pos_id"
                  v-model="form.fbr_pos_id"
                  type="text"
                  class="form-input"
                  :class="{ 'is-invalid': form.errors.fbr_pos_id }"
                  placeholder="e.g. POS-1002"
                />
                <span v-if="form.errors.fbr_pos_id" class="invalid-feedback">{{ form.errors.fbr_pos_id }}</span>
              </div>
            </div>
          </div>

          <!-- Section 2: Owner User Credentials -->
          <div style="padding-bottom: 1rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: #34d399; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              Owner Account Credentials
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
              <!-- Owner Name -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="owner_name">Owner Full Name *</label>
                <input
                  id="owner_name"
                  v-model="form.owner_name"
                  type="text"
                  class="form-input"
                  :class="{ 'is-invalid': form.errors.owner_name }"
                  placeholder="e.g. Muhammad Aslam"
                  required
                />
                <span v-if="form.errors.owner_name" class="invalid-feedback">{{ form.errors.owner_name }}</span>
              </div>

              <!-- Owner Email -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="owner_email">Owner Email (Login Username) *</label>
                <input
                  id="owner_email"
                  v-model="form.owner_email"
                  type="email"
                  class="form-input"
                  :class="{ 'is-invalid': form.errors.owner_email }"
                  placeholder="e.g. aslam@retail.pk"
                  required
                />
                <span class="form-hint">Welcome email with login instructions will be sent here</span>
                <span v-if="form.errors.owner_email" class="invalid-feedback">{{ form.errors.owner_email }}</span>
              </div>

              <!-- Owner Password -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="owner_password">Initial Password *</label>
                <input
                  id="owner_password"
                  v-model="form.owner_password"
                  type="password"
                  class="form-input"
                  :class="{ 'is-invalid': form.errors.owner_password }"
                  placeholder="Minimum 8 characters"
                  required
                />
                <span class="form-hint">Can be changed after first login</span>
                <span v-if="form.errors.owner_password" class="invalid-feedback">{{ form.errors.owner_password }}</span>
              </div>
            </div>
          </div>

          <!-- Submit Buttons -->
          <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
            <Link href="/superadmin/tenants" class="btn btn-secondary">
              Cancel
            </Link>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="form.processing"
            >
              <svg v-if="!form.processing" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              <span>{{ form.processing ? 'Creating Tenant...' : 'Create & Provision Tenant' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const form = useForm({
  tenant_name: '',
  ntn: '',
  strn: '',
  province: 'punjab',
  fbr_pos_id: '',
  owner_name: '',
  owner_email: '',
  owner_password: '',
});

function submit() {
  form.post('/superadmin/tenants');
}
</script>
