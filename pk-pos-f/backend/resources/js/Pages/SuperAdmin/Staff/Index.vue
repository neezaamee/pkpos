<template>
  <SuperAdminLayout title="Internal Staff & RBAC">
    <!-- Header with Action -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 0.25rem;">Platform Staff & Role-Based Access Control</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">Manage internal team members, assign operational roles, and enforce security policies.</p>
      </div>
      <div>
        <button
          @click="openCreateModal"
          class="btn btn-primary"
          style="background: linear-gradient(135deg, #ec4899, #8b5cf6); border: none; display: flex; align-items: center; gap: 0.5rem;"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Add Staff Member
        </button>
      </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="metric-card">
        <div class="metric-label">Total Platform Staff</div>
        <div class="metric-value" style="color: #fff;">{{ metrics.total_staff }}</div>
        <div class="metric-caption">Active accounts across all roles</div>
      </div>

      <div class="metric-card">
        <div class="metric-label">Super Admins</div>
        <div class="metric-value" style="color: #f472b6;">{{ metrics.super_admins }}</div>
        <div class="metric-caption">Full infrastructure & billing control</div>
      </div>

      <div class="metric-card">
        <div class="metric-label">Support Agents</div>
        <div class="metric-value" style="color: #60a5fa;">{{ metrics.support_agents }}</div>
        <div class="metric-caption">Tickets & impersonation triage</div>
      </div>

      <div class="metric-card">
        <div class="metric-label">Compliance Auditors</div>
        <div class="metric-value" style="color: #fbbf24;">{{ metrics.compliance_auditors }}</div>
        <div class="metric-caption">Read-only FBR & fiscal audit access</div>
      </div>
    </div>

    <!-- Role Permissions Quick Reference Card -->
    <div class="card" style="margin-bottom: 1.5rem; background: rgba(30, 41, 59, 0.4); border-color: rgba(255, 255, 255, 0.08);">
      <div style="font-size: 0.85rem; font-weight: 600; color: #e2e8f0; margin-bottom: 0.75rem;">
        Internal Staff Roles & Access Matrix
      </div>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; font-size: 0.8rem;">
        <div style="padding: 0.75rem; border-radius: 8px; background: rgba(236, 72, 153, 0.08); border: 1px solid rgba(236, 72, 153, 0.2);">
          <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: #f472b6; margin-bottom: 0.35rem;">
            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #f472b6;"></span>
            Super Admin
          </div>
          <p style="color: var(--text-muted); margin: 0; line-height: 1.4;">Unrestricted platform access: tenant suspension, billing plans, system queues & telemetry, staff management, and release publishing.</p>
        </div>

        <div style="padding: 0.75rem; border-radius: 8px; background: rgba(96, 165, 250, 0.08); border: 1px solid rgba(96, 165, 250, 0.2);">
          <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: #60a5fa; margin-bottom: 0.35rem;">
            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #60a5fa;"></span>
            Support Agent
          </div>
          <p style="color: var(--text-muted); margin: 0; line-height: 1.4;">Customer support hub: respond to merchant tickets, add internal notes, reset merchant staff passwords, inspect terminals, and audited impersonation.</p>
        </div>

        <div style="padding: 0.75rem; border-radius: 8px; background: rgba(251, 191, 36, 0.08); border: 1px solid rgba(251, 191, 36, 0.2);">
          <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: #fbbf24; margin-bottom: 0.35rem;">
            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #fbbf24;"></span>
            Compliance Auditor
          </div>
          <p style="color: var(--text-muted); margin: 0; line-height: 1.4;">Fiscal regulatory scrutiny: read-only access to FBR Dead-Letter Queue, 24h provisional receipts, master HS codes, tax rate templates, and audit logs.</p>
        </div>
      </div>
    </div>

    <!-- Filters Bar -->
    <div class="card" style="margin-bottom: 1.5rem;">
      <form @submit.prevent="applyFilters" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 220px;">
          <label class="form-label" style="font-size: 0.75rem;">Search Name / Email</label>
          <input
            v-model="filterForm.search"
            type="text"
            class="form-control"
            placeholder="e.g. Tariq, tariq@pkpos.pk..."
          />
        </div>

        <div style="width: 180px;">
          <label class="form-label" style="font-size: 0.75rem;">Filter by Role</label>
          <select v-model="filterForm.role" class="form-control" @change="applyFilters">
            <option value="">All Staff Roles</option>
            <option value="super_admin">Super Admin</option>
            <option value="support_agent">Support Agent</option>
            <option value="compliance_auditor">Compliance Auditor</option>
          </select>
        </div>

        <div style="width: 150px;">
          <label class="form-label" style="font-size: 0.75rem;">Status</label>
          <select v-model="filterForm.status" class="form-control" @change="applyFilters">
            <option value="">All Statuses</option>
            <option value="active">Active Only</option>
            <option value="inactive">Deactivated</option>
          </select>
        </div>

        <div style="display: flex; gap: 0.5rem;">
          <button type="submit" class="btn btn-secondary">
            Filter
          </button>
          <button
            v-if="hasActiveFilters"
            type="button"
            @click="clearFilters"
            class="btn btn-secondary"
            title="Reset Filters"
          >
            Clear
          </button>
        </div>
      </form>
    </div>

    <!-- Staff Members Table -->
    <div class="card">
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Staff Member</th>
              <th>Assigned Role</th>
              <th>Status</th>
              <th>Created At</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="staff_members.data.length === 0">
              <td colspan="5" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                No staff members match the selected criteria.
              </td>
            </tr>
            <tr v-for="member in staff_members.data" :key="member.id">
              <td>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                  <div
                    style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;"
                    :style="getAvatarStyle(member.staff_role)"
                  >
                    {{ member.name.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <div style="font-weight: 600; color: #fff; display: flex; align-items: center; gap: 0.5rem;">
                      {{ member.name }}
                      <span v-if="member.is_current_user" class="badge" style="background: rgba(236, 72, 153, 0.2); color: #f472b6; font-size: 0.65rem;">
                        You
                      </span>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                      {{ member.email }}
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <span
                  class="badge"
                  :style="getRoleBadgeStyle(member.staff_role)"
                >
                  <span class="badge-dot" :style="{ backgroundColor: getRoleColor(member.staff_role) }"></span>
                  {{ formatRoleLabel(member.staff_role) }}
                </span>
              </td>
              <td>
                <span
                  v-if="member.is_active"
                  class="badge badge-success"
                >
                  <span class="badge-dot" style="background: #10b981;"></span>
                  Active
                </span>
                <span
                  v-else
                  class="badge badge-danger"
                >
                  <span class="badge-dot" style="background: #ef4444;"></span>
                  Deactivated
                </span>
              </td>
              <td style="font-size: 0.8rem; color: var(--text-muted);">
                {{ formatDate(member.created_at) }}
              </td>
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 0.4rem;">
                  <!-- Edit Staff Member -->
                  <button
                    @click="openEditModal(member)"
                    class="btn btn-secondary"
                    style="padding: 0.3rem 0.6rem; font-size: 0.75rem;"
                    title="Edit Role & Details"
                  >
                    Edit
                  </button>

                  <!-- Reset Password -->
                  <button
                    @click="openResetPasswordModal(member)"
                    class="btn btn-secondary"
                    style="padding: 0.3rem 0.6rem; font-size: 0.75rem;"
                    title="Reset Password"
                  >
                    Reset Pwd
                  </button>

                  <!-- Toggle Active Status -->
                  <button
                    v-if="!member.is_current_user"
                    @click="toggleStatus(member)"
                    class="btn"
                    :class="member.is_active ? 'btn-secondary' : 'btn-primary'"
                    style="padding: 0.3rem 0.6rem; font-size: 0.75rem;"
                    :title="member.is_active ? 'Deactivate staff account' : 'Reactivate staff account'"
                  >
                    {{ member.is_active ? 'Deactivate' : 'Activate' }}
                  </button>

                  <!-- Delete Staff Member -->
                  <button
                    v-if="!member.is_current_user"
                    @click="deleteStaff(member)"
                    class="btn btn-danger"
                    style="padding: 0.3rem 0.6rem; font-size: 0.75rem;"
                    title="Remove staff account"
                  >
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="staff_members.links && staff_members.links.length > 3" style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem;">
        <Link
          v-for="(link, i) in staff_members.links"
          :key="i"
          :href="link.url || '#'"
          class="btn btn-secondary"
          :class="{ active: link.active, disabled: !link.url }"
          style="padding: 0.35rem 0.75rem; font-size: 0.8rem;"
          v-html="link.label"
        />
      </div>
    </div>

    <!-- Modal: Add New Staff Member -->
    <div v-if="showCreateModal" class="modal-backdrop">
      <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
          <h3 class="modal-title">Add Platform Staff Member</h3>
          <button @click="showCreateModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitCreate">
          <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <label class="form-label">Full Name *</label>
              <input
                v-model="createForm.name"
                type="text"
                class="form-control"
                placeholder="e.g. Asad Ullah"
                required
              />
              <span v-if="createForm.errors.name" class="form-error">{{ createForm.errors.name }}</span>
            </div>

            <div>
              <label class="form-label">Work Email Address *</label>
              <input
                v-model="createForm.email"
                type="email"
                class="form-control"
                placeholder="e.g. asad@pkpos.pk"
                required
              />
              <span v-if="createForm.errors.email" class="form-error">{{ createForm.errors.email }}</span>
            </div>

            <div>
              <label class="form-label">Temporary Password (Min 8 characters) *</label>
              <input
                v-model="createForm.password"
                type="password"
                class="form-control"
                placeholder="••••••••••••"
                required
                minlength="8"
              />
              <span v-if="createForm.errors.password" class="form-error">{{ createForm.errors.password }}</span>
            </div>

            <div>
              <label class="form-label">Assigned Staff Role *</label>
              <select v-model="createForm.staff_role" class="form-control" required>
                <option v-for="r in dynamicRoleList" :key="r.slug" :value="r.slug">
                  {{ r.name }}
                </option>
              </select>
              <span v-if="createForm.errors.staff_role" class="form-error">{{ createForm.errors.staff_role }}</span>
            </div>
          </div>

          <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" @click="showCreateModal = false" class="btn btn-secondary">
              Cancel
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="createForm.processing"
              style="background: linear-gradient(135deg, #ec4899, #8b5cf6); border: none;"
            >
              {{ createForm.processing ? 'Creating...' : 'Create Staff Account' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Edit Staff Member -->
    <div v-if="showEditModal" class="modal-backdrop">
      <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
          <h3 class="modal-title">Edit Staff Member</h3>
          <button @click="showEditModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitEdit">
          <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <label class="form-label">Full Name *</label>
              <input
                v-model="editForm.name"
                type="text"
                class="form-control"
                required
              />
              <span v-if="editForm.errors.name" class="form-error">{{ editForm.errors.name }}</span>
            </div>

            <div>
              <label class="form-label">Email Address *</label>
              <input
                v-model="editForm.email"
                type="email"
                class="form-control"
                required
              />
              <span v-if="editForm.errors.email" class="form-error">{{ editForm.errors.email }}</span>
            </div>

            <div>
              <label class="form-label">Staff Role *</label>
              <select
                v-model="editForm.staff_role"
                class="form-control"
                :disabled="editingMember?.is_current_user"
                required
              >
                <option v-for="r in dynamicRoleList" :key="r.slug" :value="r.slug">
                  {{ r.name }}
                </option>
              </select>
              <span v-if="editingMember?.is_current_user" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                You cannot modify your own role.
              </span>
              <span v-if="editForm.errors.staff_role" class="form-error">{{ editForm.errors.staff_role }}</span>
            </div>

            <div v-if="!editingMember?.is_current_user" style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.5rem;">
              <input
                id="edit-is-active"
                v-model="editForm.is_active"
                type="checkbox"
                style="width: 18px; height: 18px;"
              />
              <label for="edit-is-active" style="font-size: 0.85rem; color: #fff; cursor: pointer;">
                Account Active (uncheck to deactivate access immediately)
              </label>
            </div>
          </div>

          <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" @click="showEditModal = false" class="btn btn-secondary">
              Cancel
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="editForm.processing"
            >
              {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Reset Staff Password -->
    <div v-if="showResetModal" class="modal-backdrop">
      <div class="modal" style="max-width: 480px;">
        <div class="modal-header">
          <h3 class="modal-title">Reset Staff Password</h3>
          <button @click="showResetModal = false" class="modal-close">&times;</button>
        </div>
        <form @submit.prevent="submitResetPassword">
          <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
              Resetting password for <strong style="color: #fff;">{{ resettingMember?.name }}</strong> ({{ resettingMember?.email }}).
            </p>

            <div>
              <label class="form-label">New Password *</label>
              <input
                v-model="resetForm.password"
                type="password"
                class="form-control"
                placeholder="Min 8 characters"
                required
                minlength="8"
              />
              <span v-if="resetForm.errors.password" class="form-error">{{ resetForm.errors.password }}</span>
            </div>

            <div>
              <label class="form-label">Confirm New Password *</label>
              <input
                v-model="resetForm.password_confirmation"
                type="password"
                class="form-control"
                placeholder="Re-type password"
                required
                minlength="8"
              />
            </div>
          </div>

          <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" @click="showResetModal = false" class="btn btn-secondary">
              Cancel
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="resetForm.processing"
            >
              {{ resetForm.processing ? 'Resetting...' : 'Update Password' }}
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
  staff_members: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  metrics: {
    type: Object,
    default: () => ({}),
  },
  available_roles: {
    type: Array,
    default: () => [],
  },
});

const dynamicRoleList = computed(() => {
  if (props.available_roles && props.available_roles.length > 0) {
    return props.available_roles;
  }
  return [
    { slug: 'super_admin', name: 'Super Admin (Full Platform Control)', color: '#ec4899' },
    { slug: 'support_agent', name: 'Support Agent (Helpdesk & Impersonation)', color: '#60a5fa' },
    { slug: 'compliance_auditor', name: 'Compliance Auditor (Read-Only FBR & Audits)', color: '#fbbf24' },
  ];
});

// Filters
const filterForm = ref({
  search: props.filters.search || '',
  role: props.filters.role || '',
  status: props.filters.status || '',
});

const hasActiveFilters = computed(() => {
  return filterForm.value.search || filterForm.value.role || filterForm.value.status;
});

const applyFilters = () => {
  router.get('/superadmin/staff', {
    search: filterForm.value.search || undefined,
    role: filterForm.value.role || undefined,
    status: filterForm.value.status || undefined,
  }, {
    preserveState: true,
    replace: true,
  });
};

const clearFilters = () => {
  filterForm.value = { search: '', role: '', status: '' };
  applyFilters();
};

// Create Modal
const showCreateModal = ref(false);
const createForm = useForm({
  name: '',
  email: '',
  password: '',
  staff_role: 'support_agent',
});

const openCreateModal = () => {
  createForm.reset();
  createForm.clearErrors();
  showCreateModal.value = true;
};

const submitCreate = () => {
  createForm.post('/superadmin/staff', {
    onSuccess: () => {
      showCreateModal.value = false;
      createForm.reset();
    },
  });
};

// Edit Modal
const showEditModal = ref(false);
const editingMember = ref(null);
const editForm = useForm({
  name: '',
  email: '',
  staff_role: 'support_agent',
  is_active: true,
});

const openEditModal = (member) => {
  editingMember.value = member;
  editForm.reset();
  editForm.clearErrors();
  editForm.name = member.name;
  editForm.email = member.email;
  editForm.staff_role = member.staff_role;
  editForm.is_active = member.is_active;
  showEditModal.value = true;
};

const submitEdit = () => {
  if (!editingMember.value) return;
  editForm.put(`/superadmin/staff/${editingMember.value.id}`, {
    onSuccess: () => {
      showEditModal.value = false;
      editingMember.value = null;
    },
  });
};

// Reset Password Modal
const showResetModal = ref(false);
const resettingMember = ref(null);
const resetForm = useForm({
  password: '',
  password_confirmation: '',
});

const openResetPasswordModal = (member) => {
  resettingMember.value = member;
  resetForm.reset();
  resetForm.clearErrors();
  showResetModal.value = true;
};

const submitResetPassword = () => {
  if (!resettingMember.value) return;
  resetForm.post(`/superadmin/staff/${resettingMember.value.id}/reset-password`, {
    onSuccess: () => {
      showResetModal.value = false;
      resettingMember.value = null;
    },
  });
};

// Toggle Active Status
const toggleStatus = (member) => {
  const action = member.is_active ? 'deactivate' : 'activate';
  if (confirm(`Are you sure you want to ${action} staff account for ${member.name}?`)) {
    router.post(`/superadmin/staff/${member.id}/toggle-status`, {}, {
      preserveScroll: true,
    });
  }
};

// Delete Staff
const deleteStaff = (member) => {
  if (confirm(`Are you sure you want to completely delete the staff account for ${member.name}? This cannot be undone.`)) {
    router.delete(`/superadmin/staff/${member.id}`, {
      preserveScroll: true,
    });
  }
};

// Visual Helpers
const formatRoleLabel = (role) => {
  const match = props.available_roles?.find(r => r.slug === role);
  if (match) return match.name;

  switch (role) {
    case 'super_admin':
      return 'Super Admin';
    case 'support_agent':
      return 'Support Agent';
    case 'compliance_auditor':
      return 'Compliance Auditor';
    default:
      return role;
  }
};

const getRoleColor = (role) => {
  const match = props.available_roles?.find(r => r.slug === role);
  if (match && match.color) return match.color;

  switch (role) {
    case 'super_admin':
      return '#ec4899';
    case 'support_agent':
      return '#60a5fa';
    case 'compliance_auditor':
      return '#fbbf24';
    default:
      return '#94a3b8';
  }
};

const getRoleBadgeStyle = (role) => {
  const color = getRoleColor(role);
  return {
    background: `${color}1f`,
    color: color,
    borderColor: `${color}4d`,
  };
};

const getAvatarStyle = (role) => {
  const color = getRoleColor(role);
  return {
    background: `${color}2e`,
    color: color,
    border: `1px solid ${color}66`,
  };
};

const formatDate = (isoStr) => {
  if (!isoStr) return 'N/A';
  return new Date(isoStr).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
};
</script>

