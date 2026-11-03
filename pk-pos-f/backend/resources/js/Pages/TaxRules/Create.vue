<template>
  <AdminLayout title="Add Tax Rule">
    <div style="max-width: 700px; margin: 0 auto;">
      <div class="form-card" style="max-width: 100%;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
          <div>
            <h3 style="font-size: 1.2rem; font-weight: 700;">New Tax Rule</h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);">
              Configure province and payment-specific GST/SST rates
            </span>
          </div>
          <Link href="/tax-rules" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">
            ← Back to Rules
          </Link>
        </div>

        <form @submit.prevent="submit">
          <!-- Name -->
          <div class="form-group">
            <label class="form-label" for="name">Rule Name *</label>
            <input
              id="name"
              v-model="form.name"
              type="text"
              class="form-input"
              :class="{ 'is-invalid': form.errors.name }"
              placeholder="e.g. Federal Standard Sales Tax on Goods"
              required
            />
            <div v-if="form.errors.name" class="invalid-feedback">
              {{ form.errors.name }}
            </div>
          </div>

          <!-- Category & Authority -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label class="form-label" for="tax_category">Tax Category *</label>
              <input
                id="tax_category"
                v-model="form.tax_category"
                type="text"
                class="form-input"
                :class="{ 'is-invalid': form.errors.tax_category }"
                placeholder="e.g. general_goods, restaurant, services"
                required
              />
              <div v-if="form.errors.tax_category" class="invalid-feedback">
                {{ form.errors.tax_category }}
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="authority">Revenue Authority *</label>
              <select
                id="authority"
                v-model="form.authority"
                class="form-input"
                :class="{ 'is-invalid': form.errors.authority }"
                required
              >
                <option value="fbr">FBR (Federal Board of Revenue)</option>
                <option value="pra">PRA (Punjab Revenue Authority)</option>
                <option value="srb">SRB (Sindh Revenue Board)</option>
                <option value="kpra">KPRA (Khyber Pakhtunkhwa Revenue Authority)</option>
                <option value="bra">BRA (Balochistan Revenue Authority)</option>
              </select>
              <div v-if="form.errors.authority" class="invalid-feedback">
                {{ form.errors.authority }}
              </div>
            </div>
          </div>

          <!-- Province & Payment Method -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label class="form-label" for="province">Jurisdiction Province *</label>
              <select
                id="province"
                v-model="form.province"
                class="form-input"
                :class="{ 'is-invalid': form.errors.province }"
                required
              >
                <option value="federal">Federal (All Pakistan Goods)</option>
                <option value="punjab">Punjab</option>
                <option value="sindh">Sindh</option>
                <option value="kp">Khyber Pakhtunkhwa (KP)</option>
                <option value="balochistan">Balochistan</option>
              </select>
              <div v-if="form.errors.province" class="invalid-feedback">
                {{ form.errors.province }}
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="payment_method">Payment Method Target *</label>
              <select
                id="payment_method"
                v-model="form.payment_method"
                class="form-input"
                :class="{ 'is-invalid': form.errors.payment_method }"
                required
              >
                <option value="any">Any / All Payment Methods</option>
                <option value="cash">Cash Only</option>
                <option value="card">Card / Digital Payment (Discounted)</option>
                <option value="raast">Raast Instant Payment</option>
                <option value="wallet">Mobile Wallet</option>
              </select>
              <span class="form-hint">Used for provincial digital payment tax incentives (e.g. 5% on card vs 16% on cash).</span>
              <div v-if="form.errors.payment_method" class="invalid-feedback">
                {{ form.errors.payment_method }}
              </div>
            </div>
          </div>

          <!-- Rate Percentage -->
          <div class="form-group">
            <label class="form-label" for="rate_percent">Tax Rate Percentage (%) *</label>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <input
                id="rate_percent"
                v-model="form.rate_percent"
                type="number"
                step="0.01"
                min="0"
                max="100"
                class="form-input"
                :class="{ 'is-invalid': form.errors.rate_percent }"
                placeholder="e.g. 18.00"
                required
              />
              <span style="font-size: 1.1rem; font-weight: 700; color: var(--text-muted);">%</span>
            </div>
            <span class="form-hint" style="color: var(--accent-cyan);">
              Entered as %, stored as basis points (e.g. 18.00% = 1800 basis points).
            </span>
            <div v-if="form.errors.rate_percent" class="invalid-feedback">
              {{ form.errors.rate_percent }}
            </div>
          </div>

          <!-- Effective Dates -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label class="form-label" for="effective_from">Effective From Date *</label>
              <input
                id="effective_from"
                v-model="form.effective_from"
                type="date"
                class="form-input"
                :class="{ 'is-invalid': form.errors.effective_from }"
                required
              />
              <div v-if="form.errors.effective_from" class="invalid-feedback">
                {{ form.errors.effective_from }}
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="effective_to">Effective Until Date (Optional)</label>
              <input
                id="effective_to"
                v-model="form.effective_to"
                type="date"
                class="form-input"
                :class="{ 'is-invalid': form.errors.effective_to }"
              />
              <span class="form-hint">Leave blank for permanent / ongoing tax rules.</span>
              <div v-if="form.errors.effective_to" class="invalid-feedback">
                {{ form.errors.effective_to }}
              </div>
            </div>
          </div>

          <!-- Submit Buttons -->
          <div style="margin-top: 2rem; display: flex; align-items: center; justify-content: flex-end; gap: 1rem;">
            <Link href="/tax-rules" class="btn btn-secondary">
              Cancel
            </Link>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="form.processing"
            >
              {{ form.processing ? 'Creating Rule...' : 'Save Tax Rule' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const form = useForm({
  name: '',
  tax_category: 'general_goods',
  province: 'federal',
  payment_method: 'any',
  rate_percent: '18.00',
  authority: 'fbr',
  effective_from: new Date().toISOString().substring(0, 10),
  effective_to: '',
  is_active: true,
});

function submit() {
  form.post('/tax-rules');
}
</script>
