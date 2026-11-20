<template>
  <AdminLayout :title="`Site Ledger: ${project.name}`">
    <!-- Top Action Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
          <Link href="/hardware/projects" class="btn btn-sm btn-outline" style="font-size: 0.75rem; padding: 0.2rem 0.5rem;">
            &larr; Back to Projects
          </Link>
          <span
            class="badge"
            :style="{
              background: project.status === 'active' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(59, 130, 246, 0.15)',
              color: project.status === 'active' ? '#10b981' : '#3b82f6',
              padding: '0.2rem 0.5rem',
              borderRadius: '4px',
              fontSize: '0.75rem',
              fontWeight: 700,
              textTransform: 'uppercase'
            }"
          >
            {{ project.status }}
          </span>
        </div>
        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0;">
          {{ project.name }}
        </h1>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
          Contractor: <strong style="color: var(--text-primary);">{{ project.customer?.name }}</strong> ({{ project.customer?.phone || 'No phone' }})
        </p>
      </div>

      <div style="display: flex; gap: 0.5rem;">
        <button @click="printStatement" class="btn btn-outline" style="display: flex; align-items: center; gap: 0.5rem;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 6 2 18 2 18 9"></polyline>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <rect x="6" y="14" width="12" height="8"></rect>
          </svg>
          <span>Print Site Statement</span>
        </button>
      </div>
    </div>

    <!-- Summary Metrics Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card" style="background: linear-gradient(135deg, rgba(249, 115, 22, 0.08) 0%, rgba(234, 88, 12, 0.05) 100%); border: 1px solid rgba(249, 115, 22, 0.25);">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #f97316; letter-spacing: 0.05em;">
          Total Billed to Site
        </div>
        <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
          {{ formatMoney(ledger.total_billed_minor) }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Across {{ ledger.sales_count }} material dispatch invoices
        </div>
      </div>

      <div class="card" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(220, 38, 38, 0.05) 100%); border: 1px solid rgba(239, 68, 68, 0.25);">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #ef4444; letter-spacing: 0.05em;">
          Site Outstanding Balance
        </div>
        <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
          {{ formatMoney(ledger.total_receivable_minor) }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Khata debt attributed to this site
        </div>
      </div>

      <div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(5, 150, 105, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.25);">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #10b981; letter-spacing: 0.05em;">
          Cash / Payments Received
        </div>
        <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
          {{ formatMoney(ledger.total_paid_cash_minor) }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Collected directly against site dispatches
        </div>
      </div>
    </div>

    <!-- Site Meta Information Card -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
      <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.75rem 0;">
        📍 Project Site Specifications
      </h3>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.85rem;">
        <div>
          <span style="color: var(--text-muted); display: block; font-size: 0.75rem;">Plot / Sector:</span>
          <strong>{{ project.plot_number || 'N/A' }}</strong>
        </div>
        <div>
          <span style="color: var(--text-muted); display: block; font-size: 0.75rem;">City / Location:</span>
          <strong>{{ project.city || 'N/A' }}</strong>
        </div>
        <div>
          <span style="color: var(--text-muted); display: block; font-size: 0.75rem;">Supervisor (Munshi):</span>
          <strong>{{ project.supervisor_name || 'N/A' }}</strong>
        </div>
        <div>
          <span style="color: var(--text-muted); display: block; font-size: 0.75rem;">Munshi Contact:</span>
          <strong>{{ project.supervisor_phone || 'N/A' }}</strong>
        </div>
        <div style="grid-column: 1 / -1;" v-if="project.site_address">
          <span style="color: var(--text-muted); display: block; font-size: 0.75rem;">Full Delivery Address:</span>
          <span>{{ project.site_address }}</span>
        </div>
        <div style="grid-column: 1 / -1;" v-if="project.notes">
          <span style="color: var(--text-muted); display: block; font-size: 0.75rem;">Special Gate Entry / Site Instructions:</span>
          <span style="color: var(--text-secondary);">{{ project.notes }}</span>
        </div>
      </div>
    </div>

    <!-- Invoices & Material Dispatches Ledger Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
      <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0;">
          Material Dispatches &amp; Billing Ledger
        </h3>
        <span style="font-size: 0.8rem; color: var(--text-muted);">
          Total Dispatches: {{ ledger.sales?.length || 0 }}
        </span>
      </div>

      <div class="table-responsive">
        <table class="table" style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="border-bottom: 1px solid var(--border-color); text-align: left; background: var(--bg-surface-soft);">
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Date</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Invoice #</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Payment Mode</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Total Billed</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Paid Cash</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Site Khata Debit</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!ledger.sales || ledger.sales.length === 0">
              <td colspan="7" style="padding: 2.5rem; text-align: center; color: var(--text-muted);">
                No material dispatches billed to this project site yet.
              </td>
            </tr>
            <tr v-for="s in ledger.sales" :key="s.id" style="border-bottom: 1px solid var(--border-color);">
              <td style="padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--text-secondary);">
                {{ s.date ? new Date(s.date).toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' }) : '—' }}
              </td>
              <td style="padding: 0.75rem 1rem;">
                <span style="font-weight: 700; font-family: var(--font-mono); color: var(--text-primary);">
                  {{ s.invoice_number }}
                </span>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <span
                  class="badge"
                  :style="{
                    background: s.payment_mode === 'credit' ? 'rgba(239, 68, 68, 0.15)' : 'rgba(16, 185, 129, 0.15)',
                    color: s.payment_mode === 'credit' ? '#ef4444' : '#10b981',
                    padding: '0.2rem 0.5rem',
                    borderRadius: '4px',
                    fontSize: '0.75rem',
                    fontWeight: 700,
                    textTransform: 'uppercase'
                  }"
                >
                  {{ s.payment_mode }}
                </span>
              </td>
              <td style="padding: 0.75rem 1rem; font-weight: 700; font-family: var(--font-mono);">
                {{ formatMoney(s.total_minor) }}
              </td>
              <td style="padding: 0.75rem 1rem; color: #10b981; font-family: var(--font-mono);">
                {{ formatMoney(s.paid_minor) }}
              </td>
              <td style="padding: 0.75rem 1rem; font-weight: 700; color: #ef4444; font-family: var(--font-mono);">
                {{ formatMoney(s.receivable_minor) }}
              </td>
              <td style="padding: 0.75rem 1rem; text-align: right;">
                <a :href="`/sales/${s.id}`" class="btn btn-sm btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;" target="_blank">
                  View Invoice &rarr;
                </a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  project: Object,
  ledger: Object,
});

function formatMoney(minor) {
  if (minor === undefined || minor === null) return 'Rs. 0';
  const val = minor / 100;
  return 'Rs. ' + val.toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function printStatement() {
  window.print();
}
</script>
