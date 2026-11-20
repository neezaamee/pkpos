<script setup>
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  prescription: {
    type: Object,
    required: true,
  },
})

const formatPKR = (minor) => {
  if (!minor) return 'Rs. 0.00'
  return 'Rs. ' + (minor / 100).toLocaleString('en-PK', { minimumFractionDigits: 2 })
}
</script>

<template>
  <AdminLayout>
    <div class="page-container" style="padding: 1.5rem; max-width: 1200px; margin: 0 auto;">
      <!-- Page Header -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
          <Link href="/pharmacy/prescriptions" style="color: #38bdf8; text-decoration: none; font-size: 0.85rem; display: flex; align-items: center; gap: 0.25rem; margin-bottom: 0.5rem;">
            &larr; Back to Prescriptions
          </Link>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0;">
              Prescription #{{ prescription.id.slice(0, 8) }}
            </h1>
            <span style="font-size: 0.8rem; background: rgba(2, 132, 199, 0.2); color: #38bdf8; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: 700;">
              Dispensed
            </span>
          </div>
          <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0.25rem 0 0 0;">
            Prescribed on {{ prescription.prescription_date }} &bull; Dispensed via Sale #{{ prescription.sale?.sale_number }}
          </p>
        </div>

        <button
          type="button"
          onclick="window.print()"
          style="padding: 0.6rem 1.25rem; background: #334155; color: #fff; border: 1px solid #475569; border-radius: 8px; font-weight: 600; cursor: pointer;"
        >
          <span>🖨️</span> Print Prescription Record
        </button>
      </div>

      <!-- Detail Cards Grid -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <!-- Patient Details -->
        <div class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem;">
          <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin: 0 0 1rem 0; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            Patient Details
          </h3>
          <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.875rem;">
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Patient Name:</span>
              <strong style="color: var(--text-primary);">{{ prescription.patient_name }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Contact Phone:</span>
              <span style="font-family: monospace; color: var(--text-primary);">{{ prescription.patient_phone || 'N/A' }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">CNIC / B-Form:</span>
              <span style="font-family: monospace; color: var(--text-primary);">{{ prescription.patient_cnic || 'N/A' }}</span>
            </div>
            <div v-if="prescription.diagnosis_notes" style="border-top: 1px solid var(--border-color); padding-top: 0.5rem;">
              <span style="color: var(--text-secondary); display: block; margin-bottom: 0.25rem;">Diagnosis / Instructions:</span>
              <p style="color: var(--text-primary); margin: 0; font-style: italic;">{{ prescription.diagnosis_notes }}</p>
            </div>
          </div>
        </div>

        <!-- Doctor & Clinic Details -->
        <div class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem;">
          <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin: 0 0 1rem 0; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            Prescribing Doctor Details
          </h3>
          <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.875rem;">
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Doctor Name:</span>
              <strong style="color: var(--text-primary);">{{ prescription.doctor_name }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">PMDC Registration #:</span>
              <span style="font-family: monospace; color: #10b981; font-weight: 700;">{{ prescription.doctor_pmdc_number }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Hospital / Clinic:</span>
              <span style="color: var(--text-primary);">{{ prescription.hospital_clinic_name || 'Private Practice' }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Dispensing Pharmacist:</span>
              <span style="color: var(--text-primary);">{{ prescription.dispensed_by_user?.name || 'Staff Pharmacist' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Dispensed Medicines Table -->
      <div class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden;">
        <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--bg-elevated);">
          <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0;">
            Dispensed Medicines &amp; Batch Allocations
          </h3>
        </div>

        <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
          <thead>
            <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-elevated); color: var(--text-secondary); text-align: left;">
              <th style="padding: 0.75rem 1rem;">Medicine</th>
              <th style="padding: 0.75rem 1rem;">Generic / Classification</th>
              <th style="padding: 0.75rem 1rem;">Batch #</th>
              <th style="padding: 0.75rem 1rem;">Expiry Date</th>
              <th style="padding: 0.75rem 1rem; text-align: right;">Quantity</th>
              <th style="padding: 0.75rem 1rem; text-align: right;">Line Total</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in prescription.sale?.items || []"
              :key="item.id"
              style="border-bottom: 1px solid var(--border-color);"
            >
              <td style="padding: 0.85rem 1rem; font-weight: 700; color: var(--text-primary);">
                {{ item.product_name }}
              </td>

              <td style="padding: 0.85rem 1rem;">
                <div style="color: var(--text-primary);">{{ item.product?.generic_name || 'Standard' }}</div>
                <div style="font-size: 0.75rem;">
                  <span v-if="item.product?.is_narcotic" style="color: #ef4444; font-weight: 700;">Schedule D Controlled</span>
                  <span v-else-if="item.product?.is_prescription_required" style="color: #38bdf8;">Prescription Drug</span>
                  <span v-else style="color: var(--text-muted);">OTC</span>
                </div>
              </td>

              <td style="padding: 0.85rem 1rem; font-family: monospace; font-weight: 700; color: #38bdf8;">
                {{ item.batch_number || 'N/A' }}
              </td>

              <td style="padding: 0.85rem 1rem; font-family: monospace; color: var(--text-secondary);">
                {{ item.expiry_date || 'N/A' }}
              </td>

              <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 700; font-family: monospace; color: var(--text-primary);">
                {{ item.quantity }} {{ item.unit_code }}
              </td>

              <td style="padding: 0.85rem 1rem; text-align: right; font-family: monospace; font-weight: 700; color: var(--text-primary);">
                {{ formatPKR(item.line_total_minor) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AdminLayout>
</template>
