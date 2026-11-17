<template>
  <SuperAdminLayout title="Tenant Organization Management">
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
      <!-- Page Header & Create Action -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
        <div>
          <h1 style="font-size: 1.5rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.25rem;">
            Tenant Organizations & Retail Fleet
          </h1>
          <p style="color: #94a3b8; font-size: 0.875rem;">
            Directory of registered multi-store tenants, SaaS plan quotas, POS terminal fleets, and store context switching.
          </p>
        </div>

        <Link
          href="/superadmin/tenants/create"
          class="btn btn-primary"
          style="display: inline-flex; align-items: center; gap: 0.5rem; background: linear-gradient(135deg, #ec4899, #8b5cf6); border: none; padding: 0.6rem 1.25rem; font-weight: 600;"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Register New Tenant
        </Link>
      </div>

      <!-- Telemetry Metric Cards -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem;">
        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Total Organizations
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #f8fafc; margin-top: 0.25rem;">
                {{ metrics.total_tenants }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(99, 102, 241, 0.15); border-radius: 8px; color: #818cf8;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
            All-time registered stores
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Active Retail Stores
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #34d399; margin-top: 0.25rem;">
                {{ metrics.active_tenants }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(16, 185, 129, 0.15); border-radius: 8px; color: #34d399;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #34d399; margin-top: 0.5rem;">
            POS terminal sync & login enabled
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Suspended Stores
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #fb7185; margin-top: 0.25rem;">
                {{ metrics.suspended_tenants }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(244, 63, 94, 0.15); border-radius: 8px; color: #fb7185;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #fb7185; margin-top: 0.5rem;">
            Overdue grace period or locked
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Active POS Hardware
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #38bdf8; margin-top: 0.25rem;">
                {{ metrics.total_terminals }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(56, 189, 248, 0.15); border-radius: 8px; color: #38bdf8;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
            Active cash register terminals
          </div>
        </div>
      </div>

      <!-- Filter Toolbar -->
      <div class="card" style="padding: 1rem 1.25rem;">
        <form @submit.prevent="applyFilters" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
          <!-- Search Field -->
          <div style="flex: 1; min-width: 260px; position: relative;">
            <input
              type="text"
              v-model="filterForm.search"
              placeholder="Search store name, NTN, STRN, or owner email..."
              class="form-control"
              style="padding-left: 2.35rem;"
            />
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #64748b; pointer-events: none;"
            >
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </div>

          <!-- Status Dropdown -->
          <div style="min-width: 170px;">
            <select v-model="filterForm.status" class="form-control">
              <option value="">All Statuses</option>
              <option value="active">Active Only</option>
              <option value="suspended">Suspended Only</option>
            </select>
          </div>

          <!-- Province Dropdown -->
          <div style="min-width: 170px;">
            <select v-model="filterForm.province" class="form-control">
              <option value="">All Provinces</option>
              <option value="punjab">Punjab</option>
              <option value="sindh">Sindh</option>
              <option value="kp">Khyber Pakhtunkhwa</option>
              <option value="balochistan">Balochistan</option>
              <option value="federal">Federal / Islamabad</option>
            </select>
          </div>

          <!-- Action Buttons -->
          <button type="submit" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.4rem;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
            </svg>
            Filter
          </button>

          <button
            v-if="filterForm.search || filterForm.status || filterForm.province"
            type="button"
            @click="clearFilters"
            class="btn btn-secondary"
            style="color: #94a3b8;"
          >
            Clear
          </button>
        </form>
      </div>

      <!-- Tenants Data Table -->
      <div class="card" style="overflow: hidden;">
        <!-- Table Header Info Bar -->
        <div style="padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.01); flex-wrap: wrap; gap: 0.5rem;">
          <div style="font-weight: 700; color: #fff; font-size: 0.95rem;">
            Stores Directory
            <span style="font-weight: 400; color: #94a3b8; font-size: 0.85rem; margin-left: 0.35rem;">
              ({{ tenants.total }} total organizations)
            </span>
          </div>
          <div style="font-size: 0.8rem; color: #64748b;">
            Showing {{ tenants.from || 0 }} to {{ tenants.to || 0 }} of {{ tenants.total }}
          </div>
        </div>

        <div style="overflow-x: auto;">
          <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.02);">
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Store & Organization</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Fiscal IDs (NTN)</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Province</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Plan & Quotas</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Owner & Contact</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Registered</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Status</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8; text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="tenant in tenants.data"
                :key="tenant.id"
                style="border-bottom: 1px solid rgba(255, 255, 255, 0.05); transition: background-color 0.15s;"
              >
                <!-- Store & Organization -->
                <td style="padding: 0.875rem 1rem;">
                  <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div
                      style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem; flex-shrink: 0;"
                      :style="getStoreAvatarBg(tenant.province)"
                    >
                      {{ tenant.name ? tenant.name.charAt(0).toUpperCase() : 'T' }}
                    </div>
                    <div>
                      <Link
                        :href="`/superadmin/tenants/${tenant.id}`"
                        style="color: #f8fafc; text-decoration: none; font-weight: 700; font-size: 0.9rem; display: block;"
                      >
                        {{ tenant.name }}
                      </Link>
                      <div style="display: flex; align-items: center; gap: 0.4rem; margin-top: 0.2rem;">
                        <span style="font-size: 0.725rem; color: #94a3b8; display: inline-flex; align-items: center; gap: 0.2rem;">
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                          </svg>
                          {{ tenant.locations_count || 1 }} Branch{{ (tenant.locations_count || 1) > 1 ? 'es' : '' }}
                        </span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Fiscal IDs -->
                <td style="padding: 0.875rem 1rem;">
                  <div style="font-family: monospace; font-size: 0.85rem; font-weight: 600; color: #38bdf8;">
                    NTN: {{ tenant.ntn || 'Not Registered' }}
                  </div>
                  <div v-if="tenant.strn" style="font-family: monospace; font-size: 0.725rem; color: #94a3b8; margin-top: 0.15rem;">
                    STRN: {{ tenant.strn }}
                  </div>
                </td>

                <!-- Province -->
                <td style="padding: 0.875rem 1rem;">
                  <span
                    class="badge"
                    :style="getProvinceBadgeStyle(tenant.province)"
                    style="text-transform: uppercase; font-size: 0.7rem; font-weight: 700;"
                  >
                    {{ tenant.province }}
                  </span>
                </td>

                <!-- Plan & Quotas -->
                <td style="padding: 0.875rem 1rem;">
                  <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    <span
                      class="badge"
                      style="background: rgba(99, 102, 241, 0.18); color: #a5b4fc; font-weight: 600; width: fit-content; font-size: 0.725rem; border: 1px solid rgba(99, 102, 241, 0.3);"
                    >
                      {{ tenant.plan_name || 'Free Tier' }}
                    </span>
                    <span style="font-size: 0.725rem; color: #94a3b8; display: inline-flex; align-items: center; gap: 0.25rem;">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                      </svg>
                      {{ tenant.active_terminals_count || 0 }} / {{ tenant.max_terminals ?? '∞' }} Terminals
                    </span>
                  </div>
                </td>

                <!-- Owner & Contact -->
                <td style="padding: 0.875rem 1rem;">
                  <div style="font-size: 0.85rem; font-weight: 600; color: #f8fafc;">
                    {{ tenant.owner_name }}
                  </div>
                  <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.15rem;">
                    <a :href="`mailto:${tenant.owner_email}`" style="color: inherit; text-decoration: none;">
                      {{ tenant.owner_email }}
                    </a>
                  </div>
                </td>

                <!-- Registered Date -->
                <td style="padding: 0.875rem 1rem;">
                  <div style="font-size: 0.85rem; color: #cbd5e1;" :title="tenant.created_at">
                    {{ tenant.created_at_human || tenant.created_at }}
                  </div>
                  <div style="font-size: 0.725rem; color: #64748b; margin-top: 0.15rem;">
                    {{ tenant.created_at }}
                  </div>
                </td>

                <!-- Status -->
                <td style="padding: 0.875rem 1rem;">
                  <span
                    v-if="tenant.status === 'suspended'"
                    class="badge"
                    style="background: rgba(244, 63, 94, 0.15); color: #fb7185; border: 1px solid rgba(244, 63, 94, 0.3); font-weight: 700; font-size: 0.725rem;"
                  >
                    <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #fb7185; margin-right: 0.25rem;"></span>
                    Suspended
                  </span>
                  <span
                    v-else
                    class="badge"
                    style="background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 700; font-size: 0.725rem;"
                  >
                    <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #10b981; margin-right: 0.25rem;"></span>
                    Active
                  </span>
                </td>

                <!-- Actions -->
                <td style="padding: 0.875rem 1rem; text-align: right;">
                  <div style="display: inline-flex; gap: 0.4rem; align-items: center; justify-content: flex-end;">
                    <!-- View Detail -->
                    <Link
                      :href="`/superadmin/tenants/${tenant.id}`"
                      class="btn btn-secondary"
                      style="padding: 0.3rem 0.65rem; font-size: 0.75rem; height: 32px; display: inline-flex; align-items: center; gap: 0.3rem;"
                      title="View Tenant Organization & Details"
                    >
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                      </svg>
                      View
                    </Link>

                    <!-- Impersonate Owner -->
                    <button
                      v-if="tenant.owner_id && tenant.status !== 'suspended'"
                      @click="impersonate(tenant.owner_id, tenant.name)"
                      class="btn btn-secondary"
                      style="padding: 0.3rem 0.65rem; font-size: 0.75rem; color: #fbbf24; border-color: rgba(245, 158, 11, 0.3); height: 32px; display: inline-flex; align-items: center; gap: 0.3rem;"
                      title="Impersonate Store Owner"
                    >
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                      </svg>
                      Impersonate
                    </button>

                    <!-- Suspend / Reactivate -->
                    <button
                      @click="toggleSuspend(tenant)"
                      class="btn btn-secondary"
                      :style="tenant.status === 'suspended' ? 'color: #34d399; border-color: rgba(16, 185, 129, 0.3);' : 'color: #fb7185; border-color: rgba(244, 63, 94, 0.3);'"
                      style="padding: 0.3rem 0.65rem; font-size: 0.75rem; height: 32px;"
                      :title="tenant.status === 'suspended' ? 'Reactivate Tenant' : 'Suspend Tenant'"
                    >
                      {{ tenant.status === 'suspended' ? 'Activate' : 'Suspend' }}
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="tenants.data.length === 0">
                <td colspan="8" style="text-align: center; padding: 3.5rem 1rem; color: #64748b;">
                  <div style="font-size: 1.1rem; font-weight: 600; color: #94a3b8; margin-bottom: 0.35rem;">
                    No tenant stores found matching your criteria.
                  </div>
                  <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 1rem;">
                    Try adjusting your search terms or clearing status and province filters.
                  </div>
                  <button @click="clearFilters" class="btn btn-secondary" style="font-size: 0.8rem;">
                    Clear All Filters
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="tenants.links && tenants.links.length > 3" class="pagination">
          <Link
            v-for="(link, idx) in tenants.links"
            :key="idx"
            :href="link.url || '#'"
            class="page-btn"
            :class="{ active: link.active, disabled: !link.url }"
            v-html="link.label"
          />
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
  tenants: {
    type: Object,
    required: true,
  },
  metrics: {
    type: Object,
    default: () => ({
      total_tenants: 0,
      active_tenants: 0,
      suspended_tenants: 0,
      total_terminals: 0,
    }),
  },
  filters: {
    type: Object,
    default: () => ({ search: '', status: '', province: '' }),
  },
});

const filterForm = ref({
  search: props.filters.search || '',
  status: props.filters.status || '',
  province: props.filters.province || '',
});

function applyFilters() {
  router.get('/superadmin/tenants', {
    search: filterForm.value.search || undefined,
    status: filterForm.value.status || undefined,
    province: filterForm.value.province || undefined,
  }, {
    preserveState: true,
    replace: true,
  });
}

function clearFilters() {
  filterForm.value = { search: '', status: '', province: '' };
  router.get('/superadmin/tenants', {}, { preserveState: true, replace: true });
}

function toggleSuspend(tenant) {
  const actionText = tenant.status === 'suspended' ? 'reactivate' : 'suspend';
  if (confirm(`Are you sure you want to ${actionText} tenant "${tenant.name}"?`)) {
    router.post(`/superadmin/tenants/${tenant.id}/suspend`, {}, {
      preserveScroll: true,
    });
  }
}

function impersonate(ownerId, tenantName) {
  if (confirm(`Impersonate owner for tenant "${tenantName}"? You will be signed in as this tenant back-office.`)) {
    router.post(`/superadmin/impersonate/${ownerId}`);
  }
}

function getProvinceBadgeStyle(province) {
  switch ((province || '').toLowerCase()) {
    case 'punjab':
      return 'background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3);';
    case 'sindh':
      return 'background: rgba(6, 182, 212, 0.15); color: #22d3ee; border: 1px solid rgba(6, 182, 212, 0.3);';
    case 'kp':
    case 'kpk':
      return 'background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3);';
    case 'balochistan':
      return 'background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3);';
    case 'federal':
    case 'islamabad':
      return 'background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3);';
    default:
      return 'background: rgba(100, 116, 139, 0.15); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.3);';
  }
}

function getStoreAvatarBg(province) {
  switch ((province || '').toLowerCase()) {
    case 'punjab':
      return 'background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.3)); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.35);';
    case 'sindh':
      return 'background: linear-gradient(135deg, rgba(6, 182, 212, 0.2), rgba(8, 145, 178, 0.3)); color: #22d3ee; border: 1px solid rgba(6, 182, 212, 0.35);';
    case 'kp':
    case 'kpk':
      return 'background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.3)); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.35);';
    case 'balochistan':
      return 'background: linear-gradient(135deg, rgba(168, 85, 247, 0.2), rgba(147, 51, 234, 0.3)); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.35);';
    case 'federal':
    case 'islamabad':
      return 'background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.3)); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.35);';
    default:
      return 'background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(79, 70, 229, 0.3)); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.35);';
  }
}
</script>
