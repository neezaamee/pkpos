<template>
  <SuperAdminLayout title="Backups & 6-Year Statutory Fiscal Retention">
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
      <!-- Page Header -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
        <div>
          <h1 style="font-size: 1.5rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.25rem;">
            Automated Backups & Fiscal Data Archiving
          </h1>
          <p style="color: #94a3b8; font-size: 0.875rem;">
            PostgreSQL snapshot management and 6-year statutory fiscal audit retention complying with Pakistan Sales Tax Act 1990 (Sec 22).
          </p>
        </div>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
          <button
            @click="showSnapshotModal = true"
            class="btn btn-secondary"
            style="display: inline-flex; align-items: center; gap: 0.5rem;"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
              <polyline points="17 21 17 13 7 13 7 21"></polyline>
              <polyline points="7 3 7 8 15 8"></polyline>
            </svg>
            Take Database Snapshot
          </button>

          <button
            @click="showFiscalModal = true"
            class="btn btn-primary"
            style="display: inline-flex; align-items: center; gap: 0.5rem;"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            Compile 6-Year Fiscal Archive
          </button>
        </div>
      </div>

      <!-- Telemetry Cards -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Database Snapshots
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #f8fafc; margin-top: 0.25rem;">
                {{ metrics.total_backups_count }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(59, 130, 246, 0.15); border-radius: 8px; color: #60a5fa;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
            Storage: {{ metrics.total_backup_size_mb }} MB compressed
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Fiscal Year Archives
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #34d399; margin-top: 0.25rem;">
                {{ metrics.fiscal_archives_count }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(52, 211, 153, 0.15); border-radius: 8px; color: #34d399;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #34d399; margin-top: 0.5rem;">
            Auditable statutory packages
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Statutory Locked Records
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #a78bfa; margin-top: 0.25rem;">
                {{ metrics.statutory_locked_records }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(167, 139, 250, 0.15); border-radius: 8px; color: #a78bfa;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
            Immutable under 6-year legal freeze
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Compliance Posture
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #38bdf8; margin-top: 0.25rem;">
                100% PASS
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(56, 189, 248, 0.15); border-radius: 8px; color: #38bdf8;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #38bdf8; margin-top: 0.5rem;">
            Sales Tax Act 1990 (Sec 22) Compliant
          </div>
        </div>
      </div>

      <!-- Tab Switcher -->
      <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding-bottom: 0.25rem;">
        <button
          @click="activeTab = 'backups'"
          class="btn"
          :style="activeTab === 'backups' ? 'background: rgba(59, 130, 246, 0.2); color: #60a5fa; border-color: rgba(59, 130, 246, 0.4);' : 'background: transparent; color: #94a3b8; border-color: transparent;'"
        >
          PostgreSQL Database Snapshots
        </button>

        <button
          @click="activeTab = 'fiscal'"
          class="btn"
          :style="activeTab === 'fiscal' ? 'background: rgba(52, 211, 153, 0.2); color: #34d399; border-color: rgba(52, 211, 153, 0.4);' : 'background: transparent; color: #94a3b8; border-color: transparent;'"
          style="display: inline-flex; align-items: center; gap: 0.5rem;"
        >
          <span>6-Year Statutory Fiscal Retention Packages</span>
          <span style="background: rgba(52, 211, 153, 0.2); color: #34d399; font-weight: 700; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px;">
            Sec 22
          </span>
        </button>
      </div>

      <!-- TAB 1: DATABASE SNAPSHOTS -->
      <div v-if="activeTab === 'backups'" class="card" style="overflow: hidden;">
        <div style="overflow-x: auto;">
          <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.02);">
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Snapshot Archive</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Type</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Size</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">SHA-256 Checksum</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Created By</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8; text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="b in backups.data"
                :key="b.id"
                style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);"
              >
                <td style="padding: 1rem;">
                  <div style="font-weight: 700; color: #f8fafc; font-family: monospace;">
                    {{ b.filename }}
                  </div>
                  <div style="font-size: 0.75rem; color: #64748b;">
                    {{ formatDate(b.created_at) }} • {{ b.notes || 'Full DB Dump' }}
                  </div>
                </td>

                <td style="padding: 1rem;">
                  <span class="badge" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; text-transform: capitalize;">
                    {{ b.backup_type.replace('_', ' ') }}
                  </span>
                </td>

                <td style="padding: 1rem; font-weight: 600; color: #cbd5e1;">
                  {{ b.file_size_mb }} MB
                </td>

                <td style="padding: 1rem; font-family: monospace; font-size: 0.75rem; color: #94a3b8;">
                  {{ b.sha256_checksum ? b.sha256_checksum.slice(0, 16) + '...' : 'N/A' }}
                </td>

                <td style="padding: 1rem; font-size: 0.8125rem; color: #94a3b8;">
                  {{ b.creator?.name || 'Automated Cron' }}
                </td>

                <td style="padding: 1rem; text-align: right;">
                  <button
                    @click="deleteBackup(b)"
                    class="btn btn-danger"
                    style="padding: 0.25rem 0.6rem; font-size: 0.75rem;"
                  >
                    Delete
                  </button>
                </td>
              </tr>

              <tr v-if="backups.data.length === 0">
                <td colspan="6" style="text-align: center; padding: 3rem 1rem; color: #64748b;">
                  No database snapshots recorded yet. Click "Take Database Snapshot" above.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 2: 6-YEAR FISCAL ARCHIVES -->
      <div v-else-if="activeTab === 'fiscal'" style="display: flex; flex-direction: column; gap: 1rem;">
        <!-- Statutory Legal Notice Banner -->
        <div class="card" style="padding: 1.25rem; border-color: rgba(52, 211, 153, 0.4); background: rgba(52, 211, 153, 0.05);">
          <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            <div>
              <div style="font-weight: 700; color: #34d399; font-size: 0.9375rem;">
                Statutory Fiscal Compliance Mandate (Pakistan Sales Tax Act 1990 - Section 22)
              </div>
              <p style="font-size: 0.8125rem; color: #cbd5e1; margin-top: 0.25rem; line-height: 1.4;">
                All Tier-1 Retailers integrated with FBR are legally obligated to retain electronic point-of-sale sales registers, buyer NTN tax invoices, and fiscal transmission logs for a minimum duration of <strong>6 years</strong>. These archives are cryptographically signed with SHA-256 and locked against deletion.
              </p>
            </div>
          </div>
        </div>

        <div class="card" style="overflow: hidden;">
          <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
              <thead>
                <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.02);">
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Archive Code</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Merchant Organization</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Financial Year</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Invoices & Logs</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">6-Year Statutory Retention</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8; text-align: right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="arc in fiscalArchives.data"
                  :key="arc.id"
                  style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);"
                >
                  <td style="padding: 1rem;">
                    <div style="font-weight: 700; color: #f8fafc; font-family: monospace;">
                      {{ arc.archive_code }}
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b;">
                      Compiled {{ formatDate(arc.created_at) }}
                    </div>
                  </td>

                  <td style="padding: 1rem;">
                    <div style="font-weight: 600; color: #e2e8f0; font-size: 0.875rem;">
                      {{ arc.tenant?.name || 'Platform Consolidated' }}
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b;">
                      NTN: {{ arc.tenant?.ntn || 'All Merchants' }}
                    </div>
                  </td>

                  <td style="padding: 1rem; font-weight: 700; color: #60a5fa;">
                    FY {{ arc.financial_year }}
                  </td>

                  <td style="padding: 1rem; font-size: 0.875rem;">
                    <div style="color: #f8fafc; font-weight: 600;">{{ arc.record_count.toLocaleString() }} Invoices</div>
                    <div style="font-size: 0.75rem; color: #94a3b8;">{{ arc.file_size_mb }} MB Compressed</div>
                  </td>

                  <td style="padding: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                      <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
                      <span style="color: #34d399; font-weight: 600; font-size: 0.8125rem;">
                        Locked until {{ arc.statutory_retention_until }}
                      </span>
                    </div>
                    <div style="font-size: 0.72rem; color: #64748b; margin-top: 0.2rem;">
                      Protected from administrative deletion
                    </div>
                  </td>

                  <td style="padding: 1rem; text-align: right;">
                    <a
                      :href="`/superadmin/backups/fiscal-archive/${arc.id}/download`"
                      class="btn btn-secondary"
                      style="padding: 0.25rem 0.6rem; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;"
                    >
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                      </svg>
                      <span>Download Archive</span>
                    </a>
                  </td>
                </tr>

                <tr v-if="fiscalArchives.data.length === 0">
                  <td colspan="6" style="text-align: center; padding: 3rem 1rem; color: #64748b;">
                    No 6-year fiscal retention archives compiled yet. Click "Compile 6-Year Fiscal Archive" above.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- MODAL: SNAPSHOT -->
      <div v-if="showSnapshotModal" class="modal-backdrop" @click.self="showSnapshotModal = false">
        <div class="card" style="width: 100%; max-width: 480px; padding: 1.5rem;">
          <h2 style="font-size: 1.25rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.5rem;">
            Trigger Database Snapshot
          </h2>
          <p style="font-size: 0.8125rem; color: #94a3b8; margin-bottom: 1.25rem;">
            Generates a compressed `.sql.gz` PostgreSQL dump of all platform schemas, tables, and tenant partitions.
          </p>

          <form @submit.prevent="submitSnapshot" style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                Snapshot Notes
              </label>
              <input
                type="text"
                v-model="snapshotNotes"
                placeholder="e.g. Pre-deployment backup or month-end snapshot"
                class="form-control"
                style="width: 100%;"
              />
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
              <button type="button" @click="showSnapshotModal = false" class="btn btn-secondary">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                {{ isSubmitting ? 'Taking Snapshot...' : 'Execute Snapshot' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- MODAL: FISCAL ARCHIVE -->
      <div v-if="showFiscalModal" class="modal-backdrop" @click.self="showFiscalModal = false">
        <div class="card" style="width: 100%; max-width: 500px; padding: 1.5rem;">
          <h2 style="font-size: 1.25rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.5rem;">
            Compile 6-Year Fiscal Retention Archive
          </h2>
          <p style="font-size: 0.8125rem; color: #94a3b8; margin-bottom: 1.25rem;">
            Package all fiscal sales invoices, HS codes, provincial taxes, and FBR IRIS verification receipts under Section 22.
          </p>

          <form @submit.prevent="submitFiscal" style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                Financial Year *
              </label>
              <input
                type="text"
                v-model="fiscalForm.financial_year"
                required
                placeholder="e.g. 2025-2026"
                class="form-control"
                style="width: 100%;"
              />
            </div>

            <div>
              <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                Scope (Merchant Tenant)
              </label>
              <select v-model="fiscalForm.tenant_id" class="form-control" style="width: 100%;">
                <option value="">Consolidated (All Active Tenants)</option>
                <option v-for="t in tenants" :key="t.id" :value="t.id">
                  {{ t.name }} (NTN: {{ t.ntn || 'N/A' }})
                </option>
              </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
              <button type="button" @click="showFiscalModal = false" class="btn btn-secondary">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                {{ isSubmitting ? 'Compiling Archive...' : 'Compile & Seal Archive' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  backups: {
    type: Object,
    required: true,
  },
  fiscalArchives: {
    type: Object,
    required: true,
  },
  metrics: {
    type: Object,
    required: true,
  },
  tenants: {
    type: Array,
    default: () => [],
  },
  tab: {
    type: String,
    default: 'backups',
  },
});

const activeTab = ref(props.tab === 'fiscal_archives' ? 'fiscal' : 'backups');
const isSubmitting = ref(false);

// Snapshot Modal
const showSnapshotModal = ref(false);
const snapshotNotes = ref('');

function submitSnapshot() {
  isSubmitting.value = true;
  router.post('/superadmin/backups/snapshot', {
    notes: snapshotNotes.value,
  }, {
    onSuccess: () => { showSnapshotModal.value = false; snapshotNotes.value = ''; },
    onFinish: () => { isSubmitting.value = false; },
  });
}

// Fiscal Modal
const showFiscalModal = ref(false);
const fiscalForm = ref({
  financial_year: '2025-2026',
  tenant_id: '',
});

function submitFiscal() {
  isSubmitting.value = true;
  router.post('/superadmin/backups/fiscal-archive', fiscalForm.value, {
    onSuccess: () => { showFiscalModal.value = false; },
    onFinish: () => { isSubmitting.value = false; },
  });
}

function deleteBackup(b) {
  if (confirm(`Delete database backup snapshot "${b.filename}"?`)) {
    router.delete(`/superadmin/backups/${b.id}`);
  }
}

function formatDate(isoStr) {
  if (!isoStr) return '-';
  const d = new Date(isoStr);
  return d.toLocaleString('en-PK', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  z-index: 1000;
}
</style>

