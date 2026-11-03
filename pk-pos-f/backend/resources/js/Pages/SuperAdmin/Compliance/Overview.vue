<template>
  <SuperAdminLayout title="FBR Fiscalization & Compliance Command Center">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h2 style="font-size: 1.25rem; font-weight: 700;">FBR Fiscalization Command Center</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          National digital invoicing oversight, dead-letter queue management, and statutory 24-hour compliance tracking.
        </p>
      </div>

      <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <!-- Ping FBR Button -->
        <button
          @click="pingFbr"
          class="btn btn-secondary"
          :disabled="isPinging"
          style="display: flex; align-items: center; gap: 0.4rem;"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="23 4 23 10 17 10"></polyline>
            <polyline points="1 20 1 14 7 14"></polyline>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
          </svg>
          {{ isPinging ? 'Pinging FBR...' : 'Test FBR Live Ping' }}
        </button>

        <Link href="/superadmin/compliance/fbr/failed" class="btn btn-primary" style="background: linear-gradient(135deg, #f43f5e, #be123c);">
          View Dead-Letter Queue
        </Link>
      </div>
    </div>

    <!-- Live FBR Health Banner -->
    <div
      class="card-table"
      style="padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; border-left: 4px solid #10b981;"
      :style="latest_health?.status === 'offline' ? 'border-left-color: #f43f5e;' : (latest_health?.status === 'degraded' ? 'border-left-color: #f59e0b;' : 'border-left-color: #10b981;')"
    >
      <div style="display: flex; align-items: center; gap: 1rem;">
        <div
          style="width: 12px; height: 12px; border-radius: 50%;"
          :style="latest_health?.status === 'offline' ? 'background: #f43f5e; box-shadow: 0 0 12px #f43f5e;' : (latest_health?.status === 'degraded' ? 'background: #f59e0b; box-shadow: 0 0 12px #f59e0b;' : 'background: #10b981; box-shadow: 0 0 12px #10b981;')"
        ></div>
        <div>
          <div style="font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem;">
            FBR IRIS Gateway Status:
            <span style="text-transform: uppercase; font-size: 0.85rem;" :style="latest_health?.status === 'offline' ? 'color: #fb7185;' : (latest_health?.status === 'degraded' ? 'color: #fbbf24;' : 'color: #34d399;')">
              {{ latest_health?.status || 'Operational' }}
            </span>
          </div>
          <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
            Endpoint: <span style="font-family: monospace;">{{ latest_health?.endpoint_url || 'https://ebps.fbr.gov.pk/fbr/api/v1/ping' }}</span>
            <span v-if="latest_health?.latency_ms"> • Latency: {{ latest_health.latency_ms }}ms</span>
            <span v-if="latest_health?.checked_at"> • Checked: {{ latest_health.checked_at }}</span>
          </div>
        </div>
      </div>

      <div style="display: flex; gap: 0.5rem;">
        <span class="badge" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8;">
          Protocol: HTTP / JSON REST
        </span>
      </div>
    </div>

    <!-- Top KPI Grid -->
    <div class="grid-cards" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); margin-bottom: 1.5rem;">
      <!-- Fiscal Success Rate -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Compliance Success Rate</span>
          <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
          </div>
        </div>
        <div class="metric-value" style="color: #34d399;">{{ metrics.success_rate }}%</div>
        <div class="metric-desc">Percentage of sales verified by FBR</div>
      </div>

      <!-- Submitted Count -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Fiscalized Invoices</span>
          <div class="metric-icon" style="background: rgba(6, 182, 212, 0.15); color: #22d3ee;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ metrics.submitted_count }}</div>
        <div class="metric-desc">Assigned valid FBR USIN codes</div>
      </div>

      <!-- Dead Letter Queue (Failed) -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Failed Invoices (DLQ)</span>
          <div class="metric-icon" style="background: rgba(244, 63, 94, 0.15); color: #fb7185;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="15" y1="9" x2="9" y2="15"></line>
              <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
          </div>
        </div>
        <div class="metric-value" style="color: #fb7185;">{{ metrics.failed_count }}</div>
        <div class="metric-desc">
          <Link href="/superadmin/compliance/fbr/failed" style="color: #38bdf8; text-decoration: underline;">
            Resolve in Dead-Letter Queue &rarr;
          </Link>
        </div>
      </div>

      <!-- Overdue Provisional Receipts (> 24h) -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Overdue Provisional (&gt;24h)</span>
          <div class="metric-icon" style="background: rgba(225, 29, 72, 0.15); color: #f43f5e;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
          </div>
        </div>
        <div class="metric-value" :style="metrics.overdue_count > 0 ? 'color: #f43f5e;' : ''">{{ metrics.overdue_count }}</div>
        <div class="metric-desc">
          <Link href="/superadmin/compliance/fbr/provisional?urgency=overdue" style="color: #38bdf8; text-decoration: underline;">
            View statutory violations &rarr;
          </Link>
        </div>
      </div>
    </div>

    <!-- 2 Column Analytics Section -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
      <!-- Common FBR Error Categories -->
      <div class="card-table">
        <div class="table-header">
          <div>
            <h2>Top Rejection Causes in DLQ</h2>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Error signatures returned by FBR IRIS APIs</span>
          </div>
          <Link href="/superadmin/compliance/fbr/failed" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem;">
            Inspect All
          </Link>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Error Signature</th>
              <th style="text-align: right;">Occurrences</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="error_breakdown.length === 0">
              <td colspan="2" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                No failed FBR sales currently in Dead-Letter Queue. Excellent!
              </td>
            </tr>
            <tr v-for="(err, i) in error_breakdown" :key="i">
              <td style="font-family: monospace; font-size: 0.82rem; color: #fb7185;">
                {{ err.error }}
              </td>
              <td style="text-align: right; font-weight: 700;">
                <span class="badge badge-failed">{{ err.total }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Merchants with Highest Rejections -->
      <div class="card-table">
        <div class="table-header">
          <div>
            <h2>Tenants Requiring Attention</h2>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Businesses with highest failed submissions</span>
          </div>
          <Link href="/superadmin/compliance/fbr/provisional" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem;">
            Provisional Tracker
          </Link>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Tenant</th>
              <th>Province</th>
              <th style="text-align: right;">Failed Invoices</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="tenants_with_failures.length === 0">
              <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                All tenant terminals are operating smoothly with zero failed invoices.
              </td>
            </tr>
            <tr v-for="t in tenants_with_failures" :key="t.id">
              <td>
                <div style="font-weight: 600; font-size: 0.88rem;">{{ t.name }}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">NTN: {{ t.ntn }}</div>
              </td>
              <td>
                <span class="badge badge-neutral" style="text-transform: uppercase;">{{ t.province }}</span>
              </td>
              <td style="text-align: right;">
                <span class="badge badge-failed">{{ t.failed_count }}</span>
              </td>
              <td style="text-align: right;">
                <Link :href="`/superadmin/tenants/${t.id}`" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                  Tenant Details
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  metrics: {
    type: Object,
    required: true,
  },
  error_breakdown: {
    type: Array,
    default: () => [],
  },
  tenants_with_failures: {
    type: Array,
    default: () => [],
  },
  latest_health: {
    type: Object,
    default: null,
  },
});

const isPinging = ref(false);

function pingFbr() {
  isPinging.value = true;
  router.post('/superadmin/compliance/fbr/ping', {}, {
    preserveScroll: true,
    onFinish: () => {
      isPinging.value = false;
    },
  });
}
</script>

