<template>
  <SuperAdminLayout :title="`Onboarding Dossier: ${application.application_number}`">
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
      <!-- Breadcrumb & Top Bar -->
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <Link href="/superadmin/onboarding" class="btn btn-secondary" style="padding: 0.35rem 0.6rem; font-size: 0.8125rem;">
            ← Back to Queue
          </Link>
          <span style="color: #64748b;">/</span>
          <span style="color: #f8fafc; font-weight: 700;">{{ application.application_number }}</span>
          <span class="badge" :style="getStatusBadgeStyle(application.status)" style="text-transform: uppercase;">
            {{ application.status.replace('_', ' ') }}
          </span>
        </div>

        <div style="display: flex; gap: 0.75rem;">
          <button
            @click="checkAtl"
            class="btn btn-secondary"
            style="display: inline-flex; align-items: center; gap: 0.5rem;"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M23 4v6h-6"></path>
              <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
            </svg>
            Re-verify FBR ATL Status
          </button>
        </div>
      </div>

      <!-- Main Dossier Layout -->
      <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 1.5rem; align-items: flex-start;">
        <!-- Left Column: Business & Compliance Dossier -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
          <!-- Business Overview Card -->
          <div class="card" style="padding: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #f8fafc; margin-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.08); padding-bottom: 0.5rem;">
              Applicant Organization Details
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Legal Business Name</div>
                <div style="font-size: 1rem; font-weight: 600; color: #f8fafc; margin-top: 0.25rem;">{{ application.business_name }}</div>
              </div>

              <div>
                <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Trade / Brand Name</div>
                <div style="font-size: 1rem; font-weight: 600; color: #f8fafc; margin-top: 0.25rem;">{{ application.trade_name || 'Same as Legal' }}</div>
              </div>

              <div>
                <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Retail Sector</div>
                <div style="font-size: 0.9375rem; color: #cbd5e1; margin-top: 0.25rem; text-transform: capitalize;">{{ application.sector.replace('_', ' ') }}</div>
              </div>

              <div>
                <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Estimated POS Counters</div>
                <div style="font-size: 0.9375rem; color: #cbd5e1; margin-top: 0.25rem;">{{ application.pos_terminal_count }} Terminal(s)</div>
              </div>

              <div style="grid-column: 1 / -1;">
                <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Registered Store Location</div>
                <div style="font-size: 0.9375rem; color: #cbd5e1; margin-top: 0.25rem;">
                  {{ application.address || 'Address not specified' }}, {{ application.city }}, {{ application.province.toUpperCase() }}
                </div>
              </div>
            </div>
          </div>

          <!-- Contact Person Card -->
          <div class="card" style="padding: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #f8fafc; margin-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.08); padding-bottom: 0.5rem;">
              Primary Authorized Contact
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Contact Person</div>
                <div style="font-size: 0.9375rem; color: #f8fafc; font-weight: 600; margin-top: 0.25rem;">{{ application.contact_name }}</div>
              </div>

              <div>
                <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Phone Number</div>
                <div style="font-size: 0.9375rem; color: #60a5fa; font-weight: 600; margin-top: 0.25rem; font-family: monospace;">{{ application.contact_phone }}</div>
              </div>

              <div style="grid-column: 1 / -1;">
                <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Email Address (Store Owner Login)</div>
                <div style="font-size: 0.9375rem; color: #cbd5e1; margin-top: 0.25rem; font-family: monospace;">{{ application.contact_email }}</div>
              </div>
            </div>
          </div>

          <!-- FBR ATL Regulatory Compliance Dossier -->
          <div class="card" style="padding: 1.5rem; border-color: rgba(16, 185, 129, 0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.08); padding-bottom: 0.5rem;">
              <h3 style="font-size: 1.1rem; font-weight: 700; color: #f8fafc;">
                FBR Active Taxpayer List (ATL) Status
              </h3>
              <span
                class="badge"
                :style="application.is_atl_active ? 'background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid #059669;' : 'background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #dc2626;'"
              >
                {{ application.is_atl_active ? 'FBR ATL ACTIVE FILER' : 'INACTIVE / NON-FILER' }}
              </span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">National Tax Number (NTN)</div>
                <div style="font-size: 1.1rem; font-weight: 700; color: #38bdf8; font-family: monospace; margin-top: 0.25rem;">
                  {{ application.ntn || 'Not Provided' }}
                </div>
              </div>

              <div>
                <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Sales Tax Number (STRN)</div>
                <div style="font-size: 1.1rem; font-weight: 700; color: #a78bfa; font-family: monospace; margin-top: 0.25rem;">
                  {{ application.strn || 'Not Provided' }}
                </div>
              </div>

              <div style="grid-column: 1 / -1; background: rgba(0, 0, 0, 0.2); padding: 0.75rem; border-radius: 6px;">
                <div style="font-size: 0.75rem; color: #94a3b8;">FBR IRIS ATL Record Name:</div>
                <div style="font-size: 0.9375rem; font-weight: 600; color: #34d399; margin-top: 0.2rem;">
                  {{ application.atl_taxpayer_name || 'No Taxpayer Record Match' }}
                </div>
                <div v-if="application.atl_checked_at" style="font-size: 0.72rem; color: #64748b; margin-top: 0.25rem;">
                  Verified at: {{ formatDate(application.atl_checked_at) }}
                </div>
              </div>

              <div v-if="application.document_url" style="grid-column: 1 / -1;">
                <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Business Registration Document</div>
                <div style="margin-top: 0.25rem;">
                  <a :href="application.document_url" target="_blank" style="color: #60a5fa; text-decoration: underline; font-size: 0.875rem;">
                    View Uploaded Proof Document ↗
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: 1-Click Provisioning Engine or Adjudication Details -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
          <!-- Already Approved Banner -->
          <div v-if="application.status === 'approved'" class="card" style="padding: 1.5rem; border-color: rgba(16, 185, 129, 0.4); background: rgba(16, 185, 129, 0.05);">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
              <div style="padding: 0.5rem; background: #10b981; border-radius: 50%; color: #fff;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
              </div>
              <div>
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #34d399;">
                  Tenant Provisioned & Live
                </h3>
                <div style="font-size: 0.8125rem; color: #94a3b8;">
                  Adjudicated by {{ application.reviewer?.name || 'Staff' }} on {{ formatDate(application.reviewed_at) }}
                </div>
              </div>
            </div>

            <div v-if="application.provisioned_tenant" style="margin-top: 1rem;">
              <Link
                :href="`/superadmin/tenants/${application.provisioned_tenant.id}`"
                class="btn btn-primary"
                style="width: 100%; justify-content: center;"
              >
                Open Tenant Organization Profile ↗
              </Link>
            </div>
          </div>

          <!-- Pending Review: 1-Click Provisioning Engine -->
          <div v-else-if="application.status === 'pending_review' || application.status === 'under_investigation'" class="card" style="padding: 1.5rem;">
            <h3 style="font-size: 1.15rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.5rem;">
              1-Click Provisioning Engine
            </h3>
            <p style="font-size: 0.8125rem; color: #94a3b8; margin-bottom: 1.25rem;">
              Approving automatically instantiates a new PostgreSQL tenant, provisions the store owner account, generates FBR credentials, and assigns the subscription tier.
            </p>

            <form @submit.prevent="submitProvision" style="display: flex; flex-direction: column; gap: 1rem;">
              <div>
                <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                  Assign SaaS Subscription Plan *
                </label>
                <select v-model="provisionForm.plan_id" class="form-control" style="width: 100%;" required>
                  <option v-for="p in plans" :key="p.id" :value="p.id">
                    {{ p.name }} (PKR {{ p.price_minor / 100 }} / mo)
                  </option>
                </select>
              </div>

              <div>
                <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                  Initial Store Owner Password
                </label>
                <input
                  type="text"
                  v-model="provisionForm.temp_password"
                  class="form-control"
                  style="width: 100%; font-family: monospace;"
                />
                <div style="font-size: 0.72rem; color: #64748b; margin-top: 0.25rem;">
                  Sent to {{ application.contact_email }} to access the merchant web portal.
                </div>
              </div>

              <div>
                <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                  Internal Adjudication Notes
                </label>
                <textarea
                  v-model="provisionForm.internal_notes"
                  rows="3"
                  placeholder="e.g. Verified NTN against ATL, contact confirmed via phone call."
                  class="form-control"
                  style="width: 100%; resize: vertical;"
                ></textarea>
              </div>

              <button
                type="submit"
                class="btn btn-primary"
                style="width: 100%; padding: 0.75rem; font-size: 0.9375rem; font-weight: 700; background: #10b981; border-color: #059669; justify-content: center; margin-top: 0.5rem;"
                :disabled="isSubmitting"
              >
                {{ isSubmitting ? 'Provisioning Environment...' : 'Approve & 1-Click Provision Merchant' }}
              </button>
            </form>

            <div style="margin-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 1.5rem;">
              <h4 style="font-size: 0.9375rem; font-weight: 700; color: #f87171; margin-bottom: 0.5rem;">
                Reject Application
              </h4>
              <p style="font-size: 0.8125rem; color: #94a3b8; margin-bottom: 1rem;">
                Decline the application with a mandatory formal notice reason.
              </p>

              <form @submit.prevent="submitReject" style="display: flex; flex-direction: column; gap: 0.75rem;">
                <textarea
                  v-model="rejectForm.rejection_reason"
                  required
                  rows="2"
                  placeholder="Provide rejection reason (e.g. Inactive NTN, fake details)..."
                  class="form-control"
                  style="width: 100%; resize: vertical;"
                ></textarea>

                <button
                  type="submit"
                  class="btn btn-danger"
                  style="justify-content: center;"
                  :disabled="isSubmitting"
                >
                  Reject Application
                </button>
              </form>
            </div>
          </div>

          <!-- Rejected Banner -->
          <div v-else class="card" style="padding: 1.5rem; border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.05);">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #f87171; margin-bottom: 0.5rem;">
              Application Rejected
            </h3>
            <p style="font-size: 0.875rem; color: #cbd5e1; margin-bottom: 0.5rem;">
              <strong>Reason:</strong> {{ application.rejection_reason }}
            </p>
            <div style="font-size: 0.75rem; color: #94a3b8;">
              Reviewed by {{ application.reviewer?.name || 'Staff' }} on {{ formatDate(application.reviewed_at) }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  application: {
    type: Object,
    required: true,
  },
  plans: {
    type: Array,
    default: () => [],
  },
});

