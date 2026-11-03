<template>
  <SuperAdminLayout :title="`Ticket ${ticket.ticket_number}`">
    <!-- Back Navigation -->
    <div style="margin-bottom: 1rem;">
      <Link href="/superadmin/support/tickets" style="font-size: 0.85rem; color: #38bdf8; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
        &larr; Back to Support Queue
      </Link>
    </div>

    <!-- Ticket Header Card -->
    <div class="card-table" style="padding: 1.5rem; margin-bottom: 1.5rem;">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
        <div>
          <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.4rem;">
            <span style="font-family: monospace; font-size: 1.1rem; font-weight: 800; color: #38bdf8;">
              {{ ticket.ticket_number }}
            </span>
            <span class="badge" :class="getPriorityBadgeClass(ticket.priority)">
              {{ formatPriority(ticket.priority) }}
            </span>
            <span class="badge" :class="getStatusBadgeClass(ticket.status)">
              {{ formatStatus(ticket.status) }}
            </span>
          </div>
          <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0 0 0.4rem 0;">
            {{ ticket.subject }}
          </h2>
          <div style="font-size: 0.82rem; color: var(--text-muted);">
            Category: <strong>{{ formatCategory(ticket.category) }}</strong> &bull;
            Merchant: <strong>{{ ticket.tenant_name }}</strong> ({{ ticket.tenant_province }}) &bull;
            Created: {{ ticket.created_at }}
          </div>
        </div>

        <!-- Impersonate Support Button -->
        <div style="display: flex; gap: 0.5rem; align-items: center;">
          <form v-if="ticket.user_id" :action="`/superadmin/impersonate/${ticket.user_id}`" method="POST">
            <input type="hidden" name="_token" :value="$page.props.csrf_token || ''" />
            <button
              type="submit"
              class="btn btn-secondary"
              style="display: flex; align-items: center; gap: 0.4rem; background: rgba(236, 72, 153, 0.1); border-color: rgba(236, 72, 153, 0.3); color: #ec4899;"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              One-Click Impersonate
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Main Content Grid -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: flex-start;">
      <!-- Left Column: Snapshot & Messages -->
      <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Automated Diagnostic Snapshot Card -->
        <div v-if="ticket.diagnostic_snapshot" class="card-table" style="padding: 1.25rem; border-left: 4px solid #38bdf8;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
            <div style="display: flex; align-items: center; gap: 0.4rem;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
              </svg>
              <h3 style="font-size: 0.95rem; font-weight: 700; margin: 0;">Automated Machine Diagnostic Snapshot</h3>
            </div>
            <span style="font-size: 0.72rem; color: var(--text-muted);">
              Captured at submission
            </span>
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.8rem;">
            <!-- Tenant Meta -->
            <div style="background: rgba(255,255,255,0.03); padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
              <div style="font-weight: 700; color: #38bdf8; margin-bottom: 0.35rem;">Merchant Configuration</div>
              <div>NTN: <strong>{{ ticket.diagnostic_snapshot.tenant?.ntn }}</strong></div>
              <div>STRN: <strong>{{ ticket.diagnostic_snapshot.tenant?.strn }}</strong></div>
              <div>FBR POS ID: <strong>{{ ticket.diagnostic_snapshot.tenant?.fbr_pos_id }}</strong></div>
              <div>Plan: <strong>{{ ticket.diagnostic_snapshot.tenant?.active_plan }}</strong></div>
            </div>

            <!-- Terminal Meta -->
            <div style="background: rgba(255,255,255,0.03); padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
              <div style="font-weight: 700; color: #818cf8; margin-bottom: 0.35rem;">Hardware & Terminal</div>
              <div>Terminal: <strong>{{ ticket.diagnostic_snapshot.terminal?.terminal_code }}</strong></div>
              <div>Hostname: <strong>{{ ticket.diagnostic_snapshot.terminal?.hostname }}</strong></div>
              <div>App Version: <strong style="color: #10b981;">v{{ ticket.diagnostic_snapshot.terminal?.app_version }}</strong></div>
              <div>OS: <strong>{{ ticket.diagnostic_snapshot.terminal?.platform_os }}</strong></div>
            </div>

            <!-- Outbox & FBR Telemetry -->
            <div style="background: rgba(255,255,255,0.03); padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
              <div style="font-weight: 700; color: #f59e0b; margin-bottom: 0.35rem;">Telemetry & Local State</div>
              <div>Pending Outbox: <strong :style="{ color: ticket.diagnostic_snapshot.telemetry?.pending_outbox_count > 0 ? '#ef4444' : '#10b981' }">{{ ticket.diagnostic_snapshot.telemetry?.pending_outbox_count }} sales</strong></div>
              <div style="margin-top: 0.25rem;">
                Last FBR Error:
                <div style="font-family: monospace; font-size: 0.72rem; color: #ef4444; background: rgba(239, 68, 68, 0.1); padding: 0.2rem 0.4rem; border-radius: 3px; margin-top: 0.15rem; word-break: break-all;">
                  {{ ticket.diagnostic_snapshot.telemetry?.last_fbr_error }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Conversation Stream -->
        <div class="card-table" style="padding: 1.5rem;">
          <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 1rem;">Conversation Thread</h3>

          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div
              v-for="msg in messages"
              :key="msg.id"
              :style="{
                background: msg.is_internal_note ? 'rgba(245, 158, 11, 0.08)' : (msg.is_staff ? 'rgba(99, 102, 241, 0.08)' : 'rgba(255,255,255,0.03)'),
                border: msg.is_internal_note ? '1px solid rgba(245, 158, 11, 0.3)' : (msg.is_staff ? '1px solid rgba(99, 102, 241, 0.25)' : '1px solid var(--border-color)'),
                borderRadius: 'var(--radius-md)',
                padding: '1rem',
              }"
            >
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                  <strong style="font-size: 0.88rem;">{{ msg.user_name }}</strong>
                  <span v-if="msg.is_internal_note" class="badge badge-warning" style="font-size: 0.65rem;">
                    &#128274; Internal Staff Note
                  </span>
                  <span v-else-if="msg.is_staff" class="badge" style="background: rgba(99, 102, 241, 0.2); color: #818cf8; font-size: 0.65rem;">
                    Platform Support
                  </span>
                  <span v-else class="badge badge-neutral" style="font-size: 0.65rem;">
                    Merchant Submitter
                  </span>
                </div>
                <span style="font-size: 0.72rem; color: var(--text-muted);">
                  {{ msg.created_ago }} ({{ msg.created_at }})
                </span>
              </div>

              <div style="font-size: 0.85rem; line-height: 1.6; white-space: pre-wrap; color: var(--text-main);">
                {{ msg.message }}
              </div>
            </div>
          </div>

          <!-- Reply Box -->
          <form @submit.prevent="submitReply" style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color);">
            <div class="form-group">
              <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">
                {{ replyForm.is_internal_note ? 'Add Internal Staff Note (Secret)' : 'Post Public Support Reply' }}
              </label>
              <textarea
                v-model="replyForm.message"
                rows="4"
                class="form-input"
                :placeholder="replyForm.is_internal_note ? 'Add private notes, root cause hypotheses, or instructions for other support engineers...' : 'Type your resolution message to the merchant user...'"
                required
              ></textarea>
              <span v-if="replyForm.errors.message" class="form-error">{{ replyForm.errors.message }}</span>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
              <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" v-model="replyForm.is_internal_note" />
                <span style="font-size: 0.82rem; color: #f59e0b; font-weight: 600;">
                  &#128274; Mark as private internal note (hidden from merchant)
                </span>
              </label>

              <button type="submit" class="btn btn-primary" :disabled="replyForm.processing">
                {{ replyForm.processing ? 'Posting...' : (replyForm.is_internal_note ? 'Save Internal Note' : 'Send Reply') }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Right Column: Ticket Controls & Metadata -->
      <div style="display: flex; flex-direction: column; gap: 1rem;">
        <!-- Status & Assignment Card -->
        <div class="card-table" style="padding: 1.25rem;">
          <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 1rem;">Ticket Controls</h3>

          <div class="form-group">
            <label class="form-label">Ticket Status</label>
            <select :value="ticket.status" @change="onStatusChange($event.target.value)" class="form-input">
              <option value="open">Open</option>
              <option value="in_progress">In Progress</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
          </div>

          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Assigned Staff Engineer</label>
            <select :value="ticket.assigned_to_user_id || ''" @change="onAssignChange($event.target.value)" class="form-input">
              <option value="">Unassigned</option>
              <option v-for="user in staffUsers" :key="user.id" :value="user.id">
                {{ user.name }} ({{ user.email }})
              </option>
            </select>
          </div>
        </div>

        <!-- Merchant Profile Quick View -->
        <div class="card-table" style="padding: 1.25rem;">
          <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.85rem;">Merchant Details</h3>
          <div style="font-size: 0.82rem; display: flex; flex-direction: column; gap: 0.4rem;">
            <div>Organization: <strong>{{ ticket.tenant_name }}</strong></div>
            <div>Province: <strong>{{ ticket.tenant_province }}</strong></div>
            <div>Submitter: <strong>{{ ticket.user_name }}</strong></div>
            <div>Email: <a :href="`mailto:${ticket.user_email}`" style="color: #38bdf8;">{{ ticket.user_email }}</a></div>
          </div>
          <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
            <Link :href="`/superadmin/tenants/${ticket.tenant_id}`" class="btn btn-secondary" style="width: 100%; text-align: center; font-size: 0.78rem;">
              View Full Merchant Record &rarr;
            </Link>
          </div>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  ticket: Object,
  messages: Array,
  staffUsers: Array,
});

