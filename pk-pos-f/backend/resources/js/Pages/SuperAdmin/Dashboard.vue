<template>
  <SuperAdminLayout title="Executive Platform Intelligence">
    <!-- Executive Command Center Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 0.25rem;">
          PK-POS Enterprise Intelligence & Executive Command
        </h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">
          Consolidated Pakistan-wide fiscalization, transaction GMV, hardware fleet telemetry, and merchant operations.
        </p>
      </div>

      <!-- Quick Action Shortcuts -->
      <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <Link
          v-if="metrics.failed_invoices_count > 0"
          href="/superadmin/compliance/fbr/failed"
          class="btn btn-danger"
          style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; padding: 0.4rem 0.8rem;"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          {{ metrics.failed_invoices_count }} DLQ Invoices
        </Link>

        <Link
          v-if="metrics.open_tickets_count > 0"
          href="/superadmin/support/tickets"
          class="btn"
          style="background: rgba(96, 165, 250, 0.15); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; padding: 0.4rem 0.8rem;"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
          </svg>
          {{ metrics.open_tickets_count }} Open Tickets
        </Link>

        <Link
          href="/superadmin/tenants/create"
          class="btn btn-primary"
          style="background: linear-gradient(135deg, #ec4899, #8b5cf6); border: none; display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; padding: 0.4rem 0.8rem;"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Create Tenant
        </Link>
      </div>
    </div>

    <!-- Executive KPI Grid (6 Cards) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <!-- Platform All-Time GMV -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">All-Time Platform GMV</span>
          <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="1" x2="12" y2="23"></line>
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
          </div>
        </div>
        <div class="metric-value" style="color: #34d399;">{{ formatRupees(metrics.all_time_gmv) }}</div>
        <div class="metric-desc">Total processed transactions across Pakistan</div>
      </div>

      <!-- 30-Day Sales Volume -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Sales Volume (30d)</span>
          <div class="metric-icon" style="background: rgba(6, 182, 212, 0.15); color: #22d3ee;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ formatRupees(metrics.total_sales_volume) }}</div>
        <div class="metric-desc">Last 30 days transaction revenue</div>
      </div>

      <!-- Registered Merchants -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Registered Merchants</span>
          <div class="metric-icon" style="background: rgba(99, 102, 241, 0.15); color: #818cf8;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ metrics.total_tenants }}</div>
        <div class="metric-desc">{{ metrics.active_tenants }} active in the last 30 days</div>
      </div>

      <!-- FBR Fiscalization Score -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">FBR Fiscal Compliance</span>
          <div class="metric-icon" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
              <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
          </div>
        </div>
        <div class="metric-value" :style="{ color: metrics.fbr_compliance_rate >= 95 ? '#34d399' : '#fbbf24' }">
          {{ metrics.fbr_compliance_rate }}%
        </div>
        <div class="metric-desc">{{ metrics.fbr_submissions_today }} verified invoices today</div>
      </div>

      <!-- Active POS Terminals Fleet -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Active POS Fleet</span>
          <div class="metric-icon" style="background: rgba(139, 92, 246, 0.15); color: #a78bfa;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
              <line x1="8" y1="21" x2="16" y2="21"></line>
              <line x1="12" y1="17" x2="12" y2="21"></line>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ metrics.active_terminals_count }}</div>
        <div class="metric-desc">Paired desktop Tauri client registers</div>
      </div>

      <!-- Open Support Cases -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Support Desk Backlog</span>
          <div class="metric-icon" style="background: rgba(236, 72, 153, 0.15); color: #f472b6;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ metrics.open_tickets_count }}</div>
        <div class="metric-desc">Unresolved merchant helpdesk inquiries</div>
      </div>
    </div>

    <!-- Analytics Section 1: GMV Trajectory & FBR Compliance Rate -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
      <!-- 14-Day GMV Trajectory Visualizer -->
      <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <div>
            <h3 style="font-size: 1.05rem; font-weight: 700; color: #fff; margin-bottom: 0.2rem;">
              14-Day Platform GMV Trajectory
            </h3>
            <p style="font-size: 0.775rem; color: var(--text-muted); margin: 0;">Daily gross sales volume across all merchants</p>
          </div>
          <span class="badge" style="background: rgba(52, 211, 153, 0.1); color: #34d399; border-color: rgba(52, 211, 153, 0.25);">
            Live Sync
          </span>
        </div>

        <!-- Custom SVG Bar Chart -->
        <div style="height: 180px; display: flex; align-items: flex-end; gap: 8px; padding-top: 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
          <div
            v-for="day in daily_gmv"
            :key="day.date"
            style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end;"
            :title="`${day.label}: ${formatRupees(day.total_volume)} (${day.tx_count} sales)`"
          >
            <div
              style="width: 100%; border-radius: 4px 4px 0 0; background: linear-gradient(180deg, #38bdf8, #0284c7); transition: all 0.3s; min-height: 4px;"
              :style="{ height: getBarHeight(day.total_volume) }"
            ></div>
          </div>
        </div>

        <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.7rem; color: var(--text-muted);">
          <span>{{ daily_gmv[0]?.label }}</span>
          <span>{{ daily_gmv[Math.floor(daily_gmv.length / 2)]?.label }}</span>
          <span>{{ daily_gmv[daily_gmv.length - 1]?.label }}</span>
        </div>
      </div>

      <!-- FBR Fiscalization Breakdown -->
      <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <div>
            <h3 style="font-size: 1.05rem; font-weight: 700; color: #fff; margin-bottom: 0.2rem;">
              FBR Fiscal Compliance Health
            </h3>
            <p style="font-size: 0.775rem; color: var(--text-muted); margin: 0;">Platform-wide submission & dead-letter queue ratio</p>
          </div>
          <Link href="/superadmin/compliance/fbr" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">
            Command Center
          </Link>
        </div>

        <!-- Progress Ratio Bar -->
        <div style="height: 14px; border-radius: 7px; overflow: hidden; display: flex; background: rgba(255, 255, 255, 0.05); margin-bottom: 1.25rem;">
          <div
            style="background: #10b981; transition: width 0.3s;"
            :style="{ width: `${fbr_breakdown.compliance_rate}%` }"
            title="Submitted & Compliant"
          ></div>
          <div
            v-if="fbr_breakdown.failed > 0"
            style="background: #ef4444; transition: width 0.3s;"
            :style="{ width: `${getFbrPercentage(fbr_breakdown.failed)}%` }"
            title="Failed / Dead-Letter Queue"
          ></div>
          <div
            v-if="fbr_breakdown.pending > 0"
            style="background: #60a5fa; transition: width 0.3s;"
            :style="{ width: `${getFbrPercentage(fbr_breakdown.pending)}%` }"
            title="Pending Sync"
          ></div>
        </div>

        <!-- Status Counters Grid -->
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
          <div style="padding: 0.75rem; border-radius: 8px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2);">
            <div style="font-size: 0.75rem; color: #34d399; font-weight: 600;">Verified Submissions</div>
            <div style="font-size: 1.25rem; font-weight: 700; color: #fff;">{{ fbr_breakdown.submitted.toLocaleString() }}</div>
            <div style="font-size: 0.7rem; color: var(--text-muted);">{{ fbr_breakdown.compliance_rate }}% clean compliance</div>
          </div>

          <div style="padding: 0.75rem; border-radius: 8px; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2);">
            <div style="font-size: 0.75rem; color: #f87171; font-weight: 600;">Dead-Letter Invoices</div>
            <div style="font-size: 1.25rem; font-weight: 700; color: #fff;">{{ fbr_breakdown.failed.toLocaleString() }}</div>
            <div style="font-size: 0.7rem; color: var(--text-muted);">Requires retry or token refresh</div>
          </div>

          <div style="padding: 0.75rem; border-radius: 8px; background: rgba(96, 165, 250, 0.08); border: 1px solid rgba(96, 165, 250, 0.2);">
            <div style="font-size: 0.75rem; color: #60a5fa; font-weight: 600;">Pending In Queue</div>
            <div style="font-size: 1.25rem; font-weight: 700; color: #fff;">{{ fbr_breakdown.pending.toLocaleString() }}</div>
            <div style="font-size: 0.7rem; color: var(--text-muted);">Awaiting background dispatcher</div>
          </div>

          <div style="padding: 0.75rem; border-radius: 8px; background: rgba(251, 191, 36, 0.08); border: 1px solid rgba(251, 191, 36, 0.2);">
            <div style="font-size: 0.75rem; color: #fbbf24; font-weight: 600;">24h Provisional Receipts</div>
            <div style="font-size: 1.25rem; font-weight: 700; color: #fff;">{{ fbr_breakdown.provisional.toLocaleString() }}</div>
            <div style="font-size: 0.7rem; color: var(--text-muted);">Within offline emergency window</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Analytics Section 2: Provincial Breakdown & Top Merchants -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
      <!-- Province Distribution -->
      <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <h3 style="font-size: 1.05rem; font-weight: 700; color: #fff; margin: 0;">
            Provincial Revenue & Store Distribution
          </h3>
          <Link href="/superadmin/master-data/tax-templates" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">
            Tax Rates
          </Link>
        </div>

        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Province</th>
                <th>Merchants</th>
                <th>Invoices</th>
                <th style="text-align: right;">Sales Volume</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="province_distribution.length === 0">
                <td colspan="4" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">
                  No provincial sales data recorded yet.
                </td>
              </tr>
              <tr v-for="prov in province_distribution" :key="prov.province">
                <td>
                  <span class="badge" :style="getProvinceBadgeStyle(prov.province)" style="text-transform: uppercase;">
                    {{ prov.province }}
                  </span>
                </td>
                <td style="color: #fff; font-weight: 600;">{{ prov.tenant_count }}</td>
                <td style="font-size: 0.85rem; color: var(--text-muted);">{{ prov.sales_count.toLocaleString() }}</td>
                <td style="text-align: right; font-weight: 600; color: #34d399;">
                  {{ formatRupees(prov.total_volume) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Top Merchants Ranking -->
      <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <h3 style="font-size: 1.05rem; font-weight: 700; color: #fff; margin: 0;">
            Top Merchants by Volume
          </h3>
          <Link href="/superadmin/tenants" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">
            All Merchants
          </Link>
        </div>

        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Merchant</th>
                <th>Province</th>
                <th>Fleet</th>
                <th style="text-align: right;">GMV Volume</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="top_merchants.length === 0">
                <td colspan="4" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">
                  No sales recorded by merchants yet.
                </td>
              </tr>
              <tr v-for="(merchant, idx) in top_merchants" :key="merchant.id">
                <td>
                  <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 0.75rem; font-weight: 700; width: 18px; color: var(--text-muted);">#{{ idx + 1 }}</span>
                    <div>
                      <Link :href="`/superadmin/tenants/${merchant.id}`" style="font-weight: 600; color: #fff; text-decoration: none;">
                        {{ merchant.name }}
                      </Link>
                      <div style="font-size: 0.7rem; color: var(--text-muted);">{{ merchant.total_sales }} orders</div>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="badge badge-neutral" style="text-transform: uppercase; font-size: 0.65rem;">
                    {{ merchant.province }}
                  </span>
                </td>
                <td style="font-size: 0.85rem; color: var(--text-muted);">
                  {{ merchant.terminals_count }} registers
                </td>
                <td style="text-align: right; font-weight: 700; color: #38bdf8;">
                  {{ formatRupees(merchant.total_volume) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Bottom Section: Recently Onboarded Tenants -->
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <div>
          <h3 style="font-size: 1.05rem; font-weight: 700; color: #fff; margin-bottom: 0.2rem;">
            Recently Onboarded Merchants
          </h3>
          <p style="font-size: 0.775rem; color: var(--text-muted); margin: 0;">Latest retail organizations joined to PK-POS</p>
        </div>
        <Link href="/superadmin/tenants" class="btn btn-secondary" style="font-size: 0.8rem;">
          View All Tenants
        </Link>
      </div>

      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Tenant Name</th>
              <th>NTN</th>
              <th>Province</th>
              <th>Status</th>
              <th>Created</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!recent_tenants || recent_tenants.length === 0">
              <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                No tenants registered yet.
              </td>
            </tr>
            <tr v-for="tenant in recent_tenants" :key="tenant.id">
              <td style="font-weight: 600; color: #fff;">
                {{ tenant.name }}
              </td>
              <td>
                <code style="font-size: 0.775rem;">{{ tenant.ntn || 'N/A' }}</code>
              </td>
              <td>
                <span class="badge badge-neutral" style="text-transform: uppercase;">
                  {{ tenant.province }}
                </span>
              </td>
              <td>
                <span v-if="tenant.suspended_at" class="badge badge-failed">
                  <span class="badge-dot"></span>
                  Suspended
                </span>
                <span v-else class="badge badge-submitted">
                  <span class="badge-dot"></span>
                  Active
                </span>
              </td>
              <td style="font-size: 0.8rem; color: var(--text-muted);">
                {{ formatDate(tenant.created_at) }}
              </td>
              <td style="text-align: right;">
                <Link :href="`/superadmin/tenants/${tenant.id}`" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.775rem;">
                  Manage
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
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  metrics: {
    type: Object,
    required: true,
  },
  fbr_breakdown: {
    type: Object,
    default: () => ({}),
  },
  daily_gmv: {
    type: Array,
    default: () => [],
  },
  province_distribution: {
    type: Array,
    default: () => [],
  },
  top_merchants: {
    type: Array,
    default: () => [],
  },
  recent_tenants: {
    type: Array,
    default: () => [],
  },
});

const maxDailyVolume = computed(() => {
  if (!props.daily_gmv || props.daily_gmv.length === 0) return 1;
  const max = Math.max(...props.daily_gmv.map(d => d.total_volume || 0));
  return max > 0 ? max : 1;
});

function getBarHeight(volume) {
  if (!volume || volume <= 0) return '4px';
  const pct = Math.round((volume / maxDailyVolume.value) * 100);
  return `${Math.max(pct, 6)}%`;
}

function getFbrPercentage(count) {
  const total = (props.fbr_breakdown.submitted || 0) +
                (props.fbr_breakdown.failed || 0) +
                (props.fbr_breakdown.pending || 0) +
                (props.fbr_breakdown.provisional || 0);
  if (total <= 0) return 0;
  return Math.round((count / total) * 100);
}

function formatRupees(minorUnits) {
  const rupees = (minorUnits || 0) / 100;
  return new Intl.NumberFormat('en-PK', {
    style: 'currency',
    currency: 'PKR',
    maximumFractionDigits: 0,
  }).format(rupees);
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-PK', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function getProvinceBadgeStyle(province) {
  switch (province?.toLowerCase()) {
    case 'punjab':
      return { background: 'rgba(52, 211, 153, 0.15)', color: '#34d399', borderColor: 'rgba(52, 211, 153, 0.3)' };
    case 'sindh':
      return { background: 'rgba(96, 165, 250, 0.15)', color: '#60a5fa', borderColor: 'rgba(96, 165, 250, 0.3)' };
    case 'kpk':
      return { background: 'rgba(251, 191, 36, 0.15)', color: '#fbbf24', borderColor: 'rgba(251, 191, 36, 0.3)' };
    case 'islamabad':
      return { background: 'rgba(167, 139, 250, 0.15)', color: '#a78bfa', borderColor: 'rgba(167, 139, 250, 0.3)' };
    case 'balochistan':
      return { background: 'rgba(244, 114, 182, 0.15)', color: '#f472b6', borderColor: 'rgba(244, 114, 182, 0.3)' };
    default:
      return { background: 'rgba(148, 163, 184, 0.15)', color: '#94a3b8', borderColor: 'rgba(148, 163, 184, 0.3)' };
  }
}
</script>
