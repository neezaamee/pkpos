<template>
  <SuperAdminLayout :title="`Edit Tenant: ${tenant.name}`">
    <div style="max-width: 800px; margin: 0 auto;">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
        <div>
          <h2 style="font-size: 1.25rem; font-weight: 700;">Edit Tenant Profile</h2>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
            Update tax identifiers, registered jurisdiction, and internal platform notes.
          </p>
        </div>
        <Link :href="`/superadmin/tenants/${tenant.id}`" class="btn btn-secondary">
          Cancel
        </Link>
      </div>

      <div class="form-card" style="max-width: 100%;">
        <form @submit.prevent="submit">
          <!-- Section 1: Business Details -->
          <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 1.25rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: #818cf8; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
              </svg>
              Legal Business Information
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
              <!-- Tenant Name -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="name">Legal Business Name *</label>
                <input
                  id="name"
                  v-model="form.name"
                  type="text"
                  class="form-input"
                  :class="{ 'is-invalid': form.errors.name }"
                  placeholder="e.g. Apex Retailers Pakistan"
                  required
                />
                <span v-if="form.errors.name" class="invalid-feedback">{{ form.errors.name }}</span>
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
                  <option value="federal">Federal / Islamabad (FBR)</option>
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
              <div class="form-group" style="margin-bottom: 0; grid-column: 1 / -1;">
                <label class="form-label" for="fbr_pos_id">Assigned FBR POS ID</label>
                <input
                  id="fbr_pos_id"
                  v-model="form.fbr_pos_id"
                  type="text"
                  class="form-input"
                  :class="{ 'is-invalid': form.errors.fbr_pos_id }"
                  placeholder="e.g. POS-1001"
                />
                <span class="form-hint">Unique identifier issued by FBR e-Services for this merchant</span>
                <span v-if="form.errors.fbr_pos_id" class="invalid-feedback">{{ form.errors.fbr_pos_id }}</span>
              </div>
            </div>
          </div>

          <!-- Section 2: Internal Admin Notes -->
          <div style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: #ec4899; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
              </svg>
              Internal Notes & Observations
            </h3>

            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" for="notes">Admin Notes (Private to Platform Staff)</label>
              <textarea
                id="notes"
                v-model="form.notes"
                class="form-input"
                :class="{ 'is-invalid': form.errors.notes }"
                rows="4"
                placeholder="Record compliance history, hardware setup notes, or special merchant arrangements..."
              ></textarea>
              <span v-if="form.errors.notes" class="invalid-feedback">{{ form.errors.notes }}</span>
            </div>
          </div>

          <!-- Form Actions -->
          <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
            <Link :href="`/superadmin/tenants/${tenant.id}`" class="btn btn-secondary">
              Cancel
            </Link>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="form.processing"
              style="min-width: 140px;"
            >
              <span v-if="form.processing">Saving...</span>
              <span v-else>Update Profile</span>
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

const props = defineProps({
  tenant: {
    type: Object,
    required: true,
  },
});

const form = useForm({
  name: props.tenant.name,
  province: props.tenant.province,
  ntn: props.tenant.ntn,
  strn: props.tenant.strn || '',
  fbr_pos_id: props.tenant.fbr_pos_id || '',
  notes: props.tenant.notes || '',
});

function submit() {
  form.put(`/superadmin/tenants/${props.tenant.id}`);
}
</script>

