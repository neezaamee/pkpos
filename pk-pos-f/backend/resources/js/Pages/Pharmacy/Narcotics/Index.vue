<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  entries: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({ search: '', from: '', to: '', product_id: '' }),
  },
  narcoticProducts: {
    type: Array,
    default: () => [],
  },
})

const search = ref(props.filters.search || '')
const fromDate = ref(props.filters.from || '')
const toDate = ref(props.filters.to || '')
const selectedProduct = ref(props.filters.product_id || '')

const applyFilters = () => {
  router.get('/pharmacy/narcotics', {
    search: search.value,
    from: fromDate.value,
    to: toDate.value,
    product_id: selectedProduct.value,
  }, { preserveState: true, replace: true })
}

const printRegister = () => {
  window.print()
}
</script>

<template>
  <AdminLayout>
    <div class="page-container" style="padding: 1.5rem; max-width: 1400px; margin: 0 auto;">
      <!-- Page Header -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
          <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <span style="font-size: 0.8rem; font-weight: 700; color: #ef4444; text-transform: uppercase; letter-spacing: 0.05em;">Schedule D Register</span>
            <span style="color: var(--text-muted);">&bull;</span>
            <span style="font-size: 0.8rem; color: var(--text-muted);">DRAP Statutory Compliance</span>
          </div>
          <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0 0 0.25rem 0;">
            Controlled Substances &amp; Narcotics Dispensing Register
          </h1>
          <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;">
            Mandatory Drug Rules Form 7 inspection register capturing prescribing doctor PMDC numbers, patient CNIC, and batch allocations.
          </p>
        </div>

        <button
          type="button"
          @click="printRegister"
          style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; font-weight: 600; border-radius: 8px; background: #334155; color: #fff; border: 1px solid #475569; cursor: pointer;"
        >
          <span>🖨️</span> Print Inspection Register
        </button>
      </div>

      <!-- Filters Toolbar -->
      <div class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem;">
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1.5fr auto; gap: 0.75rem; align-items: end;">
          <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 0.25rem; text-transform: uppercase;">
              Search Patient / Doctor PMDC / Batch
            </label>
            <input
              type="text"
              v-model="search"
              placeholder="e.g. 12345-P or 35202-..."
              @keydown.enter="applyFilters"
              style="width: 100%; padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.85rem;"
            />
          </div>

          <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 0.25rem; text-transform: uppercase;">
              From Date
            </label>
            <input
              type="date"
              v-model="fromDate"
              style="width: 100%; padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.85rem;"
            />
          </div>

          <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 0.25rem; text-transform: uppercase;">
              To Date
            </label>
            <input
              type="date"
              v-model="toDate"
              style="width: 100%; padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.85rem;"
            />
          </div>

          <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 0.25rem; text-transform: uppercase;">
              Controlled Drug
            </label>
            <select
              v-model="selectedProduct"
              style="width: 100%; padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.85rem;"
            >
              <option value="">All Controlled Substances</option>
              <option v-for="p in narcoticProducts" :key="p.id" :value="p.id">
                {{ p.name }} ({{ p.generic_name }})
              </option>
            </select>
          </div>

          <button
            type="button"
            @click="applyFilters"
            style="padding: 0.5rem 1.25rem; background: #0284c7; color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; height: 38px;"
          >
            Filter
          </button>
        </div>
      </div>

      <!-- Statutory Register Table -->
      <div class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
          <thead>
            <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-elevated); color: var(--text-secondary); text-align: left;">
              <th style="padding: 0.75rem 1rem;">Dispensed At</th>
              <th style="padding: 0.75rem 1rem;">Controlled Drug</th>
              <th style="padding: 0.75rem 1rem;">Batch #</th>
              <th style="padding: 0.75rem 1rem; text-align: right;">Qty Dispensed</th>
              <th style="padding: 0.75rem 1rem;">Patient Details</th>
              <th style="padding: 0.75rem 1rem;">Prescribing Doctor</th>
              <th style="padding: 0.75rem 1rem;">Pharmacist / Invoice</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="e in entries.data"
              :key="e.id"
              style="border-bottom: 1px solid var(--border-color);"
            >
              <td style="padding: 0.85rem 1rem; font-family: monospace; color: var(--text-secondary);">
                {{ new Date(e.dispensed_at).toLocaleString('en-GB') }}
              </td>

              <td style="padding: 0.85rem 1rem;">
                <div style="font-weight: 700; color: #ef4444;">
                  {{ e.product?.name }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                  {{ e.product?.generic_name }}
                </div>
              </td>

              <td style="padding: 0.85rem 1rem; font-family: monospace; font-weight: 700; color: #38bdf8;">
                {{ e.batch_number }}
              </td>

              <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 800; font-family: monospace; color: var(--text-primary);">
                {{ e.quantity_dispensed }} {{ e.unit_code }}
              </td>

              <td style="padding: 0.85rem 1rem;">
                <div style="font-weight: 600; color: var(--text-primary);">
                  {{ e.patient_name }}
                </div>
                <div v-if="e.patient_cnic" style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                  CNIC: {{ e.patient_cnic }}
                </div>
              </td>

              <td style="padding: 0.85rem 1rem;">
                <div style="font-weight: 600; color: var(--text-primary);">
                  {{ e.doctor_name }}
                </div>
                <div style="font-size: 0.75rem; color: #10b981; font-family: monospace; font-weight: 700;">
                  PMDC: {{ e.doctor_pmdc_number }}
                </div>
              </td>

              <td style="padding: 0.85rem 1rem;">
                <div style="color: var(--text-primary);">
                  {{ e.dispensed_by_user?.name || 'Staff' }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                  {{ e.sale?.sale_number }}
                </div>
              </td>
            </tr>

            <tr v-if="entries.data.length === 0">
              <td colspan="7" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                No controlled drug dispensations logged for the selected period.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AdminLayout>
</template>
