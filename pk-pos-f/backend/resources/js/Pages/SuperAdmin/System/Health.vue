<template>
  <SuperAdminLayout title="System Diagnostics & Telemetry">
    <!-- Header Summary -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h2 style="font-size: 1.25rem; font-weight: 700;">System Diagnostics & Database Telemetry</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          Real-time PostgreSQL telemetry, server runtime performance, storage footprint, and transaction sync throughput.
        </p>
      </div>
      <div>
        <Link href="/superadmin/system/jobs" class="btn btn-secondary">
          View Background Queue & Workers &rarr;
        </Link>
      </div>
    </div>

    <!-- Health Telemetry KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card-table" style="padding: 1.25rem;">
        <div style="display: flex; align-items: center; gap: 0.4rem;">
          <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981;"></span>
          <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
            Database Latency
          </div>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #10b981;">
          {{ database.latency_ms }} ms
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          PostgreSQL ping roundtrip
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Database Size
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #38bdf8;">
          {{ database.size }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          {{ database.name }} ({{ database.driver }})
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          PHP Memory
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: var(--text-main);">
          {{ runtime.memory_usage }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Limit: {{ runtime.memory_limit }}
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Environment
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #818cf8;">
          PHP {{ runtime.php_version.split('-')[0] }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Laravel {{ runtime.laravel_version }} ({{ runtime.os }})
        </div>
      </div>
    </div>

    <!-- Main Grid: Tables Row Counts & System Runtime Details -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; align-items: flex-start;">
      <!-- Table Rows Count -->
      <div class="card-table">
        <div class="table-header">
          <h2>Database Tables & Row Volumes</h2>
          <span style="font-size: 0.8rem; color: var(--text-muted);">PostgreSQL Storage</span>
        </div>

        <div style="overflow-x: auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Resource Entity</th>
                <th>Database Table</th>
                <th style="text-align: right;">Total Records</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tbl in database.tables" :key="tbl.table">
                <td>
                  <div style="font-weight: 600; font-size: 0.88rem;">{{ tbl.label }}</div>
                </td>
                <td>
                  <span style="font-family: monospace; font-size: 0.8rem; color: var(--text-muted);">{{ tbl.table }}</span>
                </td>
                <td style="text-align: right; font-weight: 700; font-family: monospace; font-size: 0.95rem; color: #38bdf8;">
                  {{ tbl.rows.toLocaleString() }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Infrastructure & Runtime Config -->
      <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="card-table" style="padding: 1.5rem;">
          <h3 style="font-size: 1rem; font-weight: 700; margin: 0 0 1rem 0;">Infrastructure & Runtime Services</h3>

          <div style="display: flex; flex-direction: column; gap: 0.85rem; font-size: 0.85rem;">
            <div style="display: flex; justify-content: space-between; padding-bottom: 0.6rem; border-bottom: 1px solid var(--border-color);">
              <span style="color: var(--text-muted);">Database Connection</span>
              <strong style="font-family: monospace;">{{ database.driver }} ({{ database.name }})</strong>
            </div>

            <div style="display: flex; justify-content: space-between; padding-bottom: 0.6rem; border-bottom: 1px solid var(--border-color);">
              <span style="color: var(--text-muted);">Cache Driver</span>
              <strong style="font-family: monospace;">{{ runtime.cache_driver }}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; padding-bottom: 0.6rem; border-bottom: 1px solid var(--border-color);">
              <span style="color: var(--text-muted);">Queue Driver</span>
              <strong style="font-family: monospace;">{{ runtime.queue_driver }}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; padding-bottom: 0.6rem; border-bottom: 1px solid var(--border-color);">
              <span style="color: var(--text-muted);">Operating System</span>
              <strong>{{ runtime.os }}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; padding-bottom: 0.6rem; border-bottom: 1px solid var(--border-color);">
              <span style="color: var(--text-muted);">Application Timezone</span>
              <strong>{{ runtime.timezone }}</strong>
            </div>

            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-muted);">Server Timestamp</span>
              <strong style="font-family: monospace; font-size: 0.8rem; color: #38bdf8;">{{ runtime.server_time }}</strong>
            </div>
          </div>
        </div>

        <!-- 24h Sync Throughput Summary -->
        <div class="card-table" style="padding: 1.5rem;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <h3 style="font-size: 1rem; font-weight: 700; margin: 0;">24-Hour Sync Throughput</h3>
            <span class="badge badge-success" style="font-size: 0.7rem;">Active Nationwide Sync</span>
          </div>
          <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0 0 1rem 0;">
            Sales transaction batches processed and committed to the central database in the last 24 hours.
          </p>

          <div v-if="sync_throughput && sync_throughput.length > 0" style="display: flex; flex-direction: column; gap: 0.5rem; max-height: 220px; overflow-y: auto;">
            <div
              v-for="point in sync_throughput"
              :key="point.hour"
              style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; background: rgba(255,255,255,0.02); padding: 0.4rem 0.65rem; border-radius: 4px;"
            >
              <span style="font-family: monospace; color: var(--text-muted);">{{ point.hour }}</span>
              <strong style="color: #10b981;">{{ point.count }} transactions</strong>
            </div>
          </div>
          <div v-else style="font-size: 0.8rem; color: var(--text-muted); text-align: center; padding: 1.5rem;">
            No transactions synced within the last 24 hours.
          </div>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

defineProps({
  database: Object,
  runtime: Object,
  sync_throughput: Array,
});
</script>

