<template>
  <SuperAdminLayout title="Database Schema & Migrations">
    <div class="page-container">
      <!-- Breadcrumb Header -->
      <div class="module-header">
        <div>
          <div class="breadcrumb">
            <Link href="/superadmin/dashboard">Super Admin</Link>
            <span class="sep">/</span>
            <span class="current">System Migrations</span>
          </div>
          <h1 class="text-2xl font-bold tracking-tight">Database Schema &amp; Migrations</h1>
          <p class="text-muted text-sm mt-1">
            Execute pending Laravel database migrations, inspect schema versioning, and synchronize live tables.
          </p>
        </div>
        <div class="action-buttons">
          <button
            type="button"
            class="btn btn-secondary"
            :disabled="isOptimizing"
            @click="optimizeClear"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>
            </svg>
            <span>{{ isOptimizing ? 'Optimizing...' : 'Clear & Optimize Cache' }}</span>
          </button>

          <button
            type="button"
            class="btn btn-secondary btn-master-sync"
            :disabled="isSeeding"
            @click="seedMasterData"
            title="Synchronize baseline production master data (Plans, Roles, HS Codes, Tax Templates, System Settings)"
          >
            <svg v-if="!isSeeding" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
              <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
              <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>
            <span v-else class="spinner-sm"></span>
            <span>{{ isSeeding ? 'Syncing Master...' : 'Sync Master Data' }}</span>
          </button>

          <button
            type="button"
            class="btn btn-primary btn-migrate"
            :disabled="isRunning"
            @click="runMigrations"
          >
            <svg v-if="!isRunning" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polygon points="5 3 19 12 5 21 5 3"></polygon>
            </svg>
            <span v-else class="spinner-sm"></span>
            <span>{{ isRunning ? 'Running Migrations...' : 'Run Pending Migrations' }}</span>
            <span v-if="stats.pending_count > 0" class="badge-pending-count">{{ stats.pending_count }}</span>
          </button>
        </div>
      </div>

      <!-- Telemetry KPI Cards -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-icon-box" :class="database.status === 'connected' ? 'icon-emerald' : 'icon-rose'">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
              <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
              <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
            </svg>
          </div>
          <div class="kpi-meta">
            <span class="kpi-label">Database Connection</span>
            <div class="kpi-status-row">
              <span class="status-dot" :class="database.status === 'connected' ? 'dot-active' : 'dot-danger'"></span>
              <span class="kpi-val text-lg">{{ database.status === 'connected' ? 'Connected' : 'Error' }}</span>
            </div>
            <span class="kpi-sub">{{ database.driver }} • {{ database.database }}</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-box icon-blue">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
          </div>
          <div class="kpi-meta">
            <span class="kpi-label">Codebase Migrations</span>
            <span class="kpi-val">{{ stats.total_files }}</span>
            <span class="kpi-sub">Total migration definitions</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-box icon-emerald">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
          </div>
          <div class="kpi-meta">
            <span class="kpi-label">Executed in Database</span>
            <span class="kpi-val">{{ stats.ran_count }}</span>
            <span class="kpi-sub">Batch #{{ stats.latest_batch }} current</span>
          </div>
        </div>

        <div class="kpi-card" :class="{ 'card-pending-alert': stats.pending_count > 0 }">
          <div class="kpi-icon-box" :class="stats.pending_count > 0 ? 'icon-amber pulse-amber' : 'icon-slate'">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
          </div>
          <div class="kpi-meta">
            <span class="kpi-label">Pending Execution</span>
            <span class="kpi-val" :class="stats.pending_count > 0 ? 'text-amber' : ''">
              {{ stats.pending_count }}
            </span>
            <span class="kpi-sub">
              {{ stats.pending_count > 0 ? 'Needs migration run!' : 'All migrations applied' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Master Data Status & Seeding Registry -->
      <div class="card master-data-card mt-6">
        <div class="card-header">
          <div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <h2 class="card-title">Platform Master Data Registry</h2>
              <span class="badge badge-success" style="font-size: 0.68rem;">Production Safe</span>
            </div>
            <p class="card-subtitle">
              Synchronize baseline reference tables (Subscription Packages, RBAC Roles, Pakistan HS Codes, Provincial Tax Templates, Knowledge Base, and System Settings).
            </p>
          </div>
          <button
            type="button"
            class="btn btn-secondary btn-sm"
            :disabled="isSeeding"
            @click="seedMasterData"
          >
            <svg v-if="!isSeeding" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
              <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
              <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>
            <span v-else class="spinner-sm"></span>
            <span>{{ isSeeding ? 'Synchronizing...' : 'Sync All Master Data' }}</span>
          </button>
        </div>

        <div class="master-stats-grid">
          <Link href="/superadmin/plans" class="master-stat-item">
            <div class="master-stat-top">
              <span class="master-stat-name">SaaS Subscription Plans</span>
              <span class="badge" :class="master_stats.plans > 0 ? 'badge-success' : 'badge-warning'">
                {{ master_stats.plans > 0 ? 'Initialized' : 'Empty' }}
              </span>
            </div>
            <div class="master-stat-num">{{ master_stats.plans }}</div>
            <span class="master-stat-sub">Starter, Growth &amp; Enterprise Tiers &rarr;</span>
          </Link>

          <Link href="/superadmin/roles" class="master-stat-item">
            <div class="master-stat-top">
              <span class="master-stat-name">Staff RBAC Roles</span>
              <span class="badge" :class="master_stats.roles > 0 ? 'badge-success' : 'badge-warning'">
                {{ master_stats.roles > 0 ? 'Initialized' : 'Empty' }}
              </span>
            </div>
            <div class="master-stat-num">{{ master_stats.roles }}</div>
            <span class="master-stat-sub">Auditors, Billing, Support &amp; Ops &rarr;</span>
          </Link>

          <Link href="/superadmin/master-data/hs-codes" class="master-stat-item">
            <div class="master-stat-top">
              <span class="master-stat-name">Pakistan HS Codes</span>
              <span class="badge" :class="master_stats.hs_codes > 0 ? 'badge-success' : 'badge-warning'">
                {{ master_stats.hs_codes > 0 ? 'Initialized' : 'Empty' }}
              </span>
            </div>
            <div class="master-stat-num">{{ master_stats.hs_codes }}</div>
            <span class="master-stat-sub">FBR Customs Harmonized Tariff &rarr;</span>
          </Link>

          <Link href="/superadmin/master-data/tax-templates" class="master-stat-item">
            <div class="master-stat-top">
              <span class="master-stat-name">Provincial Tax Templates</span>
              <span class="badge" :class="master_stats.tax_templates > 0 ? 'badge-success' : 'badge-warning'">
                {{ master_stats.tax_templates > 0 ? 'Initialized' : 'Empty' }}
              </span>
            </div>
            <div class="master-stat-num">{{ master_stats.tax_templates }}</div>
            <span class="master-stat-sub">FBR, PRA, SRB, BRA, KPRA Rates &rarr;</span>
          </Link>

          <Link href="/superadmin/support" class="master-stat-item">
            <div class="master-stat-top">
              <span class="master-stat-name">Knowledge Base Articles</span>
              <span class="badge" :class="master_stats.articles > 0 ? 'badge-success' : 'badge-warning'">
                {{ master_stats.articles > 0 ? 'Initialized' : 'Empty' }}
              </span>
            </div>
            <div class="master-stat-num">{{ master_stats.articles }}</div>
            <span class="master-stat-sub">Merchant Self-Service Guides &rarr;</span>
          </Link>

          <Link href="/superadmin/settings" class="master-stat-item">
            <div class="master-stat-top">
              <span class="master-stat-name">Platform System Settings</span>
              <span class="badge" :class="master_stats.settings > 0 ? 'badge-success' : 'badge-warning'">
                {{ master_stats.settings > 0 ? 'Initialized' : 'Empty' }}
              </span>
            </div>
            <div class="master-stat-num">{{ master_stats.settings }}</div>
            <span class="master-stat-sub">Currency, Gateways &amp; Defaults &rarr;</span>
          </Link>
        </div>
      </div>

      <!-- Pending Migrations Alert Banner -->
      <div v-if="pending_migrations.length > 0" class="pending-alert-banner">
        <div class="alert-icon-box">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
        </div>
        <div class="alert-body">
          <h3 class="alert-title">{{ pending_migrations.length }} Pending Migration(s) Detected</h3>
          <p class="alert-desc">
            The following migrations exist in the codebase but have not yet been executed in the active database.
            Running them will create or update required tables and columns.
          </p>
          <div class="pending-list-tags">
            <span v-for="m in pending_migrations" :key="m" class="pending-tag">
              <span class="tag-bullet"></span>
              {{ m }}
            </span>
          </div>
        </div>
        <div class="alert-action">
          <button
            type="button"
            class="btn btn-primary"
            :disabled="isRunning"
            @click="runMigrations"
          >
            {{ isRunning ? 'Running...' : 'Run All Pending Now' }}
          </button>
        </div>
      </div>

      <!-- Live Terminal Output Console -->
      <div v-if="consoleOutput" class="terminal-card">
        <div class="terminal-header">
          <div class="terminal-dots">
            <span class="term-dot dot-red"></span>
            <span class="term-dot dot-yellow"></span>
            <span class="term-dot dot-green"></span>
          </div>
          <span class="terminal-title">Artisan Command Execution Output</span>
          <button type="button" class="btn-term-copy" @click="copyConsoleOutput">
            {{ copied ? 'Copied!' : 'Copy Log' }}
          </button>
        </div>
        <pre class="terminal-pre"><code>{{ consoleOutput }}</code></pre>
      </div>

      <!-- Migrations History Ledger -->
      <div class="card mt-6">
        <div class="card-header">
          <div>
            <h2 class="card-title">Executed Migrations Ledger</h2>
            <p class="card-subtitle">Complete chronological history of migrations applied to this database instance.</p>
          </div>
          <div class="filter-box">
            <input
              v-model="searchQuery"
              type="text"
              class="form-control form-input"
              placeholder="Search migrations..."
              style="max-width: 260px;"
            />
            <button
              v-if="stats.ran_count > 0"
              type="button"
              class="btn btn-secondary btn-sm"
              :disabled="isRollingBack"
              @click="confirmRollback"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                <path d="M3 3v5h5"></path>
              </svg>
              <span>{{ isRollingBack ? 'Rolling back...' : 'Rollback Batch' }}</span>
            </button>
          </div>
        </div>

        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th style="width: 80px;">Batch</th>
                <th>Migration Name</th>
                <th style="width: 140px; text-align: center;">Status</th>
                <th style="width: 80px; text-align: right;">ID</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredRanMigrations.length === 0">
                <td colspan="4" class="text-center py-6 text-muted">
                  No executed migrations found matching criteria.
                </td>
              </tr>
              <tr v-for="item in filteredRanMigrations" :key="item.id">
                <td>
                  <span class="batch-pill">#{{ item.batch }}</span>
                </td>
                <td>
                  <span class="font-mono text-xs text-primary">{{ item.migration }}</span>
                </td>
                <td style="text-align: center;">
                  <span class="badge badge-success">
                    <span class="badge-dot"></span>
                    Applied
                  </span>
                </td>
                <td style="text-align: right;" class="text-muted text-xs">
                  #{{ item.id }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  database: {
    type: Object,
    required: true,
  },
  stats: {
    type: Object,
    required: true,
  },
  master_stats: {
    type: Object,
    default: () => ({
      plans: 0,
      roles: 0,
      hs_codes: 0,
      tax_templates: 0,
      articles: 0,
      settings: 0,
    }),
  },
  pending_migrations: {
    type: Array,
    default: () => [],
  },
  ran_migrations: {
    type: Array,
    default: () => [],
  },
  last_output: {
    type: String,
    default: null,
  },
});

