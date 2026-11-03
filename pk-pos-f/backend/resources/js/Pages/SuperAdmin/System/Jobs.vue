<template>
  <SuperAdminLayout title="Queue & Background Jobs Monitor">
    <!-- Header Summary -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h2 style="font-size: 1.25rem; font-weight: 700;">Queue & Background Worker Telemetry</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          Surveillance of asynchronous workers, failed queue retries, and background job stack traces.
        </p>
      </div>
      <div style="display: flex; gap: 0.75rem; align-items: center;">
        <button
          v-if="metrics.failed_jobs_count > 0"
          @click="retryAll"
          class="btn btn-primary"
          style="display: flex; align-items: center; gap: 0.4rem;"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="1 4 1 10 7 10"></polyline>
            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
          </svg>
          Retry All Failed Jobs ({{ metrics.failed_jobs_count }})
        </button>
        <button
          v-if="metrics.failed_jobs_count > 0"
          @click="flushAll"
          class="btn btn-secondary"
          style="color: #ef4444; border-color: rgba(239, 68, 68, 0.4);"
        >
          Flush All
        </button>
      </div>
    </div>

    <!-- Queue Metrics KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Pending Queue Workload
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #38bdf8;">
          {{ metrics.pending_jobs_count }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Tasks awaiting worker execution
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="display: flex; align-items: center; gap: 0.4rem;">
          <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%;" :style="{ background: metrics.failed_jobs_count > 0 ? '#ef4444' : '#10b981', boxShadow: metrics.failed_jobs_count > 0 ? '0 0 8px #ef4444' : '0 0 8px #10b981' }"></span>
          <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
            Failed Background Tasks
          </div>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem;" :style="{ color: metrics.failed_jobs_count > 0 ? '#ef4444' : '#10b981' }">
          {{ metrics.failed_jobs_count }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Dead-letter worker failures
        </div>
      </div>
    </div>

    <!-- Failed Jobs Table -->
    <div class="card-table">
      <div class="table-header">
        <h2>Failed Jobs Dead-Letter Queue</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">
          Showing {{ failed_jobs.from || 0 }} to {{ failed_jobs.to || 0 }} of {{ failed_jobs.total }} jobs
        </span>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Job ID & Queue</th>
              <th>Task Handler</th>
              <th>Failure Diagnostic</th>
              <th>Failed At</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="job in failed_jobs.data" :key="job.id">
              <td>
                <div style="font-family: monospace; font-size: 0.88rem; font-weight: 700; color: #38bdf8;">
                  #{{ job.id }}
                </div>
                <div style="font-size: 0.72rem; color: var(--text-muted);">
                  Queue: <code style="color: #818cf8;">{{ job.queue }}</code> ({{ job.connection }})
                </div>
              </td>
              <td>
                <div style="font-weight: 600; font-size: 0.88rem;">{{ job.display_name }}</div>
                <div style="font-size: 0.72rem; font-family: monospace; color: var(--text-muted); margin-top: 0.15rem;">
                  UUID: {{ job.uuid }}
                </div>
              </td>
              <td>
                <div style="font-family: monospace; font-size: 0.75rem; color: #ef4444; max-width: 440px; line-height: 1.4;">
                  {{ job.exception_snippet }}
                </div>
                <button
                  @click="openTraceModal(job)"
                  class="btn btn-secondary"
                  style="margin-top: 0.35rem; padding: 0.15rem 0.45rem; font-size: 0.7rem;"
                >
                  Inspect Full Stack Trace
                </button>
              </td>
              <td>
                <span style="font-size: 0.8rem; color: var(--text-muted);">
                  {{ job.failed_at }}
                </span>
              </td>
              <td style="text-align: right;">
                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                  <button
                    @click="retryJob(job.id)"
                    class="btn btn-primary"
                    style="padding: 0.25rem 0.55rem; font-size: 0.75rem;"
                  >
                    Retry
                  </button>
                  <button
                    @click="deleteJob(job.id)"
                    class="btn btn-secondary"
                    style="padding: 0.25rem 0.55rem; font-size: 0.75rem; color: #ef4444;"
                  >
                    Delete
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="failed_jobs.data.length === 0">
              <td colspan="5" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                <div style="color: #10b981; font-weight: 600; font-size: 1rem; margin-bottom: 0.35rem;">
                  &#10003; Background Queue is Healthy
                </div>
                <div>There are currently zero failed background jobs.</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="failed_jobs.links && failed_jobs.links.length > 3" style="display: flex; justify-content: flex-end; padding: 1rem; gap: 0.35rem; border-top: 1px solid var(--border-color);">
        <Link
          v-for="(link, i) in failed_jobs.links"
          :key="i"
          :href="link.url || '#'"
          class="btn btn-secondary"
          :class="{ 'btn-primary': link.active, disabled: !link.url }"
          style="padding: 0.35rem 0.65rem; font-size: 0.75rem;"
          v-html="link.label"
        />
      </div>
    </div>

    <!-- Stack Trace Modal -->
    <div v-if="showTraceModal" class="modal-overlay" @click.self="showTraceModal = false">
      <div class="modal-container" style="max-width: 720px;">
        <div class="modal-header">
          <h3>Failed Job Stack Trace — #{{ activeJob?.id }}</h3>
          <button @click="showTraceModal = false" class="close-btn">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
          <div style="font-weight: 700; color: #ef4444; margin-bottom: 0.5rem;">
            {{ activeJob?.display_name }}
          </div>
          <pre style="background: rgba(0,0,0,0.4); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-family: monospace; font-size: 0.75rem; line-height: 1.5; white-space: pre-wrap; word-break: break-all; color: #fca5a5;">{{ activeJob?.exception_full }}</pre>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--border-color);">
          <button @click="showTraceModal = false" class="btn btn-secondary">Close</button>
          <button @click="retryJob(activeJob?.id)" class="btn btn-primary">Retry Job Now</button>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

defineProps({
  failed_jobs: Object,
  metrics: Object,
});

const showTraceModal = ref(false);
const activeJob = ref(null);

function openTraceModal(job) {
  activeJob.value = job;
  showTraceModal.value = true;
}

function retryJob(id) {
  router.post(`/superadmin/system/jobs/${id}/retry`, {}, {
    onSuccess: () => {
      showTraceModal.value = false;
    },
  });
}

function retryAll() {
  if (confirm('Are you sure you want to retry all failed background jobs?')) {
    router.post('/superadmin/system/jobs/retry-all');
  }
}

function deleteJob(id) {
  if (confirm(`Delete failed job record #${id}?`)) {
    router.delete(`/superadmin/system/jobs/${id}`);
  }
}

function flushAll() {
  if (confirm('Are you sure you want to flush and permanently delete ALL failed jobs?')) {
    router.post('/superadmin/system/jobs/flush');
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 1.5rem;
}

.modal-container {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  width: 100%;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
  animation: modalIn 0.15s ease-out;
}

@keyframes modalIn {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0;
}

.close-btn {
  background: transparent;
  border: none;
  font-size: 1.5rem;
  line-height: 1;
  color: var(--text-muted);
  cursor: pointer;
}

.close-btn:hover {
  color: var(--text-main);
}

.modal-body {
  padding: 1.5rem;
}
</style>

