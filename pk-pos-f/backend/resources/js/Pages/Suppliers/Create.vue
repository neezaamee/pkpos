<template>
  <AdminLayout title="Register Supplier Account">
    <div style="max-width: 800px; margin: 0 auto;">
      <!-- Header -->
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
        <div>
          <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-primary); margin: 0;">
            Register Supplier / Distributor
          </h2>
          <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
            Set up vendor profile, statutory NTN / STRN tax compliance, and credit payment terms
          </div>
        </div>
        <Link href="/suppliers" class="btn btn-outline" style="padding: 0.4rem 0.85rem;">
          &larr; Back to Suppliers
        </Link>
      </div>

      <div class="card" style="padding: 2rem;">
        <form @submit.prevent="submit">
          <!-- Primary Identification -->
          <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 1rem;">
            1. Supplier &amp; Company Details
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Supplier / Company Name <span style="color: #ef4444;">*</span>
              </label>
              <input
                v-model="form.name"
                type="text"
                class="form-control"
                placeholder="e.g. Al-Rehman Distributors / Unilever PK"
                required
              />
              <div v-if="form.errors.name" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
                {{ form.errors.name }}
              </div>
            </div>

            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Contact Person / Sales Agent
              </label>
              <input
                v-model="form.contact_person"
                type="text"
                class="form-control"
                placeholder="e.g. Muhammad Aslam (Territory Manager)"
              />
              <div v-if="form.errors.contact_person" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
                {{ form.errors.contact_person }}
              </div>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Official Mobile / Phone <span style="color: #ef4444;">*</span>
              </label>
              <input
                v-model="form.phone"
                type="text"
                class="form-control"
                placeholder="03001234567 or 042-35123456"
                required
              />
              <div v-if="form.errors.phone" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
                {{ form.errors.phone }}
              </div>
            </div>

            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Email Address
              </label>
              <input
                v-model="form.email"
                type="email"
                class="form-control"
                placeholder="billing@supplier.com"
              />
              <div v-if="form.errors.email" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
                {{ form.errors.email }}
              </div>
            </div>
          </div>

          <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;" />

          <!-- Statutory & Tax Identification -->
          <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 1rem;">
            2. Statutory Tax &amp; Withholding Details
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                National Tax No. (NTN)
              </label>
              <input
                v-model="form.ntn"
                type="text"
                class="form-control"
                placeholder="1234567-8"
              />
              <div v-if="form.errors.ntn" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
                {{ form.errors.ntn }}
              </div>
            </div>

            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Sales Tax Reg No. (STRN)
              </label>
              <input
                v-model="form.strn"
                type="text"
                class="form-control"
                placeholder="12-34-5678-001-99"
              />
              <div v-if="form.errors.strn" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
                {{ form.errors.strn }}
              </div>
            </div>

            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                CNIC (Proprietor)
              </label>
              <input
                v-model="form.cnic"
                type="text"
                class="form-control"
                placeholder="35202-1234567-1"
              />
              <div v-if="form.errors.cnic" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
                {{ form.errors.cnic }}
              </div>
            </div>
          </div>

          <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;" />

          <!-- Terms & Address -->
          <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 1rem;">
            3. Credit Terms &amp; Warehouse Location
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Payment Terms (Days)
              </label>
              <select v-model="form.payment_terms_days" class="form-control">
                <option :value="0">0 Days (Cash on Delivery / Immediate)</option>
                <option :value="7">Net 7 Days</option>
                <option :value="15">Net 15 Days</option>
                <option :value="30">Net 30 Days (Standard Monthly)</option>
                <option :value="45">Net 45 Days</option>
                <option :value="60">Net 60 Days</option>
              </select>
              <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem;">
                Expected repayment window after Goods Received Note (GRN)
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
                placeholder="e.g. Lahore, Karachi, Rawalpindi"
              />
            </div>
          </div>

          <div style="margin-bottom: 1.25rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Physical Warehouse / Office Address
            </label>
            <input
              v-model="form.address"
              type="text"
              class="form-control"
              placeholder="e.g. Suite 402, Shah Alam Market, Circular Road, Lahore"
            />
          </div>

          <div style="margin-bottom: 1.75rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Internal Notes / Bank Account Details
            </label>
            <textarea
              v-model="form.notes"
              class="form-control"
              rows="3"
              placeholder="Add bank account number, IBAN, authorized representative details, or procurement remarks..."
            ></textarea>
          </div>

          <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem;">
            <Link href="/suppliers" class="btn btn-secondary">
              Cancel
            </Link>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="form.processing"
              style="min-width: 150px;"
            >
              <span v-if="form.processing">Saving Supplier...</span>
              <span v-else>Register Supplier</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const form = useForm({
  name: '',
  contact_person: '',
  phone: '',
  email: '',
  cnic: '',
  ntn: '',
  strn: '',
  address: '',
  city: '',
  payment_terms_days: 0,
  notes: '',
})

function submit() {
  form.post('/suppliers')
}
</script>