const replyForm = useForm({
  message: '',
  is_internal_note: false,
});

function submitReply() {
  replyForm.post(`/superadmin/support/tickets/${props.ticket.id}/reply`, {
    onSuccess: () => {
      replyForm.reset();
    },
  });
}

function onStatusChange(newStatus) {
  router.post(`/superadmin/support/tickets/${props.ticket.id}/status`, {
    status: newStatus,
  });
}

function onAssignChange(staffId) {
  router.post(`/superadmin/support/tickets/${props.ticket.id}/assign`, {
    assigned_to_user_id: staffId || null,
  });
}

function formatCategory(c) {
  const map = {
    fbr_sync_failure: 'FBR Sync Failure',
    hardware_printer: 'Hardware & Printer',
    tax_calculation: 'Tax Calculation',
    billing: 'Account & Billing',
    general: 'General Support',
  };
  return map[c] || c;
}

function formatPriority(p) {
  const map = {
    critical_counter_down: 'Critical (Counter Down)',
    high: 'High',
    medium: 'Medium',
    low: 'Low',
  };
  return map[p] || p;
}

function formatStatus(s) {
  const map = {
    open: 'Open',
    in_progress: 'In Progress',
    resolved: 'Resolved',
    closed: 'Closed',
  };
  return map[s] || s;
}

function getPriorityBadgeClass(p) {
  switch (p) {
    case 'critical_counter_down': return 'badge-danger';
    case 'high': return 'badge-warning';
    case 'medium': return 'badge-neutral';
    case 'low': return 'badge-neutral';
    default: return 'badge-neutral';
  }
}

function getStatusBadgeClass(s) {
  switch (s) {
    case 'open': return 'badge-warning';
    case 'in_progress': return 'badge-neutral';
    case 'resolved': return 'badge-success';
    case 'closed': return 'badge-neutral';
    default: return 'badge-neutral';
  }
}
</script>

