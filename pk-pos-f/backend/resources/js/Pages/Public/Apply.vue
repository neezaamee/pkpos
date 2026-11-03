<template>
  <div style="min-height: 100vh; background: #0f172a; color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 2rem 1rem;">
    <div style="max-width: 760px; margin: 0 auto;">
      <!-- Branding & Header -->
      <div style="text-align: center; margin-bottom: 2rem;">
        <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.35rem 0.85rem; border-radius: 9999px; margin-bottom: 1rem;">
          <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
          <span style="font-size: 0.75rem; font-weight: 700; color: #34d399; text-transform: uppercase; letter-spacing: 0.05em;">
            FBR Certified Tier-1 Digital Invoicing Partner
          </span>
        </div>
        <h1 style="font-size: 2rem; font-weight: 800; color: #ffffff; letter-spacing: -0.025em; margin-bottom: 0.5rem;">
          PK-POS Retail Merchant Onboarding
        </h1>
        <p style="color: #94a3b8; font-size: 0.95rem; max-width: 540px; margin: 0 auto;">
          Get your retail business live with automated FBR IRIS fiscalization, provincial sales tax compliance (PRA, SRB, KPRA), and offline desktop POS terminals.
        </p>
      </div>

      <!-- Flash Success Alert -->
      <div v-if="$page.props.flash?.success" style="background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; border-radius: 12px; padding: 1.25rem; margin-bottom: 2rem; display: flex; align-items: flex-start; gap: 1rem;">
        <div style="padding: 0.35rem; background: #10b981; border-radius: 50%; color: #000;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
        </div>
        <div>
          <div style="font-weight: 700; color: #34d399; font-size: 1rem;">Application Submitted Successfully!</div>
          <div style="font-size: 0.875rem; color: #cbd5e1; margin-top: 0.25rem;">{{ $page.props.flash.success }}</div>
        </div>
      </div>

      <!-- Application Form Card -->
      <div style="background: #1e293b; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 2rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);">
        <form @submit.prevent="submitForm" style="display: flex; flex-direction: column; gap: 1.75rem;">
          <!-- 1. Business Legal Identity -->
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #f8fafc; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
              <span style="width: 24px; height: 24px; border-radius: 50%; background: #3b82f6; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem;">1</span>
              Business Legal Identity
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.35rem;">
                  Legal Business Name *
                </label>
                <input
                  type="text"
                  v-model="form.business_name"
                  required
                  placeholder="e.g. Metro Retailers (Pvt) Ltd"
                  style="width: 100%; padding: 0.65rem 0.85rem; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; color: #fff; font-size: 0.9375rem;"
                />
              </div>

              <div>
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.35rem;">
                  Trade / Store Brand Name
                </label>
                <input
                  type="text"
                  v-model="form.trade_name"
                  placeholder="e.g. Metro Mart"
                  style="width: 100%; padding: 0.65rem 0.85rem; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; color: #fff; font-size: 0.9375rem;"
                />
              </div>

              <div>
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.35rem;">
                  Retail Sector / Industry *
                </label>
                <select
                  v-model="form.sector"
                  required
                  style="width: 100%; padding: 0.65rem 0.85rem; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; color: #fff; font-size: 0.9375rem;"
                >
                  <option value="general_retail">General Retail & Department Store</option>
                  <option value="supermarket">Supermarket & Grocery</option>
                  <option value="pharmacy">Pharmacy & Healthcare</option>
                  <option value="apparel">Apparel, Footwear & Fashion</option>
                  <option value="restaurant">Restaurant & Bakery</option>
                  <option value="electronics">Electronics & Mobile Store</option>
                </select>
              </div>

              <div>
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.35rem;">
                  Estimated POS Counters *
                </label>
                <input
                  type="number"
                  min="1"
                  max="100"
                  v-model.number="form.pos_terminal_count"
                  required
                  style="width: 100%; padding: 0.65rem 0.85rem; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; color: #fff; font-size: 0.9375rem;"
                />
              </div>
            </div>
          </div>

          <!-- 2. Store Geography -->
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #f8fafc; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
              <span style="width: 24px; height: 24px; border-radius: 50%; background: #3b82f6; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem;">2</span>
              Store Geography & Location
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.35rem;">
                  Province *
                </label>
                <select
                  v-model="form.province"
                  required
                  style="width: 100%; padding: 0.65rem 0.85rem; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; color: #fff; font-size: 0.9375rem;"
                >
                  <option value="punjab">Punjab (PRA)</option>
                  <option value="sindh">Sindh (SRB)</option>
                  <option value="kpk">Khyber Pakhtunkhwa (KPRA)</option>
                  <option value="balochistan">Balochistan (BRA)</option>
                  <option value="islamabad">Islamabad Capital Territory</option>
                </select>
              </div>

              <div>
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.35rem;">
                  City *
                </label>
                <input
                  type="text"
                  v-model="form.city"
                  required
                  placeholder="e.g. Lahore, Karachi, Islamabad"
                  style="width: 100%; padding: 0.65rem 0.85rem; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; color: #fff; font-size: 0.9375rem;"
                />
              </div>

              <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.35rem;">
                  Physical Store Address
                </label>
                <input
                  type="text"
                  v-model="form.address"
                  placeholder="Plot / Shop #, Street, Plaza, Commercial Market..."
                  style="width: 100%; padding: 0.65rem 0.85rem; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; color: #fff; font-size: 0.9375rem;"
                />
              </div>
            </div>
          </div>

          <!-- 3. FBR Tax Identification -->
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #f8fafc; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
              <span style="width: 24px; height: 24px; border-radius: 50%; background: #10b981; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem;">3</span>
              FBR Tax Identification & ATL
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.35rem;">
                  National Tax Number (NTN)
                </label>
                <input
                  type="text"
                  v-model="form.ntn"
                  placeholder="e.g. 1234567-8"
                  style="width: 100%; padding: 0.65rem 0.85rem; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; color: #fff; font-size: 0.9375rem; font-family: monospace;"
                />
              </div>

              <div>
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.35rem;">
                  Sales Tax Number (STRN)
                </label>
                <input
                  type="text"
                  v-model="form.strn"
                  placeholder="e.g. 12-34-5678-001-99"
                  style="width: 100%; padding: 0.65rem 0.85rem; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; color: #fff; font-size: 0.9375rem; font-family: monospace;"
                />
              </div>
            </div>
          </div>

          <!-- 4. Authorized Contact -->
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #f8fafc; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
              <span style="width: 24px; height: 24px; border-radius: 50%; background: #3b82f6; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem;">4</span>
              Authorized Representative Contact
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.35rem;">
                  Contact Person Full Name *
                </label>
                <input
                  type="text"
                  v-model="form.contact_name"
                  required
                  placeholder="e.g. Muhammad Usman"
                  style="width: 100%; padding: 0.65rem 0.85rem; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; color: #fff; font-size: 0.9375rem;"
                />
              </div>

              <div>
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.35rem;">
                  Mobile / WhatsApp Number *
                </label>
                <input
                  type="text"
                  v-model="form.contact_phone"
                  required
                  placeholder="0300-1234567"
                  style="width: 100%; padding: 0.65rem 0.85rem; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; color: #fff; font-size: 0.9375rem;"
                />
              </div>

              <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.35rem;">
                  Official Email Address (Your Merchant Portal Login) *
                </label>
                <input
                  type="email"
                  v-model="form.contact_email"
                  required
                  placeholder="admin@yourbusiness.pk"
                  style="width: 100%; padding: 0.65rem 0.85rem; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; color: #fff; font-size: 0.9375rem;"
                />
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <div style="padding-top: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1);">
            <button
              type="submit"
              :disabled="isSubmitting"
              style="width: 100%; padding: 0.875rem; background: #10b981; border: none; border-radius: 8px; color: #fff; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background 0.15s; display: flex; align-items: center; justify-content: center; gap: 0.5rem;"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
              </svg>
              <span>{{ isSubmitting ? 'Submitting Application...' : 'Submit Application & Verify FBR ATL' }}</span>
            </button>
            <div style="text-align: center; color: #64748b; font-size: 0.75rem; margin-top: 0.75rem;">
              Already registered? <a href="/login" style="color: #60a5fa; text-decoration: underline;">Merchant Login</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  plans: {
    type: Array,
    default: () => [],
  },
});

const isSubmitting = ref(false);

const form = ref({
  business_name: '',
  trade_name: '',
  sector: 'general_retail',
  pos_terminal_count: 1,
  province: 'punjab',
  city: '',
  address: '',
  ntn: '',
  strn: '',
  contact_name: '',
  contact_phone: '',
  contact_email: '',
  requested_plan_id: props.plans[0]?.id || '',
});

function submitForm() {
  isSubmitting.value = true;
  router.post('/apply', form.value, {
    onFinish: () => { isSubmitting.value = false; },
  });
}
</script>

