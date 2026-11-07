<template>
  <AdminLayout title="System Overview">
    <!-- Summary Cards -->
    <div class="grid-cards">
      <!-- Card 1: Today's Sales Count -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Today's Sales</span>
          <div class="metric-icon" style="background: rgba(99, 102, 241, 0.15); color: #818cf8;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <path d="M16 10a4 4 0 0 1-8 0"></path>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ stats.today_sales_count }}</div>
        <div class="metric-desc">Total completed sales today</div>
      </div>

      <!-- Card 2: Total Revenue -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Total Revenue</span>
          <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="1" x2="12" y2="23"></line>
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ stats.formatted_revenue }}</div>
        <div class="metric-desc">Lifetime processed volume (PKR)</div>
      </div>

      <!-- Card 3: FBR Submission Success Rate -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">FBR Success Rate</span>
          <div class="metric-icon" style="background: rgba(6, 182, 212, 0.15); color: #22d3ee;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ stats.fbr_success_rate }}%</div>
        <div class="metric-desc">{{ stats.fbr_submitted_count }} of {{ stats.fbr_eligible_count }} verified with FBR</div>
      </div>

      <!-- Card 4: Pending Outbox Count -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Pending Outbox</span>
          <div class="metric-icon" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ stats.pending_outbox_count }}</div>
        <div class="metric-desc">Invoices awaiting cloud / FBR sync</div>
      </div>

      <!-- Card 5: Active Terminals Fleet -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">POS Terminals</span>
          <div class="metric-icon" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
              <line x1="8" y1="21" x2="16" y2="21"></line>
              <line x1="12" y1="17" x2="12" y2="21"></line>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ stats.terminals_online ?? 0 }} / {{ stats.terminals_total ?? 0 }}</div>
        <div class="metric-desc">
          <span :style="{ color: (stats.terminals_online > 0 ? '#10b981' : 'var(--text-muted)') }">
            {{ (stats.terminals_online > 0 ? '● Active' : '○ Standby') }}
          </span> • <Link href="/terminals" style="color: #38bdf8; text-decoration: underline;">Manage Fleet</Link>
        </div>
      </div>
    </div>

    <!-- Quick Status Breakdown -->
    <div class="card-table" style="margin-top: 1.5rem;">
      <div class="table-header">
        <h2>FBR Integration Diagnostics</h2>
        <Link href="/sales" class="btn btn-secondary" style="padding: 0.4rem 0.85rem; font-size: 0.8rem;">
          View All Sales →
        </Link>
      </div>
      <div style="padding: 1.5rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <div style="padding: 1rem; background: rgba(0,0,0,0.2); border-radius: 8px;">
          <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem;">Submitted to FBR</div>
          <div style="font-size: 1.4rem; font-weight: 700; color: var(--badge-green-text);">{{ stats.fbr_submitted_count }}</div>
        </div>
        <div style="padding: 1rem; background: rgba(0,0,0,0.2); border-radius: 8px;">
          <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem;">Offline Provisional</div>
          <div style="font-size: 1.4rem; font-weight: 700; color: var(--badge-amber-text);">{{ stats.fbr_offline_count }}</div>
        </div>
        <div style="padding: 1rem; background: rgba(0,0,0,0.2); border-radius: 8px;">
          <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem;">Failed / Rejection</div>
          <div style="font-size: 1.4rem; font-weight: 700; color: var(--badge-red-text);">{{ stats.fbr_failed_count }}</div>
        </div>
        <div style="padding: 1rem; background: rgba(0,0,0,0.2); border-radius: 8px;">
          <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem;">Pending Sync</div>
          <div style="font-size: 1.4rem; font-weight: 700; color: var(--text-secondary);">{{ stats.fbr_pending_count }}</div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
  stats: {
    type: Object,
    required: true,
  },
});
</script>
