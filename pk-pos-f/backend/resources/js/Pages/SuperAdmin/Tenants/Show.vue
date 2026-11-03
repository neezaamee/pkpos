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
          Impersonate Owner
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

    <!-- Tenant Stats & Overview -->
    <div class="grid-cards" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
      <!-- Recent Sales (Last 7 Days) -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Sales (Last 7d)</span>
          <div class="metric-icon" style="background: rgba(99, 102, 241, 0.15); color: #818cf8;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="3" y1="9" x2="21" y2="9"></line>
              <line x1="9" y1="21" x2="9" y2="9"></line>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ recent_sales_count }}</div>
        <div class="metric-desc">Transactions in last 7 days</div>
      </div>

      <!-- FBR Success Rate -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">FBR Success Rate</span>
          <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ fbr_success_rate }}%</div>
        <div class="metric-desc">{{ fbr_submitted_count }} submitted / {{ fbr_submitted_count + fbr_failed_count }} total attempts</div>
      </div>

      <!-- FBR Submitted -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">Submitted to FBR</span>
          <div class="metric-icon" style="background: rgba(6, 182, 212, 0.15); color: #22d3ee;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ fbr_submitted_count }}</div>
        <div class="metric-desc">Sales synchronized with FBR POS</div>
      </div>

      <!-- FBR Failed -->
      <div class="metric-card">
        <div class="metric-header">
          <span class="metric-title">FBR Failed</span>
          <div class="metric-icon" style="background: rgba(244, 63, 94, 0.15); color: #fb7185;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="15" y1="9" x2="9" y2="15"></line>
              <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
          </div>
        </div>
        <div class="metric-value">{{ fbr_failed_count }}</div>
        <div class="metric-desc">Submissions flagged with error</div>
      </div>
    </div>

    <!-- Tenant Profile Details Card -->
    <div class="card-table" style="margin-bottom: 2rem;">
      <div class="table-header">
        <h2>Organization Details</h2>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
          <span class="badge badge-neutral" style="text-transform: uppercase;">{{ tenant.province }}</span>
          <Link :href="`/superadmin/tenants/${tenant.id}/edit`" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
            Edit Details
          </Link>
        </div>
      </div>
      <div style="padding: 1.5rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem;">
        <div>
          <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Tenant Name</span>
          <p style="font-size: 1.1rem; font-weight: 600; margin-top: 0.25rem;">{{ tenant.name }}</p>
        </div>
        <div>
          <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">NTN</span>
          <p style="font-size: 1.1rem; font-family: monospace; font-weight: 600; margin-top: 0.25rem;">{{ tenant.ntn }}</p>
        </div>
        <div>
          <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">STRN</span>
          <p style="font-size: 1.1rem; font-family: monospace; font-weight: 600; margin-top: 0.25rem;">{{ tenant.strn || 'Not Provided' }}</p>
        </div>
        <div>
          <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">FBR POS ID</span>
          <p style="font-size: 1.1rem; font-family: monospace; font-weight: 600; margin-top: 0.25rem; color: #38bdf8;">{{ tenant.fbr_pos_id }}</p>
        </div>
        <div>
          <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Province</span>
          <p style="font-size: 1rem; font-weight: 600; margin-top: 0.25rem; text-transform: capitalize;">{{ tenant.province }}</p>
        </div>
        <div>
          <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Registered On</span>
          <p style="font-size: 1rem; color: var(--text-secondary); margin-top: 0.25rem;">{{ tenant.created_at }}</p>
        </div>
      </div>

      <!-- Notes block if present -->
      <div
        v-if="tenant.notes"
        style="padding: 1rem 1.5rem; background: rgba(15, 23, 42, 0.4); border-top: 1px solid var(--border-color);"
      >
        <span style="font-size: 0.75rem; color: #ec4899; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Admin Notes:</span>
        <p style="font-size: 0.88rem; color: var(--text-secondary); margin-top: 0.25rem; line-height: 1.5; white-space: pre-wrap;">{{ tenant.notes }}</p>
      </div>
    </div>

    <!-- Staff Management & Locations in 2 Columns -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
      <!-- Staff Users Management -->
      <div class="card-table">
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
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="tenant.users.length === 0">
              <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
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

      <!-- Branch Locations Management -->
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

    <!-- Modal: Add Staff User -->
    <div v-if="showAddUserModal" class="modal-backdrop">
      <div class="modal-card">
        <div class="modal-header">
          <h3>Add Staff User to {{ tenant.name }}</h3>
          <button @click="showAddUserModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitAddUser">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label" for="new_user_name">Full Name *</label>
              <input
                id="new_user_name"
                v-model="addUserForm.name"
                type="text"
                class="form-input"
                :class="{ 'is-invalid': addUserForm.errors.name }"
                placeholder="e.g. Muhammad Ali"
                required
              />
              <span v-if="addUserForm.errors.name" class="invalid-feedback">{{ addUserForm.errors.name }}</span>
            </div>

            <div class="form-group">
              <label class="form-label" for="new_user_email">Email Address *</label>
              <input
                id="new_user_email"
                v-model="addUserForm.email"
                type="email"
                class="form-input"
                :class="{ 'is-invalid': addUserForm.errors.email }"
                placeholder="e.g. ali@retailer.pk"
                required
              />
              <span v-if="addUserForm.errors.email" class="invalid-feedback">{{ addUserForm.errors.email }}</span>
            </div>

            <div class="form-group">
              <label class="form-label" for="new_user_role">Role *</label>
              <select
                id="new_user_role"
                v-model="addUserForm.role"
                class="form-input"
                :class="{ 'is-invalid': addUserForm.errors.role }"
                required
              >
                <option value="cashier">Cashier (POS Checkout only)</option>
                <option value="manager">Store Manager (Catalog & Inventory)</option>
                <option value="owner">Owner (Full Business Admin & FBR)</option>
              </select>
              <span v-if="addUserForm.errors.role" class="invalid-feedback">{{ addUserForm.errors.role }}</span>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" for="new_user_pwd">Initial Password (min 8 chars) *</label>
              <input
                id="new_user_pwd"
                v-model="addUserForm.password"
                type="password"
                class="form-input"
                :class="{ 'is-invalid': addUserForm.errors.password }"
                placeholder="••••••••"
                required
              />
              <span v-if="addUserForm.errors.password" class="invalid-feedback">{{ addUserForm.errors.password }}</span>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="showAddUserModal = false" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="addUserForm.processing">
              {{ addUserForm.processing ? 'Creating...' : 'Create Staff Member' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Reset Password -->
    <div v-if="showResetPasswordModal" class="modal-backdrop">
      <div class="modal-card">
        <div class="modal-header">
          <h3>Reset Password</h3>
          <button @click="showResetPasswordModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitResetPassword">
          <div class="modal-body">
            <p style="font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 1.25rem;">
              Set a new password for <strong>{{ selectedUser?.name }}</strong> ({{ selectedUser?.email }}).
            </p>
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" for="reset_pwd">New Password (min 8 chars) *</label>
              <input
                id="reset_pwd"
                v-model="resetPasswordForm.password"
                type="password"
                class="form-input"
                :class="{ 'is-invalid': resetPasswordForm.errors.password }"
                placeholder="Enter new secure password"
                required
              />
              <span v-if="resetPasswordForm.errors.password" class="invalid-feedback">{{ resetPasswordForm.errors.password }}</span>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="showResetPasswordModal = false" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="resetPasswordForm.processing">
              {{ resetPasswordForm.processing ? 'Updating...' : 'Set Password' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Add Branch Location -->
    <div v-if="showAddLocationModal" class="modal-backdrop">
      <div class="modal-card">
        <div class="modal-header">
          <h3>Add Branch Location</h3>
          <button @click="showAddLocationModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitAddLocation">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label" for="loc_name">Branch Name *</label>
              <input
                id="loc_name"
                v-model="addLocationForm.name"
                type="text"
                class="form-input"
                :class="{ 'is-invalid': addLocationForm.errors.name }"
                placeholder="e.g. Gulberg Branch"
                required
              />
              <span v-if="addLocationForm.errors.name" class="invalid-feedback">{{ addLocationForm.errors.name }}</span>
            </div>

            <div class="form-group">
              <label class="form-label" for="loc_province">Province / Tax Jurisdiction *</label>
              <select
                id="loc_province"
                v-model="addLocationForm.province"
                class="form-input"
                :class="{ 'is-invalid': addLocationForm.errors.province }"
                required
              >
                <option value="punjab">Punjab (PRA)</option>
                <option value="sindh">Sindh (SRB)</option>
                <option value="kp">Khyber Pakhtunkhwa (KPRA)</option>
                <option value="balochistan">Balochistan (BRA)</option>
                <option value="federal">Federal / Islamabad (FBR)</option>
              </select>
              <span v-if="addLocationForm.errors.province" class="invalid-feedback">{{ addLocationForm.errors.province }}</span>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" for="loc_address">Commercial Address *</label>
              <textarea
                id="loc_address"
                v-model="addLocationForm.address"
                class="form-input"
                :class="{ 'is-invalid': addLocationForm.errors.address }"
                rows="2"
                placeholder="e.g. Shop 12, Main Boulevard, Gulberg III, Lahore"
                required
              ></textarea>
              <span v-if="addLocationForm.errors.address" class="invalid-feedback">{{ addLocationForm.errors.address }}</span>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="showAddLocationModal = false" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="addLocationForm.processing">
              {{ addLocationForm.processing ? 'Adding...' : 'Add Branch Location' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Suspend Reason -->
    <div v-if="showSuspendModal" class="modal-backdrop">
      <div class="modal-card">
        <div class="modal-header">
          <h3>Suspend Tenant Organization</h3>
          <button @click="showSuspendModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitSuspend">
          <div class="modal-body">
            <p style="font-size: 0.88rem; color: #fb7185; margin-bottom: 1rem;">
              Suspending this tenant will immediately block all cashier checkouts, POS API synchronizations, and web logins for <strong>{{ tenant.name }}</strong>.
            </p>
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" for="suspension_reason">Reason for Suspension *</label>
              <textarea
                id="suspension_reason"
                v-model="suspendForm.suspension_reason"
                class="form-input"
                rows="3"
                placeholder="e.g. Non-payment of platform SaaS fee / FBR compliance audit failure..."
                required
              ></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="showSuspendModal = false" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" class="btn" style="background: #e11d48; color: white;" :disabled="suspendForm.processing">
              Confirm Suspension
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
  tenant: {
    type: Object,
    required: true,
  },
  recent_sales_count: {
    type: Number,
    default: 0,
  },
  fbr_success_rate: {
    type: Number,
    default: 0,
  },
  fbr_submitted_count: {
    type: Number,
    default: 0,
  },
  fbr_failed_count: {
    type: Number,
    default: 0,
  },
});

const primaryOwner = computed(() => {
  return props.tenant.users?.find((u) => u.role === 'owner') || props.tenant.users?.[0];
});

function getRoleBadgeStyle(role) {
  if (role === 'owner') {
    return 'background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3);';
  }
  if (role === 'manager') {
    return 'background: rgba(6, 182, 212, 0.15); color: #22d3ee; border: 1px solid rgba(6, 182, 212, 0.3);';
  }
  return 'background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3);';
}

// Modals State
const showAddUserModal = ref(false);
const showResetPasswordModal = ref(false);
const showAddLocationModal = ref(false);
const showSuspendModal = ref(false);
const selectedUser = ref(null);

// Forms
const addUserForm = useForm({
  name: '',
  email: '',
  role: 'cashier',
  password: '',
});

const resetPasswordForm = useForm({
  password: '',
});

const addLocationForm = useForm({
  name: '',
  address: '',
  province: props.tenant.province || 'punjab',
});

const suspendForm = useForm({
  suspension_reason: '',
});

function openSuspendModal() {
  if (props.tenant.is_suspended) {
    if (confirm(`Are you sure you want to reactivate tenant "${props.tenant.name}"?`)) {
      router.post(`/superadmin/tenants/${props.tenant.id}/suspend`, {}, {
        preserveScroll: true,
      });
    }
  } else {
    suspendForm.suspension_reason = '';
    showSuspendModal.value = true;
  }
}

function submitSuspend() {
  suspendForm.post(`/superadmin/tenants/${props.tenant.id}/suspend`, {
    preserveScroll: true,
    onSuccess: () => {
      showSuspendModal.value = false;
    },
  });
}

function submitAddUser() {
  addUserForm.post(`/superadmin/tenants/${props.tenant.id}/users`, {
    preserveScroll: true,
    onSuccess: () => {
      showAddUserModal.value = false;
      addUserForm.reset();
    },
  });
}

function openResetPasswordModal(user) {
  selectedUser.value = user;
  resetPasswordForm.reset();
  showResetPasswordModal.value = true;
}

function submitResetPassword() {
  if (!selectedUser.value) return;
  resetPasswordForm.post(`/superadmin/tenants/${props.tenant.id}/users/${selectedUser.value.id}/reset-password`, {
    preserveScroll: true,
    onSuccess: () => {
      showResetPasswordModal.value = false;
      resetPasswordForm.reset();
      selectedUser.value = null;
    },
  });
}

function deleteUser(user) {
  if (confirm(`Are you sure you want to remove staff user "${user.name}" (${user.email})?`)) {
    router.delete(`/superadmin/tenants/${props.tenant.id}/users/${user.id}`, {
      preserveScroll: true,
    });
  }
}

function submitAddLocation() {
  addLocationForm.post(`/superadmin/tenants/${props.tenant.id}/locations`, {
    preserveScroll: true,
    onSuccess: () => {
      showAddLocationModal.value = false;
      addLocationForm.reset();
    },
  });
}

function impersonate(userId) {
  if (confirm(`Impersonate user for tenant "${props.tenant.name}"? You will be signed into this session.`)) {
    router.post(`/superadmin/impersonate/${userId}`);
  }
}
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
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
  max-width: 500px;
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
