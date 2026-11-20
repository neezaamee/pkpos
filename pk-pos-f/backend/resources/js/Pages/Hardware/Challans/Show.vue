<template>
  <AdminLayout :title="`Delivery Challan: ${challan.challan_number}`">
    <!-- Non-printable top navigation -->
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div style="display: flex; align-items: center; gap: 0.5rem;">
        <Link href="/hardware/challans" class="btn btn-outline" style="font-size: 0.85rem;">
          &larr; Back to Challans
        </Link>
        <span
          class="badge"
          :style="{
            background: challan.status === 'delivered' ? 'rgba(16, 185, 129, 0.15)' : challan.status === 'dispatched' ? 'rgba(59, 130, 246, 0.15)' : 'rgba(245, 158, 11, 0.15)',
            color: challan.status === 'delivered' ? '#10b981' : challan.status === 'dispatched' ? '#3b82f6' : '#f59e0b',
            padding: '0.25rem 0.6rem',
            borderRadius: '4px',
            fontSize: '0.8rem',
            fontWeight: 700,
            textTransform: 'uppercase'
          }"
        >
          Status: {{ challan.status }}
        </span>
      </div>

      <div style="display: flex; gap: 0.5rem;">
        <button
          v-if="challan.status === 'dispatched'"
          @click="markDelivered"
          class="btn btn-outline"
          style="color: #10b981; border-color: rgba(16, 185, 129, 0.4);"
        >
          ✓ Mark Delivered at Site
        </button>
        <button @click="printChallan" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 6 2 18 2 18 9"></polyline>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <rect x="6" y="14" width="12" height="8"></rect>
          </svg>
          <span>Print Gate Pass &amp; Challan</span>
        </button>
      </div>
    </div>

    <!-- Printable Biltee Document -->
    <div class="printable-challan card" style="background: #ffffff; color: #111827; padding: 2.5rem; max-width: 850px; margin: 0 auto; border: 2px solid #000; border-radius: 8px;">
      <!-- Header -->
      <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 1rem; margin-bottom: 1.25rem;">
        <div>
          <h2 style="font-size: 1.6rem; font-weight: 900; margin: 0; color: #000; text-transform: uppercase; letter-spacing: 0.05em;">
            {{ challan.tenant?.business_name || challan.tenant?.name || 'BUILDING MATERIALS & HARDWARE' }}
          </h2>
          <div style="font-size: 0.85rem; color: #374151; margin-top: 0.25rem;">
            {{ challan.location?.address || 'Main Warehouse & Distribution Center' }}
          </div>
          <div style="font-size: 0.8rem; color: #4b5563;">
            <span v-if="challan.tenant?.ntn">NTN: <strong>{{ challan.tenant.ntn }}</strong> | </span>
            <span v-if="challan.tenant?.strn">STRN: <strong>{{ challan.tenant.strn }}</strong> | </span>
            <span>Phone: {{ challan.location?.phone || challan.tenant?.phone || '042-111-222-333' }}</span>
          </div>
        </div>

        <div style="text-align: right;">
          <div style="background: #000; color: #fff; padding: 0.35rem 0.75rem; font-weight: 900; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block;">
            DELIVERY CHALLAN &amp; GATE PASS
          </div>
          <div style="font-family: monospace; font-size: 1.1rem; font-weight: 900; margin-top: 0.5rem; color: #000;">
            {{ challan.challan_number }}
          </div>
          <div style="font-family: monospace; font-size: 0.9rem; font-weight: 700; color: #d97706;">
            Gate Pass: {{ challan.gate_pass_number }}
          </div>
          <div style="font-size: 0.8rem; color: #4b5563; margin-top: 0.25rem;">
            Date: {{ new Date(challan.created_at).toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' }) }}
          </div>
        </div>
      </div>

      <!-- Consignee & Transport Meta Grid -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; border-bottom: 2px solid #000; padding-bottom: 1.25rem; margin-bottom: 1.25rem; font-size: 0.85rem;">
        <!-- Left: Destination & Contractor -->
        <div style="border-right: 1px solid #d1d5db; padding-right: 1rem;">
          <div style="font-weight: 800; text-transform: uppercase; font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">
            Consignee / Site Destination
          </div>
          <div style="font-size: 1.05rem; font-weight: 800; color: #000;">
            {{ challan.contractor_project?.name || challan.site_destination || 'Direct Site Delivery' }}
          </div>
          <div style="margin-top: 0.25rem; color: #374151;">
            Contractor: <strong>{{ challan.contractor_project?.customer?.name || challan.sale?.customer?.name || 'Walk-in / Cash' }}</strong>
          </div>
          <div v-if="challan.contractor_project?.plot_number" style="color: #374151;">
            Plot / Sector: <strong>{{ challan.contractor_project.plot_number }}</strong>
          </div>
          <div style="color: #4b5563; margin-top: 0.25rem;">
            Site Address: {{ challan.site_destination || challan.contractor_project?.site_address || 'As per instructions' }}
          </div>
          <div v-if="challan.contractor_project?.supervisor_name" style="margin-top: 0.35rem; color: #1e3a8a; font-weight: 700;">
            Site Supervisor (Munshi): {{ challan.contractor_project.supervisor_name }} ({{ challan.contractor_project.supervisor_phone || 'No Phone' }})
          </div>
        </div>

        <!-- Right: Vehicle & Driver (Logistics) -->
        <div>
          <div style="font-weight: 800; text-transform: uppercase; font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">
            Truck Transport &amp; Freight Details
          </div>
          <div style="display: grid; grid-template-columns: auto 1fr; gap: 0.35rem 0.75rem;">
            <span style="color: #4b5563;">Vehicle / Truck #:</span>
            <strong style="font-family: monospace; font-size: 1rem; color: #000;">{{ challan.vehicle_number || 'N/A' }}</strong>

            <span style="color: #4b5563;">Driver Name:</span>
            <strong>{{ challan.driver_name || 'Unassigned' }}</strong>

            <span style="color: #4b5563;">Driver Phone:</span>
            <span>{{ challan.driver_phone || 'N/A' }}</span>

            <span style="color: #4b5563;">Transporter:</span>
            <span>{{ challan.transporter_name || 'Self / Own Fleet' }}</span>

            <span style="color: #4b5563;">Freight Terms:</span>
            <strong style="text-transform: uppercase; color: #000;">
              {{ challan.freight_terms === 'paid_by_store' ? 'PAID BY STORE' : 'TO-PAY (COLLECT FROM SITE)' }}
            </strong>

            <span v-if="challan.freight_amount_minor > 0" style="color: #4b5563;">Freight Amount:</span>
            <span v-if="challan.freight_amount_minor > 0" style="font-weight: 700;">
              Rs. {{ (challan.freight_amount_minor / 100).toLocaleString('en-PK') }}
            </span>
          </div>
        </div>
      </div>

      <!-- Dispatched Items Table -->
      <table style="width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: 0.85rem;">
        <thead>
          <tr style="border-bottom: 2px solid #000; border-top: 1px solid #000; text-align: left; background: #f3f4f6;">
            <th style="padding: 0.6rem 0.5rem; width: 40px; font-weight: 800; text-transform: uppercase;">#</th>
            <th style="padding: 0.6rem 0.75rem; font-weight: 800; text-transform: uppercase;">Material Description</th>
            <th style="padding: 0.6rem 0.75rem; font-weight: 800; text-transform: uppercase; text-align: center;">Dispatched Qty</th>
            <th style="padding: 0.6rem 0.75rem; font-weight: 800; text-transform: uppercase; text-align: center;">Unit</th>
            <th style="padding: 0.6rem 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Est. Gross Weight</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, idx) in challan.items" :key="item.id" style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 0.6rem 0.5rem; font-weight: 700;">{{ idx + 1 }}</td>
            <td style="padding: 0.6rem 0.75rem;">
              <div style="font-weight: 700; color: #000;">{{ item.item_description || item.product?.name }}</div>
              <div v-if="item.remarks" style="font-size: 0.75rem; color: #6b7280;">{{ item.remarks }}</div>
            </td>
            <td style="padding: 0.6rem 0.75rem; text-align: center; font-weight: 800; font-family: monospace; font-size: 0.95rem;">
              {{ item.quantity_dispatched }}
            </td>
            <td style="padding: 0.6rem 0.75rem; text-align: center; text-transform: uppercase; font-weight: 600;">
              {{ item.unit || item.product?.uom || 'Unit' }}
            </td>
            <td style="padding: 0.6rem 0.75rem; text-align: right; font-weight: 700; font-family: monospace;">
              {{ item.weight_kg ? Number(item.weight_kg).toLocaleString('en-PK', { maximumFractionDigits: 1 }) + ' KG' : '—' }}
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr style="border-top: 2px solid #000; font-weight: 900;">
            <td colspan="4" style="padding: 0.75rem 0.5rem; text-align: right; text-transform: uppercase;">
              Total Estimated Dispatch Weight:
            </td>
            <td style="padding: 0.75rem 0.75rem; text-align: right; font-family: monospace; font-size: 1rem; color: #000;">
              {{ calculateTotalWeight() }}
            </td>
          </tr>
        </tfoot>
      </table>

      <!-- Special Instructions / Notes -->
      <div v-if="challan.notes" style="background: #f9fafb; border: 1px dashed #9ca3af; padding: 0.75rem; font-size: 0.8rem; margin-bottom: 2rem;">
        <strong>Special Instructions:</strong> {{ challan.notes }}
      </div>

      <!-- Three-Way Signatures & Acceptance Box -->
      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-top: 3rem; text-align: center; font-size: 0.8rem;">
        <div>
          <div style="border-bottom: 1px solid #000; height: 45px; margin-bottom: 0.5rem;"></div>
          <div style="font-weight: 800; text-transform: uppercase;">Warehouse / Dispatcher</div>
          <div style="font-size: 0.75rem; color: #6b7280;">Goods loaded &amp; verified</div>
        </div>

        <div>
          <div style="border-bottom: 1px solid #000; height: 45px; margin-bottom: 0.5rem;"></div>
          <div style="font-weight: 800; text-transform: uppercase;">Driver Acceptance</div>
          <div style="font-size: 0.75rem; color: #6b7280;">Received in good condition</div>
        </div>

        <div>
          <div style="border-bottom: 1px solid #000; height: 45px; margin-bottom: 0.5rem;"></div>
          <div style="font-weight: 800; text-transform: uppercase;">Site Munshi / Receiver</div>
          <div style="font-size: 0.75rem; color: #6b7280;">Sign &amp; Site Rubber Stamp</div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  challan: Object,
});

function calculateTotalWeight() {
  if (!props.challan?.items || props.challan.items.length === 0) return '0 KG';
  const totalKg = props.challan.items.reduce((sum, item) => sum + (Number(item.weight_kg) || 0), 0);
  if (totalKg >= 1000) {
    const tons = (totalKg / 1000).toFixed(2);
    return `${totalKg.toLocaleString('en-PK', { maximumFractionDigits: 1 })} KG (${tons} Tons)`;
  }
  return `${totalKg.toLocaleString('en-PK', { maximumFractionDigits: 1 })} KG`;
}

function printChallan() {
  window.print();
}

function markDelivered() {
  router.post(`/hardware/challans/${props.challan.id}/status`, { status: 'delivered' });
}
</script>

<style>
@media print {
  .no-print {
    display: none !important;
  }
  .printable-challan {
    border: none !important;
    padding: 0 !important;
    max-width: 100% !important;
  }
  body {
    background: #ffffff !important;
    color: #000000 !important;
  }
}
</style>