const page = usePage();
const searchQuery = ref('');
const isRunning = ref(false);
const isRollingBack = ref(false);
const isOptimizing = ref(false);
const isSeeding = ref(false);
const copied = ref(false);

const consoleOutput = computed(() => {
  return page.props.flash?.migration_output || props.last_output || '';
});

const filteredRanMigrations = computed(() => {
  if (!searchQuery.value.trim()) {
    return props.ran_migrations;
  }
  const q = searchQuery.value.toLowerCase();
  return props.ran_migrations.filter((m) => m.migration.toLowerCase().includes(q));
});

function runMigrations() {
  if (isRunning.value) return;
  isRunning.value = true;
  router.post('/superadmin/system/migrations/run', {}, {
    preserveScroll: true,
    onFinish: () => {
      isRunning.value = false;
    },
  });
}

function optimizeClear() {
  if (isOptimizing.value) return;
  isOptimizing.value = true;
  router.post('/superadmin/system/migrations/optimize-clear', {}, {
    preserveScroll: true,
    onFinish: () => {
      isOptimizing.value = false;
    },
  });
}

function seedMasterData() {
  if (isSeeding.value) return;
  if (!confirm('This will synchronize baseline production master data (Subscription Plans, RBAC Staff Roles, Pakistan HS Codes, Provincial Tax Templates, Knowledge Base, and System Settings). Existing custom data is preserved safely. Proceed?')) {
    return;
  }
  isSeeding.value = true;
  router.post('/superadmin/system/migrations/seed-master', {}, {
    preserveScroll: true,
    onFinish: () => {
      isSeeding.value = false;
    },
  });
}

