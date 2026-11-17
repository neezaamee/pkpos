<template>
  <SuperAdminLayout :title="`Tenant: ${tenant.name}`">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
        <Link href="/superadmin/tenants" class="btn btn-secondary" style="padding: 0.5rem 0.85rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
          Back to Tenants
        </Link>
        <span v-if="tenant.is_suspended" class="badge badge-failed" style="font-size: 0.85rem; padding: 0.35rem 0.75rem;">
          <span class="badge-dot"></span>
          Tenant Suspended (since {{ tenant.suspended_at }})
        </span>
        <span v-else class="badge badge-submitted" style="font-size: 0.85rem; padding: 0.35rem 0.75rem;">
          <span class="badge-dot"></span>
          Active Tenant
        </span>
        <span class="badge" style="background: rgba(99, 102, 241, 0.18); color: #a5b4fc; font-weight: 600; font-size: 0.85rem; padding: 0.35rem 0.75rem;">
          Plan: {{ subscription.plan_name }}
        </span>
      </div>

      <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <!-- Edit Profile Button -->
        <Link :href="`/superadmin/tenants/${tenant.id}/edit`" class="btn btn-secondary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 20h9"></path>
            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
          </svg>
          Edit Profile
        </Link>

        <!-- Change Plan Button -->
        <button @click="openPlanModal" class="btn btn-secondary" style="color: #a5b4fc; border-color: rgba(99, 102, 241, 0.4);">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
          </svg>
          Change Plan
        </button>

        <!-- Suspend / Reactivate Button -->
        <button
          @click="openSuspendModal"
          class="btn btn-secondary"
          :style="tenant.is_suspended ? 'color: #34d399; border-color: rgba(16, 185, 129, 0.4);' : 'color: #fb7185; border-color: rgba(244, 63, 94, 0.4);'"
        >
          <svg v-if="tenant.is_suspended" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
          </svg>
          {{ tenant.is_suspended ? 'Reactivate Tenant' : 'Suspend Tenant' }}
        </button>

        <!-- Purge / Delete Tenant Button -->
        <button
          @click="openDeleteModal"
          class="btn btn-secondary"
          style="color: #fb7185; border-color: rgba(244, 63, 94, 0.4);"
          title="Permanently delete test tenant"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="3 6 5 6 21 6"></polyline>
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
          </svg>
          Purge Tenant
        </button>

        <!-- Quick Impersonate Owner Button -->
        <button
          v-if="primaryOwner && !tenant.is_suspended"
          @click="impersonate(primaryOwner.id)"
          class="btn btn-primary"
          style="background: linear-gradient(135deg, #f59e0b, #d97706);"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="8.5" cy="7" r="4"></circle>
            <line x1="20" y1="8" x2="20" y2="14"></line>
            <line x1="23" y1="11" x2="17" y2="11"></line>
          </svg>
          Login As Owner
        </button>
      </div>
    </div>

    <!-- Suspension Alert if Suspended -->
    <div
      v-if="tenant.is_suspended"
      style="margin-bottom: 1.5rem; background: rgba(244, 63, 94, 0.12); border: 1px solid rgba(244, 63, 94, 0.35); border-radius: 0.5rem; padding: 1rem 1.25rem; display: flex; align-items: flex-start; gap: 0.75rem;"
    >
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fb7185" stroke-width="2" style="margin-top: 0.1rem; flex-shrink: 0;">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="8" x2="12" y2="12"></line>
        <line x1="12" y1="16" x2="12.01" y2="16"></line>
      </svg>
      <div>
        <h4 style="font-size: 0.95rem; font-weight: 700; color: #fb7185; margin: 0 0 0.25rem 0;">
          Tenant Account Suspended
        </h4>
        <p style="font-size: 0.85rem; color: #fecdd3; margin: 0;">
          <strong>Reason:</strong> {{ tenant.suspension_reason || 'Administrative suspension.' }}
        </p>
      </div>
    </div>

    <!-- Executive Metrics Strip -->
    <div class="grid-cards" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); margin-bottom: 1.5rem;">
      <!-- Lifetime Volume -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Gross Sales Volume</span>
          <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="1" x2="12" y2="23"></line>
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
          </div>
        </div>
        <div class="metric-value">Rs. {{ formatMoney(lifetime_volume_minor) }}</div>
        <div class="metric-desc">{{ lifetime_sales_count }} lifetime completed sales</div>
      </div>

      <!-- Active Terminals Gauge -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">POS Terminals</span>
          <div class="metric-icon" style="background: rgba(99, 102, 241, 0.15); color: #818cf8;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
              <line x1="8" y1="21" x2="16" y2="21"></line>
              <line x1="12" y1="17" x2="12" y2="21"></line>
            </svg>
          </div>
        </div>
        <div class="metric-value">
          {{ subscription.active_terminals_count }} <span style="font-size: 0.95rem; font-weight: normal; color: var(--text-muted);">/ {{ subscription.max_terminals ?? '∞' }}</span>
        </div>
        <div class="metric-desc">
          <span v-if="subscription.max_terminals && subscription.active_terminals_count >= subscription.max_terminals" style="color: #fb7185; font-weight: 600;">Quota Reached</span>
          <span v-else>{{ subscription.max_terminals ? `${subscription.max_terminals - subscription.active_terminals_count} slots remaining` : 'Unlimited counters' }}</span>
        </div>
      </div>

      <!-- FBR Success Rate -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">FBR Fiscalization</span>
          <div class="metric-icon" style="background: rgba(6, 182, 212, 0.15); color: #22d3ee;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ fbr_success_rate }}%</div>
        <div class="metric-desc">{{ fbr_submitted_count }} submitted / {{ fbr_failed_count }} failed</div>
      </div>

      <!-- Branch Locations -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Branches & Outlets</span>
          <div class="metric-icon" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
              <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
          </div>
        </div>
        <div class="metric-value">
          {{ subscription.locations_count }} <span style="font-size: 0.95rem; font-weight: normal; color: var(--text-muted);">/ {{ subscription.max_locations ?? '∞' }}</span>
        </div>
        <div class="metric-desc">Registered retail locations</div>
      </div>
    </div>

    <!-- Modern Tabbed Navigation -->
    <div class="tab-nav-bar">
      <button :class="{ active: currentTab === 'overview' }" @click="switchTab('overview')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7"></rect>
          <rect x="14" y="3" width="7" height="7"></rect>
          <rect x="14" y="14" width="7" height="7"></rect>
          <rect x="3" y="14" width="7" height="7"></rect>
        </svg>
        Overview & Subscription
      </button>

      <button :class="{ active: currentTab === 'sales' }" @click="switchTab('sales')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
        </svg>
        Sales Ledger ({{ sales.total }})
      </button>

      <button :class="{ active: currentTab === 'terminals' }" @click="switchTab('terminals')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
          <line x1="8" y1="21" x2="16" y2="21"></line>
          <line x1="12" y1="17" x2="12" y2="21"></line>
        </svg>
        POS Terminals ({{ terminals.length }})
      </button>

      <button :class="{ active: currentTab === 'staff' }" @click="switchTab('staff')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        Staff & Access ({{ tenant.users.length }})
      </button>

      <button :class="{ active: currentTab === 'invoices' }" @click="switchTab('invoices')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
          <line x1="1" y1="10" x2="23" y2="10"></line>
        </svg>
        SaaS Invoices ({{ saas_invoices.length }})
      </button>

      <button :class="{ active: currentTab === 'audit' }" @click="switchTab('audit')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
        </svg>
        Audit Trail ({{ audit_logs.length }})
      </button>
    </div>

    <!-- TAB 1: OVERVIEW & SUBSCRIPTION -->
    <div v-show="currentTab === 'overview'">
      <!-- Subscription & Quota Card -->
      <div class="card-table" style="margin-bottom: 2rem;">
        <div class="table-header">
          <div>
            <h2>Subscription Package & Quotas</h2>
            <span style="font-size: 0.8rem; color: var(--text-muted);">SaaS billing tier, hardware limits & included features</span>
          </div>
          <button @click="openPlanModal" class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
            Change Package
          </button>
        </div>

        <div style="padding: 1.5rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; border-bottom: 1px solid var(--border-color);">
          <!-- Plan Info -->
          <div>
            <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Active Plan</span>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.35rem;">
              <span style="font-size: 1.25rem; font-weight: 700; color: #a5b4fc;">{{ subscription.plan_name }}</span>
              <span class="badge badge-submitted" style="font-size: 0.75rem;">{{ subscription.status }}</span>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem;">
              Rs. {{ formatMoney(subscription.price_minor) }} / {{ subscription.billing_interval }}
            </p>
          </div>

          <!-- Billing Cycle & Period -->
          <div>
            <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Cycle & Term</span>
            <div style="font-size: 0.95rem; font-weight: 600; margin-top: 0.35rem;">
              Started: {{ subscription.starts_at || 'N/A' }}
            </div>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem;">
              Renewal / Ends: <strong :style="subscription.ends_at ? 'color: #fbbf24;' : 'color: #34d399;'">{{ subscription.ends_at || 'Continuous / No Expiry' }}</strong>
            </p>
          </div>

          <!-- Quota Meters -->
          <div>
            <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Hardware Counter Quota</span>
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600; margin-top: 0.35rem;">
              <span>Terminals Used:</span>
              <span>{{ subscription.active_terminals_count }} / {{ subscription.max_terminals ?? '∞' }}</span>
            </div>
            <div class="progress-bar-bg" style="margin-top: 0.4rem;">
              <div
                class="progress-bar-fill"
                :style="{
                  width: subscription.max_terminals ? Math.min(100, Math.round((subscription.active_terminals_count / subscription.max_terminals) * 100)) + '%' : '25%',
                  background: (subscription.max_terminals && subscription.active_terminals_count >= subscription.max_terminals) ? '#f43f5e' : '#6366f1'
                }"
              ></div>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600; margin-top: 0.75rem;">
              <span>Branches Used:</span>
              <span>{{ subscription.locations_count }} / {{ subscription.max_locations ?? '∞' }}</span>
            </div>
            <div class="progress-bar-bg" style="margin-top: 0.4rem;">
              <div
                class="progress-bar-fill"
                :style="{
                  width: subscription.max_locations ? Math.min(100, Math.round((subscription.locations_count / subscription.max_locations) * 100)) + '%' : '30%',
                  background: '#10b981'
                }"
              ></div>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600; margin-top: 0.75rem;">
              <span>Staff Accounts:</span>
              <span>{{ subscription.active_staff_count }} / {{ subscription.max_staff ?? '∞' }}</span>
            </div>
            <div class="progress-bar-bg" style="margin-top: 0.4rem;">
              <div
                class="progress-bar-fill"
                :style="{
                  width: subscription.max_staff ? Math.min(100, Math.round((subscription.active_staff_count / subscription.max_staff) * 100)) + '%' : '20%',
                  background: (subscription.max_staff && subscription.active_staff_count >= subscription.max_staff) ? '#f43f5e' : '#f59e0b'
                }"
              ></div>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600; margin-top: 0.75rem;">
              <span>Catalog SKUs:</span>
              <span>{{ subscription.products_count }} / {{ subscription.max_skus ?? '∞' }}</span>
            </div>
            <div class="progress-bar-bg" style="margin-top: 0.4rem;">
              <div
                class="progress-bar-fill"
                :style="{
                  width: subscription.max_skus ? Math.min(100, Math.round((subscription.products_count / subscription.max_skus) * 100)) + '%' : '15%',
                  background: (subscription.max_skus && subscription.products_count >= subscription.max_skus) ? '#f43f5e' : '#06b6d4'
                }"
              ></div>
            </div>
          </div>
        </div>

        <!-- Features list -->
        <div style="padding: 1.25rem 1.5rem; background: rgba(15, 23, 42, 0.4); display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
          <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Included Features:</span>
          <span v-for="feat in subscription.features" :key="feat" class="feature-tag">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="3">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            {{ formatFeatureName(feat) }}
          </span>
          <span v-if="subscription.notes" style="font-size: 0.8rem; color: var(--text-muted); margin-left: auto;">
            Note: {{ subscription.notes }}
          </span>
        </div>
      </div>

      <!-- Organization Details & Branch Locations in 2 Columns -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
        <!-- Organization Profile -->
        <div class="card-table">
          <div class="table-header">
            <h2>Organization Details</h2>
            <Link :href="`/superadmin/tenants/${tenant.id}/edit`" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
              Edit Details
            </Link>
          </div>
          <div style="padding: 1.25rem 1.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
            <div>
              <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Tenant Name</span>
              <p style="font-size: 1rem; font-weight: 600; margin-top: 0.25rem;">{{ tenant.name }}</p>
            </div>
            <div>
              <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">NTN</span>
              <p style="font-size: 1rem; font-family: monospace; font-weight: 600; margin-top: 0.25rem;">{{ tenant.ntn }}</p>
            </div>
            <div>
              <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">STRN</span>
              <p style="font-size: 1rem; font-family: monospace; font-weight: 600; margin-top: 0.25rem;">{{ tenant.strn || 'Not Provided' }}</p>
            </div>
            <div>
              <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">FBR POS ID</span>
              <p style="font-size: 1rem; font-family: monospace; font-weight: 600; margin-top: 0.25rem; color: #38bdf8;">{{ tenant.fbr_pos_id }}</p>
            </div>
            <div>
              <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Province</span>
              <p style="font-size: 0.95rem; font-weight: 600; margin-top: 0.25rem; text-transform: capitalize;">{{ tenant.province }}</p>
            </div>
            <div>
              <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Registered On</span>
              <p style="font-size: 0.95rem; color: var(--text-secondary); margin-top: 0.25rem;">{{ tenant.created_at }}</p>
            </div>
          </div>
          <div v-if="tenant.notes" style="padding: 1rem 1.5rem; background: rgba(15, 23, 42, 0.4); border-top: 1px solid var(--border-color);">
            <span style="font-size: 0.75rem; color: #ec4899; text-transform: uppercase; font-weight: 700;">Admin Notes:</span>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem; line-height: 1.5; white-space: pre-wrap;">{{ tenant.notes }}</p>
          </div>
        </div>

        <!-- Branch Locations -->
        <div class="card-table">
          <div class="table-header">
            <div>
              <h2>Branch Locations ({{ tenant.locations.length }})</h2>
              <span style="font-size: 0.75rem; color: var(--text-muted);">Retail Outlets & Warehouses</span>
            </div>
            <button @click="showAddLocationModal = true" class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
              </svg>
              Add Branch
            </button>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>Branch Name</th>
                <th>Address</th>
                <th>Province</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="tenant.locations.length === 0">
                <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
                  No locations created yet.
                </td>
              </tr>
              <tr v-for="loc in tenant.locations" :key="loc.id">
                <td style="font-weight: 600;">{{ loc.name }}</td>
                <td style="font-size: 0.85rem; color: var(--text-secondary);">{{ loc.address || '—' }}</td>
                <td>
                  <span class="badge badge-neutral" style="text-transform: uppercase;">{{ loc.province }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 2: SALES LEDGER -->
    <div v-show="currentTab === 'sales'">
      <div class="card-table" style="margin-bottom: 2.5rem;">
        <!-- Filters & Search Toolbar -->
        <div class="table-header" style="flex-wrap: wrap; gap: 1rem;">
          <div>
            <h2>Tenant Sales Transactions</h2>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Real-time fiscal ledger & FBR IRIS verification status</span>
          </div>

          <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <!-- Export Button -->
            <a :href="exportSalesUrl" class="btn btn-secondary" style="color: #34d399; border-color: rgba(16, 185, 129, 0.4);">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
              </svg>
              Export to CSV
            </a>
          </div>
        </div>

        <!-- Filter Controls -->
        <div style="padding: 1rem 1.5rem; background: rgba(15, 23, 42, 0.4); border-bottom: 1px solid var(--border-color); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
          <!-- Search input -->
          <div style="flex: 1; min-width: 220px; position: relative;">
            <input
              v-model="salesSearchInput"
              @keyup.enter="applySalesFilters"
              type="text"
              class="form-input"
              placeholder="Search by Invoice # or FBR USIN..."
              style="padding-left: 2.2rem; font-size: 0.85rem;"
            />
            <svg
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"
            >
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </div>

          <!-- FBR Status Filter -->
          <select v-model="salesFbrStatusInput" @change="applySalesFilters" class="form-input" style="width: auto; font-size: 0.85rem;">
            <option value="">All FBR Statuses</option>
            <option value="submitted">FBR Submitted</option>
            <option value="failed">FBR Failed</option>
            <option value="pending">FBR Pending</option>
            <option value="offline">Offline / Provisional</option>
          </select>

          <!-- Tender Filter -->
          <select v-model="salesPaymentMethodInput" @change="applySalesFilters" class="form-input" style="width: auto; font-size: 0.85rem;">
            <option value="">All Payment Methods</option>
            <option value="cash">Cash</option>
            <option value="card">Card / POS Terminal</option>
            <option value="raast">Raast Instant Pay</option>
            <option value="wallet">Digital Wallet</option>
          </select>

          <!-- Sale Status Filter -->
          <select v-model="salesStatusInput" @change="applySalesFilters" class="form-input" style="width: auto; font-size: 0.85rem;">
            <option value="">All Sale Statuses</option>
            <option value="completed">Completed</option>
            <option value="refunded">Refunded</option>
            <option value="voided">Voided</option>
          </select>

          <!-- Filter Action Buttons -->
          <button @click="applySalesFilters" class="btn btn-secondary" style="padding: 0.55rem 0.85rem; font-size: 0.85rem;">
            Filter
          </button>
          <button v-if="hasActiveSalesFilters" @click="resetSalesFilters" class="btn btn-secondary" style="padding: 0.55rem 0.85rem; font-size: 0.85rem;">
            Reset
          </button>
        </div>

        <!-- Sales Data Table -->
        <table class="data-table">
          <thead>
            <tr>
              <th>Invoice #</th>
              <th>Date & Time</th>
              <th>Branch / Cashier</th>
              <th>Tender</th>
              <th>Items</th>
              <th style="text-align: right;">Amount (PKR)</th>
              <th>FBR Status</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="sales.data.length === 0">
              <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                No sales records found matching the criteria.
              </td>
            </tr>
            <tr v-for="sale in sales.data" :key="sale.id">
              <td>
                <button
                  @click="openSaleDetail(sale)"
                  style="background: none; border: none; padding: 0; font-family: monospace; font-weight: 700; color: #38bdf8; cursor: pointer; text-decoration: underline;"
                >
                  {{ sale.sale_number }}
                </button>
              </td>
              <td style="font-size: 0.825rem; color: var(--text-secondary);">
                {{ sale.created_at }}
                <div style="font-size: 0.725rem; color: var(--text-muted);">{{ sale.created_at_human }}</div>
              </td>
              <td>
                <div style="font-weight: 600; font-size: 0.85rem;">{{ sale.location_name }}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Cashier: {{ sale.cashier_name }}</div>
              </td>
              <td>
                <span class="badge" style="background: rgba(148, 163, 184, 0.15); color: #cbd5e1; text-transform: uppercase; font-size: 0.75rem;">
                  {{ sale.payment_method }}
                </span>
              </td>
              <td style="font-size: 0.85rem;">
                {{ sale.items_count }} {{ sale.items_count === 1 ? 'item' : 'items' }}
              </td>
              <td style="text-align: right;">
                <div style="font-weight: 700; font-family: monospace; font-size: 0.95rem;">
                  Rs. {{ formatMoney(sale.total_minor) }}
                </div>
                <div style="font-size: 0.725rem; color: var(--text-muted);">
                  Tax: Rs. {{ formatMoney(sale.tax_minor) }}
                </div>
              </td>
              <td>
                <span
                  class="badge"
                  :class="{
                    'badge-submitted': sale.fbr_status === 'submitted',
                    'badge-failed': sale.fbr_status === 'failed',
                    'badge-neutral': sale.fbr_status === 'pending' || sale.fbr_status === 'offline'
                  }"
                  style="font-size: 0.75rem;"
                >
                  <span class="badge-dot"></span>
                  {{ sale.fbr_status }}
                </span>
                <div v-if="sale.fbr_usin" style="font-size: 0.7rem; font-family: monospace; color: var(--text-muted); margin-top: 0.2rem;">
                  USIN: {{ sale.fbr_usin }}
                </div>
              </td>
              <td style="text-align: right;">
                <button
                  @click="openSaleDetail(sale)"
                  class="btn btn-secondary"
                  style="padding: 0.25rem 0.5rem; font-size: 0.75rem;"
                >
                  View Details
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Speedy Pagination -->
        <div v-if="sales.links && sales.links.length > 3" class="pagination">
          <button
            v-for="(link, idx) in sales.links"
            :key="idx"
            @click="goToSalesPage(link.url)"
            class="page-btn"
            :class="{ active: link.active, disabled: !link.url }"
            v-html="link.label"
          />
        </div>
      </div>
    </div>

    <!-- TAB 3: POS TERMINALS -->
    <div v-show="currentTab === 'terminals'">
      <div class="card-table" style="margin-bottom: 2.5rem;">
        <div class="table-header">
          <div>
            <h2>Terminal Hardware Counters ({{ terminals.length }})</h2>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Physical desktop & Android POS machines paired to this merchant</span>
          </div>

          <button @click="showPairingModal = true" class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Generate Pairing Code
          </button>
        </div>

        <table class="data-table">
          <thead>
            <tr>
              <th>Counter / Code</th>
              <th>Branch</th>
              <th>Status</th>
              <th>Platform & Version</th>
              <th>Heartbeat & Sync</th>
              <th>Pairing Status</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="terminals.length === 0">
              <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                No POS counter terminals paired yet. Click "Generate Pairing Code" to connect a terminal.
              </td>
            </tr>
            <tr v-for="t in terminals" :key="t.id">
              <td>
                <div style="font-weight: 700; color: var(--text-primary);">{{ t.name }}</div>
                <code style="font-size: 0.75rem; color: #38bdf8;">{{ t.terminal_code }}</code>
              </td>
              <td style="font-size: 0.85rem;">
                {{ t.location_name }}
              </td>
              <td>
                <span
                  v-if="t.is_online"
                  class="badge badge-submitted"
                  style="font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;"
                >
                  <span class="pulse-online"></span>
                  Online
                </span>
                <span
                  v-else
                  class="badge"
                  style="background: rgba(148, 163, 184, 0.15); color: #94a3b8; font-size: 0.75rem;"
                >
                  <span class="badge-dot" style="background: #94a3b8;"></span>
                  Offline
                </span>
              </td>
              <td>
                <div style="font-size: 0.825rem; font-weight: 600; text-transform: capitalize;">
                  {{ t.platform_os }}
                </div>
                <div style="font-size: 0.725rem; color: var(--text-muted);">
                  v{{ t.app_version }}
                </div>
              </td>
              <td style="font-size: 0.8rem; color: var(--text-secondary);">
                <div>Ping: {{ t.last_heartbeat_at || 'Never' }}</div>
                <div style="font-size: 0.725rem; color: var(--text-muted);">Sync: {{ t.last_sync_at || 'Never' }}</div>
                <div v-if="t.ip_address" style="font-size: 0.7rem; font-family: monospace; color: var(--text-muted);">IP: {{ t.ip_address }}</div>
              </td>
              <td>
                <div v-if="t.pairing_status === 'pending_pairing'">
                  <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; font-size: 0.75rem;">
                    Code: <strong style="letter-spacing: 0.05em; font-family: monospace;">{{ t.pairing_token }}</strong>
                  </span>
                  <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.2rem;">
                    Expires: {{ t.pairing_token_expires_at }}
                  </div>
                </div>
                <div v-else-if="t.pairing_status === 'revoked'">
                  <span class="badge badge-failed" style="font-size: 0.75rem;">
                    Revoked
                  </span>
                  <div v-if="t.revocation_reason" style="font-size: 0.7rem; color: #fecdd3; margin-top: 0.2rem;">
                    {{ t.revocation_reason }}
                  </div>
                </div>
                <div v-else>
                  <span class="badge badge-submitted" style="font-size: 0.75rem;">
                    Paired & Active
                  </span>
                </div>
              </td>
              <td style="text-align: right;">
                <div style="display: flex; gap: 0.4rem; justify-content: flex-end; align-items: center;">
                  <button
                    v-if="t.pairing_status !== 'revoked' && t.is_active"
                    @click="openRevokeModal(t)"
                    class="btn btn-secondary"
                    style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: #fb7185; border-color: rgba(244, 63, 94, 0.4);"
                  >
                    Revoke
                  </button>
                  <button
                    v-else
                    @click="reactivateTerminal(t)"
                    class="btn btn-secondary"
                    style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: #34d399; border-color: rgba(16, 185, 129, 0.4);"
                  >
                    Reactivate
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 4: STAFF & USERS -->
    <div v-show="currentTab === 'staff'">
      <div class="card-table" style="margin-bottom: 2.5rem;">
        <div class="table-header">
          <div>
            <h2>Staff Directory ({{ tenant.users.length }})</h2>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Owners, Store Managers & Cashiers</span>
          </div>
          <button @click="showAddUserModal = true" class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Staff
          </button>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Security / 2FA</th>
              <th>Registered</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="tenant.users.length === 0">
              <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
                No staff users registered.
              </td>
            </tr>
            <tr v-for="user in tenant.users" :key="user.id">
              <td>
                <div style="font-weight: 600;">{{ user.name }}</div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">{{ user.email }}</div>
              </td>
              <td>
                <span
                  class="badge"
                  :style="getRoleBadgeStyle(user.role)"
                  style="text-transform: capitalize; font-size: 0.75rem;"
                >
                  {{ user.role }}
                </span>
              </td>
              <td>
                <span v-if="user.two_factor_enabled" class="badge badge-submitted" style="font-size: 0.725rem;">
                  2FA Active
                </span>
                <span v-else class="badge" style="background: rgba(148, 163, 184, 0.15); color: #94a3b8; font-size: 0.725rem;">
                  2FA Disabled
                </span>
              </td>
              <td style="font-size: 0.8rem; color: var(--text-secondary);">
                {{ user.created_at }}
              </td>
              <td style="text-align: right;">
                <div style="display: flex; gap: 0.4rem; justify-content: flex-end; align-items: center;">
                  <!-- Impersonate -->
                  <button
                    v-if="!tenant.is_suspended"
                    @click="impersonate(user.id)"
                    class="btn btn-secondary"
                    style="padding: 0.25rem 0.5rem; font-size: 0.72rem; color: #fbbf24;"
                    title="Impersonate User"
                  >
                    Login As
                  </button>

                  <!-- Reset Password -->
                  <button
                    @click="openResetPasswordModal(user)"
                    class="btn btn-secondary"
                    style="padding: 0.25rem 0.5rem; font-size: 0.72rem;"
                    title="Reset Password"
                  >
                    Reset PWD
                  </button>

                  <!-- Remove User -->
                  <button
                    @click="deleteUser(user)"
                    class="btn btn-secondary"
                    style="padding: 0.25rem 0.45rem; font-size: 0.72rem; color: #fb7185;"
                    title="Remove User"
                  >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="3 6 5 6 21 6"></polyline>
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 5: SAAS INVOICES -->
    <div v-show="currentTab === 'invoices'">
      <div class="card-table" style="margin-bottom: 2.5rem;">
        <div class="table-header">
          <div>
            <h2>Platform SaaS Billing Invoices ({{ saas_invoices.length }})</h2>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Software subscription charges, 1Bill / Kuickpay consumer numbers & payment verification</span>
          </div>
        </div>

        <table class="data-table">
          <thead>
            <tr>
              <th>Invoice #</th>
              <th>Period</th>
              <th>Plan Tier</th>
              <th>1Bill / Kuickpay ID</th>
              <th style="text-align: right;">Amount (PKR)</th>
              <th>Status</th>
              <th>Due Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="saas_invoices.length === 0">
              <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                No SaaS subscription invoices generated for this tenant yet.
              </td>
            </tr>
            <tr v-for="inv in saas_invoices" :key="inv.id">
              <td style="font-family: monospace; font-weight: 700;">
                {{ inv.invoice_number }}
              </td>
              <td style="font-size: 0.825rem; color: var(--text-secondary);">
                {{ inv.period_start }} to {{ inv.period_end }}
                <div style="font-size: 0.725rem; text-transform: capitalize; color: var(--text-muted);">{{ inv.billing_cycle }} cycle</div>
              </td>
              <td>
                <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #818cf8; font-size: 0.75rem;">
                  {{ inv.plan_name }}
                </span>
              </td>
              <td style="font-size: 0.8rem; font-family: monospace;">
                <div v-if="inv.onebill_consumer_number">1Bill: {{ inv.onebill_consumer_number }}</div>
                <div v-if="inv.kuickpay_consumer_number">Kuickpay: {{ inv.kuickpay_consumer_number }}</div>
                <span v-if="!inv.onebill_consumer_number && !inv.kuickpay_consumer_number" style="color: var(--text-muted);">—</span>
              </td>
              <td style="text-align: right; font-weight: 700; font-family: monospace;">
                Rs. {{ formatMoney(inv.total_paisa) }}
              </td>
              <td>
                <span
                  class="badge"
                  :class="{
                    'badge-submitted': inv.status === 'paid',
                    'badge-failed': inv.status === 'void',
                    'badge-neutral': inv.status === 'unpaid' || inv.status === 'pending_review'
                  }"
                  style="text-transform: capitalize; font-size: 0.75rem;"
                >
                  <span class="badge-dot"></span>
                  {{ inv.status.replace('_', ' ') }}
                </span>
              </td>
              <td style="font-size: 0.8rem; color: var(--text-muted);">
                {{ inv.due_date || 'N/A' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 6: AUDIT TRAIL -->
    <div v-show="currentTab === 'audit'">
      <div class="card-table" style="margin-bottom: 2.5rem;">
        <div class="table-header">
          <div>
            <h2>Tenant Mutation Audit Trail ({{ audit_logs.length }})</h2>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Immutable chronological log of platform administrator actions</span>
          </div>
        </div>

        <table class="data-table">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Action / Event</th>
              <th>Target Model</th>
              <th>Performed By</th>
              <th>IP Address</th>
              <th>Changes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="audit_logs.length === 0">
              <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                No audit events recorded for this tenant yet.
              </td>
            </tr>
            <tr v-for="log in audit_logs" :key="log.id">
              <td style="font-size: 0.8rem; color: var(--text-secondary); white-space: nowrap;">
                {{ log.created_at }}
                <div style="font-size: 0.725rem; color: var(--text-muted);">{{ log.created_at_human }}</div>
              </td>
              <td>
                <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #818cf8; font-family: monospace; font-size: 0.75rem;">
                  {{ log.event }}
                </span>
              </td>
              <td style="font-size: 0.85rem; font-weight: 600;">
                {{ log.auditable_type }}
              </td>
              <td style="font-size: 0.825rem;">
                {{ log.user_name }}
              </td>
              <td style="font-size: 0.75rem; font-family: monospace; color: var(--text-muted);">
                {{ log.ip_address || '—' }}
              </td>
              <td style="font-size: 0.75rem; font-family: monospace; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <span v-if="log.new_values">{{ JSON.stringify(log.new_values) }}</span>
                <span v-else style="color: var(--text-muted);">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODAL: CHANGE SUBSCRIPTION PLAN -->
    <div v-if="showPlanModal" class="modal-backdrop">
      <div class="modal-card">
        <div class="modal-header">
          <h3>Assign Subscription Plan</h3>
          <button @click="showPlanModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitAssignPlan">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Subscription Tier *</label>
              <select v-model="planForm.plan_id" class="form-input" required>
                <option v-for="p in available_plans" :key="p.id" :value="p.id">
                  {{ p.name }} — Rs. {{ formatMoney(p.price_minor) }}/{{ p.billing_interval }} (Max {{ p.max_terminals ?? '∞' }} Terminals, {{ p.max_locations ?? '∞' }} Branches)
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Status *</label>
              <select v-model="planForm.status" class="form-input" required>
                <option value="active">Active</option>
                <option value="trialing">Trialing</option>
                <option value="past_due">Past Due</option>
                <option value="canceled">Canceled</option>
              </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Start Date</label>
                <input v-model="planForm.starts_at" type="date" class="form-input" />
              </div>
              <div class="form-group">
                <label class="form-label">Expiry / Renewal Date</label>
                <input v-model="planForm.ends_at" type="date" class="form-input" />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Admin Notes</label>
              <textarea
                v-model="planForm.notes"
                class="form-input"
                rows="2"
                placeholder="Reason for upgrade, promotional terms, contract details..."
              ></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="showPlanModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="planForm.processing">
              {{ planForm.processing ? 'Saving...' : 'Assign Plan' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: GENERATE PAIRING CODE -->
    <div v-if="showPairingModal" class="modal-backdrop">
      <div class="modal-card">
        <div class="modal-header">
          <h3>Generate Terminal Pairing Code</h3>
          <button @click="showPairingModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitPairingCode">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Branch Location</label>
              <select v-model="pairingForm.location_id" class="form-input">
                <option :value="null">Unassigned / Main Branch</option>
                <option v-for="loc in tenant.locations" :key="loc.id" :value="loc.id">
                  {{ loc.name }} ({{ loc.province }})
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Terminal Code *</label>
              <input
                v-model="pairingForm.terminal_code"
                type="text"
                class="form-input"
                placeholder="e.g. REG-01"
                required
              />
              <span style="font-size: 0.725rem; color: var(--text-muted);">Unique identifier for this cashier workstation</span>
            </div>

            <div class="form-group">
              <label class="form-label">Friendly Terminal Name *</label>
              <input
                v-model="pairingForm.name"
                type="text"
                class="form-input"
                placeholder="e.g. Main Cash Counter 1"
                required
              />
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="showPairingModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="pairingForm.processing">
              {{ pairingForm.processing ? 'Generating...' : 'Generate 8-Digit Code' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: REVOKE TERMINAL -->
    <div v-if="showRevokeModal" class="modal-backdrop">
      <div class="modal-card">
        <div class="modal-header">
          <h3 style="color: #fb7185;">Revoke Terminal Access</h3>
          <button @click="showRevokeModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitRevokeTerminal">
          <div class="modal-body">
            <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1rem;">
              Are you sure you want to revoke terminal <strong>{{ targetTerminal?.terminal_code }}</strong> ({{ targetTerminal?.name }})?
              The physical counter device will be instantly disconnected and blocked from synchronization.
            </p>
            <div class="form-group">
              <label class="form-label">Revocation Reason</label>
              <input
                v-model="revokeForm.reason"
                type="text"
                class="form-input"
                placeholder="e.g. Decommissioned hardware, counter relocated..."
                required
              />
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="showRevokeModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn" style="background: #e11d48; color: #fff;" :disabled="revokeForm.processing">
              {{ revokeForm.processing ? 'Revoking...' : 'Confirm Revoke' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: SALE ITEMIZED DETAILS -->
    <div v-if="showSaleDetailModal && selectedSale" class="modal-backdrop">
      <div class="modal-card" style="max-width: 680px;">
        <div class="modal-header">
          <div>
            <h3>Invoice #{{ selectedSale.sale_number }}</h3>
            <span style="font-size: 0.75rem; color: var(--text-muted);">{{ selectedSale.created_at }} • Cashier: {{ selectedSale.cashier_name }}</span>
          </div>
          <button @click="showSaleDetailModal = false" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
          <!-- Itemized Table -->
          <table class="data-table" style="margin-bottom: 1.25rem;">
            <thead>
              <tr>
                <th>Item / HS Code</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Unit Rate</th>
                <th style="text-align: right;">Tax</th>
                <th style="text-align: right;">Line Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in selectedSale.items" :key="item.id">
                <td>
                  <div style="font-weight: 600;">{{ item.product_name }}</div>
                  <code style="font-size: 0.725rem; color: var(--text-muted);">HS: {{ item.hs_code }}</code>
                </td>
                <td style="text-align: center; font-weight: 600;">{{ item.quantity }}</td>
                <td style="text-align: right; font-family: monospace;">Rs. {{ formatMoney(item.unit_price_minor) }}</td>
                <td style="text-align: right; font-family: monospace; color: #38bdf8;">Rs. {{ formatMoney(item.tax_amount_minor) }}</td>
                <td style="text-align: right; font-family: monospace; font-weight: 700;">Rs. {{ formatMoney(item.line_total_minor) }}</td>
              </tr>
            </tbody>
          </table>

          <!-- Financial Breakdown -->
          <div style="background: rgba(15, 23, 42, 0.5); border: 1px solid var(--border-color); border-radius: 0.5rem; padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
              <span style="color: var(--text-muted);">Subtotal:</span>
              <span style="font-family: monospace; font-weight: 600;">Rs. {{ formatMoney(selectedSale.subtotal_minor) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
              <span style="color: var(--text-muted);">Total Sales Tax:</span>
              <span style="font-family: monospace; font-weight: 600; color: #38bdf8;">Rs. {{ formatMoney(selectedSale.tax_minor) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 1.05rem; font-weight: 700; border-top: 1px solid var(--border-color); padding-top: 0.5rem; margin-top: 0.5rem;">
              <span>Total Payable:</span>
              <span style="color: #34d399; font-family: monospace;">Rs. {{ formatMoney(selectedSale.total_minor) }}</span>
            </div>
          </div>

          <!-- FBR Technical Telemetry -->
          <div style="background: rgba(15, 23, 42, 0.4); border-radius: 0.5rem; padding: 0.85rem 1rem;">
            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem;">FBR Technical Response</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; font-size: 0.825rem;">
              <div>
                <span style="color: var(--text-muted);">FBR Status:</span>
                <strong style="margin-left: 0.35rem; text-transform: uppercase;">{{ selectedSale.fbr_status }}</strong>
              </div>
              <div>
                <span style="color: var(--text-muted);">FBR USIN:</span>
                <strong style="margin-left: 0.35rem; font-family: monospace;">{{ selectedSale.fbr_usin || 'None' }}</strong>
              </div>
              <div v-if="selectedSale.fbr_submitted_at">
                <span style="color: var(--text-muted);">Submitted At:</span>
                <span style="margin-left: 0.35rem;">{{ selectedSale.fbr_submitted_at }}</span>
              </div>
              <div v-if="selectedSale.fbr_error" style="grid-column: 1 / -1; color: #fb7185;">
                <span style="color: var(--text-muted);">Error:</span>
                <span style="margin-left: 0.35rem;">{{ selectedSale.fbr_error }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" @click="showSaleDetailModal = false" class="btn btn-secondary">Close</button>
        </div>
      </div>
    </div>

    <!-- MODAL: ADD STAFF USER -->
    <div v-if="showAddUserModal" class="modal-backdrop">
      <div class="modal-card">
        <div class="modal-header">
          <h3>Add Staff User to {{ tenant.name }}</h3>
          <button @click="showAddUserModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitAddUser">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Full Name *</label>
              <input v-model="addUserForm.name" type="text" class="form-input" placeholder="e.g. Muhammad Ali" required />
            </div>
            <div class="form-group">
              <label class="form-label">Email Address *</label>
              <input v-model="addUserForm.email" type="email" class="form-input" placeholder="ali@example.com" required />
            </div>
            <div class="form-group">
              <label class="form-label">Password *</label>
              <input v-model="addUserForm.password" type="password" class="form-input" placeholder="Min 8 characters" required />
            </div>
            <div class="form-group">
              <label class="form-label">Role *</label>
              <select v-model="addUserForm.role" class="form-input" required>
                <option value="cashier">Cashier</option>
                <option value="manager">Store Manager</option>
                <option value="owner">Organization Owner</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="showAddUserModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="addUserForm.processing">Create Staff User</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: RESET PASSWORD -->
    <div v-if="showResetPasswordModal" class="modal-backdrop">
      <div class="modal-card">
        <div class="modal-header">
          <h3>Reset Password: {{ targetUser?.name }}</h3>
          <button @click="showResetPasswordModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitResetPassword">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">New Password *</label>
              <input v-model="resetPasswordForm.password" type="password" class="form-input" placeholder="Min 8 characters" required />
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="showResetPasswordModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="resetPasswordForm.processing">Set New Password</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: ADD BRANCH LOCATION -->
    <div v-if="showAddLocationModal" class="modal-backdrop">
      <div class="modal-card">
        <div class="modal-header">
          <h3>Add Branch Location</h3>
          <button @click="showAddLocationModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitAddLocation">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Branch Name *</label>
              <input v-model="addLocationForm.name" type="text" class="form-input" placeholder="e.g. Gulberg Flagship Branch" required />
            </div>
            <div class="form-group">
              <label class="form-label">Address *</label>
              <input v-model="addLocationForm.address" type="text" class="form-input" placeholder="Shop #, Street, City" required />
            </div>
            <div class="form-group">
              <label class="form-label">Province *</label>
              <select v-model="addLocationForm.province" class="form-input" required>
                <option value="punjab">Punjab</option>
                <option value="sindh">Sindh</option>
                <option value="kp">Khyber Pakhtunkhwa</option>
                <option value="balochistan">Balochistan</option>
                <option value="federal">Federal Territory</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="showAddLocationModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="addLocationForm.processing">Create Location</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: SUSPEND / REACTIVATE -->
    <div v-if="showSuspendModal" class="modal-backdrop">
      <div class="modal-card">
        <div class="modal-header">
          <h3>{{ tenant.is_suspended ? 'Reactivate Tenant' : 'Suspend Tenant' }}</h3>
          <button @click="showSuspendModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitSuspendToggle">
          <div class="modal-body">
            <div v-if="!tenant.is_suspended" class="form-group">
              <label class="form-label">Suspension Reason</label>
              <textarea
                v-model="suspendForm.suspension_reason"
                class="form-input"
                rows="3"
                placeholder="Reason for suspension (non-payment, compliance audit, violation)..."
              ></textarea>
            </div>
            <p v-else style="font-size: 0.9rem; color: var(--text-secondary); margin: 0;">
              Are you sure you want to reactivate <strong>{{ tenant.name }}</strong>? The merchant and cashier terminals will regain immediate access.
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" @click="showSuspendModal = false" class="btn btn-secondary">Cancel</button>
            <button
              type="submit"
              class="btn"
              :class="tenant.is_suspended ? 'btn-primary' : 'btn'"
              :style="!tenant.is_suspended ? 'background: #e11d48; color: #fff;' : ''"
              :disabled="suspendForm.processing"
            >
              {{ tenant.is_suspended ? 'Confirm Reactivation' : 'Confirm Suspension' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: PURGE / DELETE TENANT -->
    <div v-if="showDeleteModal" class="modal-backdrop">
      <div class="modal-card">
        <div class="modal-header">
          <h3 style="color: #fb7185;">Purge Tenant Organization</h3>
          <button @click="showDeleteModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitPurgeTenant">
          <div class="modal-body">
            <div style="background: rgba(244, 63, 94, 0.12); border: 1px solid rgba(244, 63, 94, 0.35); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem;">
              <p style="font-size: 0.88rem; color: #fecdd3; margin: 0; line-height: 1.5;">
                <strong>Warning:</strong> You are about to permanently purge <strong>{{ tenant.name }}</strong> (NTN: {{ tenant.ntn }}). All terminals, products, stock levels, staff users, and branch locations will be permanently deleted.
              </p>
            </div>
            <div v-if="fbr_submitted_count > 0" class="form-group" style="margin-bottom: 1rem;">
              <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.85rem; color: #fbbf24; cursor: pointer;">
                <input v-model="deleteForm.force_purge_fiscal_archive" type="checkbox" style="margin-top: 0.2rem;" />
                <span>Override FBR statutory compliance guard: This tenant contains {{ fbr_submitted_count }} official FBR invoices. Force deletion anyway.</span>
              </label>
            </div>
            <div class="form-group">
              <label class="form-label">Type tenant name to confirm: <code>{{ tenant.name }}</code></label>
              <input
                v-model="deleteConfirmName"
                type="text"
                class="form-input"
                :placeholder="tenant.name"
                required
              />
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="showDeleteModal = false" class="btn btn-secondary">Cancel</button>
            <button
              type="submit"
              class="btn"
              style="background: #e11d48; color: #fff;"
              :disabled="deleteConfirmName !== tenant.name || deleteForm.processing"
            >
              {{ deleteForm.processing ? 'Purging...' : 'Permanently Delete Tenant' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  tenant: { type: Object, required: true },
  subscription: { type: Object, required: true },
  available_plans: { type: Array, default: () => [] },
  sales: { type: Object, required: true },
  sales_filters: { type: Object, default: () => ({}) },
  terminals: { type: Array, default: () => [] },
  saas_invoices: { type: Array, default: () => [] },
  audit_logs: { type: Array, default: () => [] },
  recent_sales_count: { type: Number, default: 0 },
  lifetime_sales_count: { type: Number, default: 0 },
  lifetime_volume_minor: { type: Number, default: 0 },
  fbr_success_rate: { type: Number, default: 0 },
  fbr_submitted_count: { type: Number, default: 0 },
  fbr_failed_count: { type: Number, default: 0 },
});

// Tab state (check URL param or default to overview)
const urlParams = new URLSearchParams(window.location.search);
const currentTab = ref(urlParams.get('tab') || 'overview');

function switchTab(tab) {
  currentTab.value = tab;
  const url = new URL(window.location.href);
  url.searchParams.set('tab', tab);
  window.history.replaceState({}, '', url);
}

// Sales Filter Controls
const salesSearchInput = ref(props.sales_filters.sales_search || '');
const salesFbrStatusInput = ref(props.sales_filters.sales_fbr_status || '');
const salesPaymentMethodInput = ref(props.sales_filters.sales_payment_method || '');
const salesStatusInput = ref(props.sales_filters.sales_status || '');

const hasActiveSalesFilters = computed(() => {
  return !!(salesSearchInput.value || salesFbrStatusInput.value || salesPaymentMethodInput.value || salesStatusInput.value);
});

function applySalesFilters() {
  router.get(
    window.location.pathname,
    {
      tab: 'sales',
      sales_search: salesSearchInput.value || undefined,
      sales_fbr_status: salesFbrStatusInput.value || undefined,
      sales_payment_method: salesPaymentMethodInput.value || undefined,
      sales_status: salesStatusInput.value || undefined,
    },
    { preserveState: true, preserveScroll: true, only: ['sales', 'sales_filters'] }
  );
}

function resetSalesFilters() {
  salesSearchInput.value = '';
  salesFbrStatusInput.value = '';
  salesPaymentMethodInput.value = '';
  salesStatusInput.value = '';
  router.get(
    window.location.pathname,
    { tab: 'sales' },
    { preserveState: true, preserveScroll: true, only: ['sales', 'sales_filters'] }
  );
}

function goToSalesPage(url) {
  if (!url) return;
  router.get(url, {}, { preserveState: true, preserveScroll: true, only: ['sales', 'sales_filters'] });
}

const exportSalesUrl = computed(() => {
  const params = new URLSearchParams();
  if (salesSearchInput.value) params.set('sales_search', salesSearchInput.value);
  if (salesFbrStatusInput.value) params.set('sales_fbr_status', salesFbrStatusInput.value);
  if (salesPaymentMethodInput.value) params.set('sales_payment_method', salesPaymentMethodInput.value);
  if (salesStatusInput.value) params.set('sales_status', salesStatusInput.value);
  const q = params.toString();
  return `/superadmin/tenants/${props.tenant.id}/sales/export${q ? '?' + q : ''}`;
});

// Sale Details Modal
const showSaleDetailModal = ref(false);
const selectedSale = ref(null);
function openSaleDetail(sale) {
  selectedSale.value = sale;
  showSaleDetailModal.value = true;
}

// Plan Management
const showPlanModal = ref(false);
const planForm = useForm({
  plan_id: props.subscription.plan_id || '',
  status: props.subscription.status || 'active',
  starts_at: props.subscription.starts_at || '',
  ends_at: props.subscription.ends_at || '',
  notes: props.subscription.notes || '',
});

function openPlanModal() {
  planForm.plan_id = props.subscription.plan_id || (props.available_plans[0]?.id || '');
  planForm.status = props.subscription.status || 'active';
  planForm.starts_at = props.subscription.starts_at || '';
  planForm.ends_at = props.subscription.ends_at || '';
  planForm.notes = props.subscription.notes || '';
  showPlanModal.value = true;
}

function submitAssignPlan() {
  planForm.post(`/superadmin/tenants/${props.tenant.id}/plan`, {
    preserveScroll: true,
    onSuccess: () => {
      showPlanModal.value = false;
    },
  });
}

// Terminal Pairing Code
const showPairingModal = ref(false);
const pairingForm = useForm({
  terminal_code: `REG-${String(props.terminals.length + 1).padStart(2, '0')}`,
  name: `Counter ${props.terminals.length + 1}`,
  location_id: props.tenant.locations[0]?.id || null,
});

function submitPairingCode() {
  pairingForm.post(`/superadmin/tenants/${props.tenant.id}/terminals/pairing-code`, {
    preserveScroll: true,
    onSuccess: () => {
      showPairingModal.value = false;
      currentTab.value = 'terminals';
    },
  });
}

// Terminal Revoke
const showRevokeModal = ref(false);
const targetTerminal = ref(null);
const revokeForm = useForm({
  reason: '',
});

function openRevokeModal(terminal) {
  targetTerminal.value = terminal;
  revokeForm.reason = '';
  showRevokeModal.value = true;
}

function submitRevokeTerminal() {
  if (!targetTerminal.value) return;
  revokeForm.post(`/superadmin/tenants/${props.tenant.id}/terminals/${targetTerminal.value.id}/revoke`, {
    preserveScroll: true,
    onSuccess: () => {
      showRevokeModal.value = false;
      targetTerminal.value = null;
    },
  });
}

function reactivateTerminal(terminal) {
  if (confirm(`Reactivate POS terminal "${terminal.terminal_code}" (${terminal.name})?`)) {
    router.post(`/superadmin/tenants/${props.tenant.id}/terminals/${terminal.id}/reactivate`, {}, {
      preserveScroll: true,
    });
  }
}

// User Actions
const primaryOwner = computed(() => {
  return props.tenant.users.find(u => u.role === 'owner') || props.tenant.users[0] || null;
});

const showAddUserModal = ref(false);
const addUserForm = useForm({
  name: '',
  email: '',
  password: '',
  role: 'cashier',
});

function submitAddUser() {
  addUserForm.post(`/superadmin/tenants/${props.tenant.id}/users`, {
    preserveScroll: true,
    onSuccess: () => {
      showAddUserModal.value = false;
      addUserForm.reset();
    },
  });
}

const showResetPasswordModal = ref(false);
const targetUser = ref(null);
const resetPasswordForm = useForm({ password: '' });

function openResetPasswordModal(user) {
  targetUser.value = user;
  resetPasswordForm.reset();
  showResetPasswordModal.value = true;
}

function submitResetPassword() {
  if (!targetUser.value) return;
  resetPasswordForm.post(`/superadmin/tenants/${props.tenant.id}/users/${targetUser.value.id}/reset-password`, {
    preserveScroll: true,
    onSuccess: () => {
      showResetPasswordModal.value = false;
      targetUser.value = null;
    },
  });
}

function deleteUser(user) {
  if (confirm(`Remove staff user "${user.name}" (${user.role}) from this tenant?`)) {
    router.delete(`/superadmin/tenants/${props.tenant.id}/users/${user.id}`, {
      preserveScroll: true,
    });
  }
}

function impersonate(userId) {
  if (confirm('Sign in as this user? You will be redirected to the tenant portal.')) {
    router.post(`/superadmin/impersonate/${userId}`);
  }
}

// Branch Locations
const showAddLocationModal = ref(false);
const addLocationForm = useForm({
  name: '',
  address: '',
  province: props.tenant.province || 'punjab',
});

function submitAddLocation() {
  addLocationForm.post(`/superadmin/tenants/${props.tenant.id}/locations`, {
    preserveScroll: true,
    onSuccess: () => {
      showAddLocationModal.value = false;
      addLocationForm.reset();
    },
  });
}

// Suspend / Reactivate
const showSuspendModal = ref(false);
const suspendForm = useForm({ suspension_reason: '' });

function openSuspendModal() {
  suspendForm.suspension_reason = props.tenant.suspension_reason || '';
  showSuspendModal.value = true;
}

function submitSuspendToggle() {
  suspendForm.post(`/superadmin/tenants/${props.tenant.id}/suspend`, {
    preserveScroll: true,
    onSuccess: () => {
      showSuspendModal.value = false;
    },
  });
}

// Purge / Delete Tenant
const showDeleteModal = ref(false);
const deleteConfirmName = ref('');
const deleteForm = useForm({
  force_purge_fiscal_archive: false,
});

function openDeleteModal() {
  deleteConfirmName.value = '';
  deleteForm.force_purge_fiscal_archive = false;
  showDeleteModal.value = true;
}

function submitPurgeTenant() {
  deleteForm.delete(`/superadmin/tenants/${props.tenant.id}`, {
    onSuccess: () => {
      showDeleteModal.value = false;
    },
  });
}

// Helpers
function formatMoney(minor) {
  if (minor === undefined || minor === null) return '0.00';
  return (Number(minor) / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatFeatureName(feat) {
  return String(feat).replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function getRoleBadgeStyle(role) {
  if (role === 'owner') return 'background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3);';
  if (role === 'manager') return 'background: rgba(99, 102, 241, 0.15); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.3);';
  return 'background: rgba(6, 182, 212, 0.15); color: #22d3ee; border: 1px solid rgba(6, 182, 212, 0.3);';
}
</script>

<style scoped>
.tab-nav-bar {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 0.25rem;
  overflow-x: auto;
}

.tab-nav-bar button {
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  color: var(--text-muted);
  font-size: 0.9rem;
  font-weight: 600;
  padding: 0.65rem 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease-in-out;
}

.tab-nav-bar button:hover {
  color: var(--text-primary);
}

.tab-nav-bar button.active {
  color: #818cf8;
  border-bottom-color: #6366f1;
}

.progress-bar-bg {
  height: 6px;
  background: rgba(148, 163, 184, 0.2);
  border-radius: 999px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.3s ease;
}

.feature-tag {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.25rem 0.65rem;
  background: rgba(30, 41, 59, 0.7);
  border: 1px solid var(--border-color);
  border-radius: 999px;
  font-size: 0.775rem;
  font-weight: 600;
  color: var(--text-primary);
}

.pulse-online {
  width: 8px;
  height: 8px;
  background: #34d399;
  border-radius: 50%;
  box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7);
  animation: pulseGreen 1.8s infinite;
}

@keyframes pulseGreen {
  0% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7);
  }
  70% {
    transform: scale(1);
    box-shadow: 0 0 0 6px rgba(52, 211, 153, 0);
  }
  100% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(52, 211, 153, 0);
  }
}

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
  z-index: 9999;
  padding: 1rem;
}

.modal-card {
  background: #1e293b;
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  overflow: hidden;
}

.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-header h3 {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0;
}

.modal-close {
  background: transparent;
  border: none;
  color: var(--text-muted);
  font-size: 1.5rem;
  cursor: pointer;
  line-height: 1;
  padding: 0;
}

.modal-close:hover {
  color: #fff;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border-color);
  background: rgba(15, 23, 42, 0.4);
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}
</style>
