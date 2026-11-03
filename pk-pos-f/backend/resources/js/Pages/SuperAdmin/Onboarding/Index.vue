<template>
  <SuperAdminLayout title="Merchant Onboarding & FBR ATL Verification">
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
      <!-- Page Header -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
        <div>
          <h1 style="font-size: 1.5rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.25rem;">
            Merchant Self-Registration & Onboarding Queue
          </h1>
          <p style="color: #94a3b8; font-size: 0.875rem;">
            Review incoming retailer applications, verify NTN filer status against FBR Active Taxpayer List (ATL), and 1-click provision multi-tenant environments.
          </p>
        </div>
      </div>

      <!-- Telemetry KPI Cards -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Pending Adjudication
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #fbbf24; margin-top: 0.25rem;">
                {{ metrics.pending_count }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(245, 158, 11, 0.15); border-radius: 8px; color: #fbbf24;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #fbbf24; margin-top: 0.5rem;">
            Awaiting compliance & KYC review
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                FBR ATL Active Filers
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #34d399; margin-top: 0.25rem;">
                {{ metrics.atl_active_count }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(52, 211, 153, 0.15); border-radius: 8px; color: #34d399;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #34d399; margin-top: 0.5rem;">
            Verified Active Taxpayers in IRIS
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Approved & Provisioned
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #60a5fa; margin-top: 0.25rem;">
                {{ metrics.approved_count }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(59, 130, 246, 0.15); border-radius: 8px; color: #60a5fa;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
            Live merchant tenants active
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Total Applications
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #f8fafc; margin-top: 0.25rem;">
                {{ metrics.total_applications }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(148, 163, 184, 0.15); border-radius: 8px; color: #cbd5e1;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
            Total onboarding submissions
          </div>
        </div>
      </div>

      <!-- Filter Toolbar -->
      <div class="card" style="padding: 1rem;">
        <form @submit.prevent="applyFilters" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
          <div style="flex: 1; min-width: 240px; position: relative;">
            <input
              type="text"
              v-model="filterForm.q"
              placeholder="Search business name, application #, NTN, contact email..."
              class="form-control"
              style="width: 100%; padding-left: 2.25rem;"
            />
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #64748b;"
            >
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </div>

          <select v-model="filterForm.status" class="form-control" style="width: 170px;">
            <option value="">All Statuses</option>
            <option value="pending_review">Pending Review</option>
            <option value="under_investigation">Under Investigation</option>
            <option value="approved">Approved & Live</option>
            <option value="rejected">Rejected</option>
          </select>

          <select v-model="filterForm.province" class="form-control" style="width: 170px;">
            <option value="">All Provinces</option>
            <option value="punjab">Punjab</option>
            <option value="sindh">Sindh</option>
            <option value="kpk">KPK</option>
            <option value="balochistan">Balochistan</option>
            <option value="islamabad">Islamabad</option>
          </select>

          <button type="submit" class="btn btn-secondary">
            Filter
          </button>
          <button
            v-if="filterForm.q || filterForm.status || filterForm.province"
            type="button"
            @click="resetFilters"
            class="btn btn-secondary"
            style="color: #94a3b8;"
          >
            Clear
          </button>
        </form>
      </div>

      <!-- Applications Table -->
      <div class="card" style="overflow: hidden;">
        <div style="overflow-x: auto;">
          <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.02);">
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Application # & Date</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Business & Sector</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Contact & Location</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">FBR ATL Status</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Plan & Terminals</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Status</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8; text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="app in applications.data"
                :key="app.id"
                style="border-bottom: 1px solid rgba(255, 255, 255, 0.05); transition: background-color 0.15s;"
              >
                <!-- Application # -->
                <td style="padding: 1rem;">
                  <div style="font-weight: 700; color: #f8fafc; font-family: monospace;">
                    {{ app.application_number }}
                  </div>
                  <div style="font-size: 0.75rem; color: #64748b;">
                    {{ formatDate(app.created_at) }}
                  </div>
                </td>

                <!-- Business & Sector -->
                <td style="padding: 1rem;">
                  <div style="font-weight: 600; color: #f8fafc; font-size: 0.9375rem;">
                    {{ app.business_name }}
                  </div>
                  <div style="font-size: 0.75rem; color: #94a3b8; text-transform: capitalize;">
                    {{ app.sector.replace('_', ' ') }} • NTN: {{ app.ntn || 'Not Provided' }}
                  </div>
                </td>

                <!-- Contact & Location -->
                <td style="padding: 1rem;">
                  <div style="font-size: 0.875rem; color: #e2e8f0;">
                    {{ app.contact_name }}
                  </div>
                  <div style="font-size: 0.75rem; color: #64748b;">
                    {{ app.city }}, {{ app.province.toUpperCase() }} • {{ app.contact_phone }}
                  </div>
                </td>

                <!-- FBR ATL Status -->
                <td style="padding: 1rem;">
                  <div v-if="app.is_atl_active" style="display: flex; align-items: center; gap: 0.4rem;">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
                    <span style="color: #34d399; font-weight: 600; font-size: 0.8125rem;">ATL Active (Filer)</span>
                  </div>
                  <div v-else style="display: flex; align-items: center; gap: 0.4rem;">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #f87171;"></span>
                    <span style="color: #f87171; font-weight: 600; font-size: 0.8125rem;">Inactive / Non-Filer</span>
                  </div>
                  <div v-if="app.atl_checked_at" style="font-size: 0.7rem; color: #64748b; margin-top: 0.2rem;">
                    Checked {{ formatDate(app.atl_checked_at) }}
                  </div>
                </td>

                <!-- Plan & Terminals -->
                <td style="padding: 1rem; font-size: 0.8125rem;">
                  <div style="color: #e2e8f0; font-weight: 500;">
                    {{ app.requested_plan?.name || 'Standard Tier' }}
                  </div>
                  <div style="color: #64748b; font-size: 0.75rem;">
                    {{ app.pos_terminal_count }} POS Terminal(s)
                  </div>
                </td>

                <!-- Status -->
                <td style="padding: 1rem;">
                  <span
                    class="badge"
                    :style="getStatusBadgeStyle(app.status)"
                    style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase;"
                  >
                    {{ app.status.replace('_', ' ') }}
                  </span>
                </td>

                <!-- Actions -->
                <td style="padding: 1rem; text-align: right;">
                  <div style="display: inline-flex; align-items: center; gap: 0.4rem;">
                    <Link
                      :href="`/superadmin/onboarding/${app.id}`"
                      class="btn btn-secondary"
                      style="padding: 0.25rem 0.6rem; font-size: 0.75rem;"
                    >
                      Dossier & Provision
                    </Link>
                  </div>
                </td>
              </tr>

              <tr v-if="applications.data.length === 0">
                <td colspan="7" style="text-align: center; padding: 3rem 1rem; color: #64748b;">
                  No merchant applications found matching your criteria.
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
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  applications: {
    type: Object,
    required: true,
  },
  metrics: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const filterForm = ref({
  q: props.filters.q || '',
  status: props.filters.status || '',
  province: props.filters.province || '',
});

function applyFilters() {
  router.get('/superadmin/onboarding', filterForm.value, {
    preserveState: true,
    replace: true,
  });
}

function resetFilters() {
  filterForm.value = { q: '', status: '', province: '' };
  applyFilters();
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
  return d.toLocaleDateString('en-PK', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}
</script>