function confirmRollback() {
  if (isRollingBack.value) return;
  if (!confirm(`Warning: Are you sure you want to rollback the most recent migration batch (Batch #${props.stats.latest_batch})? This will drop affected tables/columns.`)) {
    return;
  }
  isRollingBack.value = true;
  router.post('/superadmin/system/migrations/rollback', {}, {
    preserveScroll: true,
    onFinish: () => {
      isRollingBack.value = false;
    },
  });
}

function copyConsoleOutput() {
  if (!consoleOutput.value) return;
  navigator.clipboard.writeText(consoleOutput.value).then(() => {
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  });
}
</script>

<style scoped>
.module-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  gap: 1rem;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.75rem;
  color: var(--text-muted);
  margin-bottom: 0.35rem;
}

.breadcrumb .sep {
  opacity: 0.5;
}

.breadcrumb .current {
  color: var(--accent-primary);
  font-weight: 500;
}

.action-buttons {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.btn-migrate {
  background: linear-gradient(135deg, #10b981, #059669);
  color: #fff;
  border: none;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1.15rem;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(16, 185, 129, 0.35);
  transition: all 0.2s ease;
}

.btn-migrate:hover:not(:disabled) {
  background: linear-gradient(135deg, #059669, #047857);
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.45);
}

.badge-pending-count {
  background: #f59e0b;
  color: #1e1b4b;
  font-size: 0.7rem;
  font-weight: 800;
  padding: 0.1rem 0.45rem;
  border-radius: 9999px;
  margin-left: 0.25rem;
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.kpi-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 1rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: all 0.2s ease;
}

.kpi-card:hover {
  border-color: rgba(99, 102, 241, 0.3);
}

.card-pending-alert {
  border-color: rgba(245, 158, 11, 0.4);
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), transparent);
}