const isSubmitting = ref(false);

const provisionForm = ref({
  plan_id: props.application.requested_plan?.id || (props.plans[0]?.id || ''),
  temp_password: 'Pos2026!' + Math.random().toString(36).slice(-4),
  internal_notes: '',
});

const rejectForm = ref({
  rejection_reason: '',
});

function checkAtl() {
  router.post(`/superadmin/onboarding/${props.application.id}/check-atl`, {}, {
    preserveScroll: true,
  });
}

function submitProvision() {
  if (confirm(`Approve and provision multi-tenant POS environment for "${props.application.business_name}"?`)) {
    isSubmitting.value = true;
    router.post(`/superadmin/onboarding/${props.application.id}/approve`, provisionForm.value, {
      onFinish: () => { isSubmitting.value = false; },
    });
  }
}

function submitReject() {
  if (confirm(`Reject onboarding application #${props.application.application_number}?`)) {
    isSubmitting.value = true;
    router.post(`/superadmin/onboarding/${props.application.id}/reject`, rejectForm.value, {
      onFinish: () => { isSubmitting.value = false; },
    });
  }
}

function getStatusBadgeStyle(status) {
  switch (status) {
    case 'approved':
      return {
        background: 'rgba(52, 211, 153, 0.2)',
        color: '#34d399',
        border: '1px solid rgba(52, 211, 153, 0.4)',
      };
    case 'pending_review':
      return {
        background: 'rgba(245, 158, 11, 0.2)',
        color: '#fbbf24',
        border: '1px solid rgba(245, 158, 11, 0.4)',
      };
    case 'rejected':
      return {
        background: 'rgba(239, 68, 68, 0.2)',
        color: '#f87171',
        border: '1px solid rgba(239, 68, 68, 0.4)',
      };
    default:
      return {
        background: 'rgba(148, 163, 184, 0.2)',
        color: '#cbd5e1',
        border: '1px solid rgba(148, 163, 184, 0.4)',
      };
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

