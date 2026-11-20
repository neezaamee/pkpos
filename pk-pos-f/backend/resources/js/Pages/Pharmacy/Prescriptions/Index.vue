<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  prescriptions: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({ search: '' }),
  },
})

const search = ref(props.filters.search || '')

const applySearch = () => {
  router.get('/pharmacy/prescriptions', {
    search: search.value,
  }, { preserveState: true, replace: true })
}
</script>

<template>
  <AdminLayout>
    <div class="page-container" style="padding: 1.5rem; max-width: 1400px; margin: 0 auto;">
      <!-- Page Header -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
          <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <span style="font-size: 0.8rem; font-weight: 700; color: #0284c7; text-transform: uppercase; letter-spacing: 0.05em;">Doctor Prescriptions</span>
            <span style="color: var(--text-muted);">&bull;</span>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Rx Dispensation Trail</span>
          </div>
          <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0 0 0.25rem 0;">
            Prescription Audit Log
          </h1>
          <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;">
            Central repository of doctor prescriptions, PMDC registration numbers, patient details, and linked POS invoices.
          </p>
        </div>

        <div style="display: flex; gap: 0.5rem;">
          <input
            type="text"
            v-model="search"
            placeholder="Search Patient, Doctor PMDC, Sale #..."
            @keydown.enter="applySearch"
            style="padding: 0.55rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.85rem; width: 300px;"
          />
          <button
            type="button"
            @click="applySearch"
            style="padding: 0.55rem 1rem; background: #0284c7; color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;"
          >
            Search
          </button>
        </div>
      </div>

      <!-- Prescriptions Table -->
      <div class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
          <thead>
            <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-elevated); color: var(--text-secondary); text-align: left;">
              <th style="padding: 0.75rem 1rem;">Date</th>
              <th style="padding: 0.75rem 1rem;">Patient Name &amp; Contact</th>
              <th style="padding: 0.75rem 1rem;">Prescribing Doctor</th>
              <th style="padding: 0.75rem 1rem;">Doctor PMDC #</th>
              <th style="padding: 0.75rem 1rem;">Hospital / Clinic</th>
              <th style="padding: 0.75rem 1rem;">POS Invoice</th>
              <th style="padding: 0.75rem 1rem; text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="p in prescriptions.data"
              :key="p.id"
              style="border-bottom: 1px solid var(--border-color);"
            >
              <td style="padding: 0.85rem 1rem; font-family: monospace; color: var(--text-secondary);">
                {{ p.prescription_date }}
              </td>

              <td style="padding: 0.85rem 1rem;">
                <div style="font-weight: 700; color: var(--text-primary);">
                  {{ p.patient_name }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                  {{ p.patient_phone || 'No phone' }} <span v-if="p.patient_cnic">&bull; CNIC: {{ p.patient_cnic }}</span>
                </div>
              </td>

              <td style="padding: 0.85rem 1rem; font-weight: 600; color: var(--text-primary);">
                {{ p.doctor_name }}
              </td>

              <td style="padding: 0.85rem 1rem;">
                <span style="padding: 0.2rem 0.5rem; background: rgba(16, 185, 129, 0.15); color: #10b981; border-radius: 4px; font-weight: 700; font-family: monospace;">
                  {{ p.doctor_pmdc_number }}
                </span>
              </td>

              <td style="padding: 0.85rem 1rem; color: var(--text-secondary);">
                {{ p.hospital_clinic_name || 'Private Practice' }}
              </td>

              <td style="padding: 0.85rem 1rem; font-family: monospace; color: #38bdf8;">
                {{ p.sale?.sale_number }}
              </td>

              <td style="padding: 0.85rem 1rem; text-align: right;">
                <Link
                  :href="`/pharmacy/prescriptions/${p.id}`"
                  style="color: #38bdf8; text-decoration: none; font-weight: 600; padding: 0.35rem 0.65rem; border: 1px solid #0284c7; border-radius: 4px;"
                >
                  View Details &rarr;
                </Link>
              </td>
            </tr>

            <tr v-if="prescriptions.data.length === 0">
              <td colspan="7" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                No prescriptions logged yet.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AdminLayout>
</template>