.kpi-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-emerald { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.icon-rose { background: rgba(244, 63, 94, 0.15); color: #f43f5e; }
.icon-blue { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.icon-amber { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.icon-slate { background: rgba(100, 116, 139, 0.15); color: #94a3b8; }

.pulse-amber {
  animation: pulse-glow-amber 2s infinite;
}

@keyframes pulse-glow-amber {
  0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
  70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
  100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}

.kpi-meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.kpi-label {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--text-muted);
  letter-spacing: 0.05em;
}

.kpi-val {
  font-size: 1.4rem;
  font-weight: 800;
  line-height: 1.2;
  color: var(--text-primary);
}

.text-amber {
  color: #f59e0b;
}

.kpi-sub {
  font-size: 0.72rem;
  color: var(--text-muted);
}

.kpi-status-row {
  display: flex;
  align-items: center;
  gap: 0.45rem;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.dot-active {
  background: #10b981;
  box-shadow: 0 0 8px #10b981;
}

.dot-danger {
  background: #f43f5e;
  box-shadow: 0 0 8px #f43f5e;
}

/* Pending Alert Banner */
.pending-alert-banner {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(217, 119, 6, 0.08));
  border: 1px solid rgba(245, 158, 11, 0.4);
  border-radius: 12px;
  padding: 1.25rem 1.5rem;
  display: flex;
  align-items: flex-start;
  gap: 1.25rem;
  margin-bottom: 1.5rem;
}

.alert-icon-box {
  color: #f59e0b;
  flex-shrink: 0;
  margin-top: 0.15rem;
}

.alert-body {
  flex: 1;
}

.alert-title {
  font-size: 1rem;
  font-weight: 700;
  color: #fbbf24;
  margin-bottom: 0.25rem;
}

.alert-desc {
  font-size: 0.8rem;
  color: #d1d5db;
  line-height: 1.4;
  margin-bottom: 0.75rem;
}

.pending-list-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.pending-tag {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-family: var(--font-mono);
  font-size: 0.72rem;
  background: rgba(245, 158, 11, 0.15);
  border: 1px solid rgba(245, 158, 11, 0.3);
  color: #fbbf24;
  padding: 0.2rem 0.55rem;
  border-radius: 6px;
}

.tag-bullet {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #f59e0b;
}

.alert-action {
  flex-shrink: 0;
}

/* Terminal Console Output */
.terminal-card {
  background: #090e17;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 1.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
}

.terminal-header {
  background: #111827;
  padding: 0.6rem 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.terminal-dots {
  display: flex;
  gap: 0.35rem;
}

.term-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.dot-red { background: #ef4444; }
.dot-yellow { background: #f59e0b; }
.dot-green { background: #10b981; }

.terminal-title {
  font-size: 0.75rem;
  font-family: var(--font-mono);
  color: #94a3b8;
  letter-spacing: 0.02em;
}

.btn-term-copy {
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #e2e8f0;
  font-size: 0.7rem;
  padding: 0.2rem 0.55rem;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-term-copy:hover {
  background: rgba(255, 255, 255, 0.15);
}

.terminal-pre {
  padding: 1.25rem;
  margin: 0;
  font-family: var(--font-mono);
  font-size: 0.78rem;
  color: #10b981;
  background: transparent;
  line-height: 1.5;
  max-height: 320px;
  overflow-y: auto;
  white-space: pre-wrap;
  word-break: break-word;
}

/* History Ledger Card */
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  gap: 1rem;
}

.card-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-primary);
}

.card-subtitle {
  font-size: 0.78rem;
  color: var(--text-muted);
  margin-top: 0.15rem;
}

.filter-box {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.batch-pill {
  font-family: var(--font-mono);
  font-size: 0.72rem;
  font-weight: 700;
  background: rgba(99, 102, 241, 0.15);
  color: #818cf8;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  border: 1px solid rgba(99, 102, 241, 0.3);
}

.badge-success {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.3);
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.72rem;
  padding: 0.15rem 0.5rem;
  border-radius: 9999px;
  font-weight: 600;
}

.badge-warning {
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.3);
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.72rem;
  padding: 0.15rem 0.5rem;
  border-radius: 9999px;
  font-weight: 600;
}

/* Master Data Registry Grid */
.master-data-card {
  margin-top: 1.5rem;
}

.master-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1rem;
  padding: 1.25rem;
}

.master-stat-item {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 1rem 1.15rem;
  background: var(--bg-tertiary, rgba(255, 255, 255, 0.02));
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md, 8px);
  text-decoration: none;
  transition: all 0.2s ease;
}

.master-stat-item:hover {
  border-color: rgba(99, 102, 241, 0.5);
  background: rgba(99, 102, 241, 0.05);
  transform: translateY(-1px);
}

.master-stat-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
}

.master-stat-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-muted);
}

.master-stat-num {
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0.4rem 0 0.2rem;
  letter-spacing: -0.02em;
}

.master-stat-sub {
  font-size: 0.75rem;
  color: #818cf8;
  font-weight: 500;
}

.badge-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: currentColor;
}

.spinner-sm {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  display: inline-block;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
