<template>
  <AdminLayout title="Register New Customer">
    <div style="max-width: 800px; margin: 0 auto;">
      <!-- Header -->
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
        <div>
          <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-primary); margin: 0;">
            Register Customer Account
          </h2>
          <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
            Set up customer profile, credit ceiling (Udhaar limit), and statutory FBR CNIC/NTN identity
          </div>
        </div>
        <Link href="/customers" class="btn btn-outline" style="padding: 0.4rem 0.85rem;">
          &larr; Back to Directory
        </Link>
      </div>

      <div class="card" style="padding: 2rem;">
        <form @submit.prevent="submit">
          <!-- Primary Identification -->
          <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 1rem;">
            1. Basic Information
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Customer / Business Name <span style="color: #ef4444;">*</span>
              </label>
              <input
                v-model="form.name"
                type="text"
                class="form-control"
                placeholder="e.g. Tariq Mehmood / Al-Madina Traders"
                required
              />
              <div v-if="form.errors.name" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
                {{ form.errors.name }}
              </div>
            </div>

            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Mobile / Contact Phone <span style="color: #ef4444;">*</span>
              </label>
              <input
                v-model="form.phone"
                type="text"
                class="form-control"
                placeholder="03001234567"
                required
              />
              <div v-if="form.errors.phone" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
                {{ form.errors.phone }}
              </div>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Email Address
              </label>
              <input
                v-model="form.email"
                type="email"
                class="form-control"
                placeholder="customer@domain.com"
              />
              <div v-if="form.errors.email" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
                {{ form.errors.email }}
              </div>
            </div>

            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                City
              </label>
              <input
                v-model="form.city"
                type="text"
                class="form-control"
                placeholder="Lahore, Karachi, Islamabad..."
              />
            </div>
          </div>

          <div style="margin-bottom: 1.5rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Full Address
            </label>
            <input
              v-model="form.address"
              type="text"
              class="form-control"
              placeholder="Shop #, Street, Plaza, Market Area"
            />
          </div>

          <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;" />

          <!-- Khata & Credit Facility -->
          <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 1rem;">
            2. Khata / Credit Terms
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Credit Ceiling Limit (in PKR)
              </label>
              <div style="position: relative; display: flex; align-items: center;">
                <span style="position: absolute; left: 0.75rem; font-weight: 700; color: var(--text-muted); font-size: 0.85rem;">Rs.</span>
                <input
                  v-model.number="creditLimitPkr"
                  type="number"
                  min="0"
                  step="1000"
                  class="form-control"
                  style="padding-left: 2.75rem; font-family: var(--font-mono); font-weight: 600;"
                  placeholder="0"
                />
              </div>
              <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.35rem;">
                Enter 0 for cash-only sales. If set to e.g. 50,000, cashier can book credit sales up to Rs. 50,000.
              </div>
            </div>

            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Internal Notes / Terms
              </label>
              <textarea
                v-model="form.notes"
                class="form-control"
                rows="2"
                placeholder="Payment terms, reference person, or guarantee notes..."
              ></textarea>
            </div>
          </div>

          <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;" />

          <!-- Statutory FBR Identity -->
          <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 1rem;">
            3. FBR Compliance &amp; Taxation (Pakistan)
          </div>

          <div style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; padding: 0.85rem; margin-bottom: 1.25rem;">
            <div style="font-size: 0.8rem; color: #10b981; font-weight: 600; display: flex; align-items: center; gap: 0.4rem;">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
              </svg>
              <span>FBR Statutory Requirement (Section 73)</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem; line-height: 1.4;">
              For single sales exceeding Rs. 100,000 to unregistered buyers, entering a CNIC is mandatory for valid fiscal transmission to FBR.
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                CNIC (13 digits)
              </label>
              <input
                v-model="form.cnic"
                type="text"
                class="form-control"
                placeholder="42101-1234567-1"
                maxlength="20"
              />
              <div v-if="form.errors.cnic" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
                {{ form.errors.cnic }}
              </div>
            </div>

            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                National Tax No. (NTN)
              </label>
              <input
                v-model="form.ntn"
                type="text"
                class="form-control"
                placeholder="1234567-8"
                maxlength="30"
              />
            </div>

            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Sales Tax Reg No. (STRN)
              </label>
              <input
                v-model="form.strn"
                type="text"
                class="form-control"
                placeholder="STRN (if registered)"
                maxlength="30"
              />
            </div>
          </div>

          <!-- Submit Buttons -->
          <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 2rem;">
            <Link href="/customers" class="btn btn-outline">
              Cancel
            </Link>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="form.processing"
              style="padding: 0.5rem 1.5rem;"
            >
              <span v-if="form.processing">Registering...</span>
              <span v-else>Register Customer</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const creditLimitPkr = ref(0);

const form = useForm({
  name: '',
  phone: '',
  email: '',
  cnic: '',
  ntn: '',
  strn: '',
  address: '',
  city: '',
  credit_limit_minor: 0,
  notes: '',
});

watch(creditLimitPkr, (val) => {
  form.credit_limit_minor = Math.max(0, Math.round((val || 0) * 100));
});

function submit() {
  form.post('/customers');
}
</script>
