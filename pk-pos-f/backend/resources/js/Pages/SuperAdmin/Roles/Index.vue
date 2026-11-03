<template>
  <SuperAdminLayout title="Roles & Granular Permissions">
    <!-- Header with Action -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 0.25rem;">
          Dynamic Roles & Granular Permissions Engine
        </h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">
          Configure customizable platform roles, define module access capabilities, and enforce strict least-privilege policies.
        </p>
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
          Create Custom Role
        </button>
      </div>
    </div>

    <!-- Summary Metrics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="metric-card">
        <div class="metric-label">Total Defined Roles</div>
        <div class="metric-value" style="color: #fff;">{{ metrics.total_roles }}</div>
        <div class="metric-caption">Active operational roles</div>
      </div>

      <div class="metric-card">
        <div class="metric-label">Core System Roles</div>
        <div class="metric-value" style="color: #f472b6;">{{ metrics.system_roles }}</div>
        <div class="metric-caption">Protected built-in baselines</div>
      </div>

      <div class="metric-card">
        <div class="metric-label">Custom Staff Roles</div>
        <div class="metric-value" style="color: #60a5fa;">{{ metrics.custom_roles }}</div>
        <div class="metric-caption">Organization-tailored roles</div>
      </div>

      <div class="metric-card">
        <div class="metric-label">Platform Capabilities</div>
        <div class="metric-value" style="color: #34d399;">{{ metrics.total_permissions }}</div>
        <div class="metric-caption">Granular module permissions</div>
      </div>
    </div>

    <!-- Defined Roles Grid -->
    <div style="margin-bottom: 2rem;">
      <h3 style="font-size: 1.15rem; font-weight: 700; color: #fff; margin-bottom: 1rem;">
        Operational Roles Directory
      </h3>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem;">
        <div
          v-for="role in roles"
          :key="role.id"
          class="card"
          style="display: flex; flex-direction: column; justify-content: space-between; border-left: 4px solid;"
          :style="{ borderLeftColor: role.color || '#3b82f6' }"
        >
          <div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
              <div>
                <h4 style="font-size: 1.1rem; font-weight: 700; color: #fff; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                  {{ role.name }}
                  <span v-if="role.is_system" class="badge" style="background: rgba(255, 255, 255, 0.08); color: var(--text-muted); font-size: 0.65rem;">
                    System Core
                  </span>
                </h4>
                <code style="font-size: 0.75rem; color: var(--text-muted);">{{ role.slug }}</code>
              </div>

              <span
                class="badge"
                :style="{ background: `${role.color}22`, color: role.color, borderColor: `${role.color}44` }"
              >
                {{ role.users_count }} Staff Assigned
              </span>
            </div>

            <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0.5rem 0 1rem 0; line-height: 1.4; min-height: 2.8em;">
              {{ role.description || 'No description provided for this operational role.' }}
            </p>

            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; font-size: 0.775rem;">
              <span style="color: var(--text-muted);">Capabilities:</span>
              <strong style="color: #fff;">
                {{ role.slug === 'super_admin' ? 'All Permissions (Unrestricted)' : `${role.permissions_count} / ${metrics.total_permissions} Active` }}
              </strong>
            </div>
          </div>

          <!-- Role Card Actions -->
          <div style="display: flex; justify-content: flex-end; gap: 0.5rem; border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 0.75rem;">
            <button
              @click="openEditModal(role)"
              class="btn btn-secondary"
              style="padding: 0.35rem 0.75rem; font-size: 0.775rem;"
            >
              Configure Permissions
            </button>
            <button
              v-if="!role.is_system"
              @click="deleteRole(role)"
              class="btn btn-danger"
              style="padding: 0.35rem 0.75rem; font-size: 0.775rem;"
              :disabled="role.users_count > 0"
              :title="role.users_count > 0 ? 'Cannot delete role while staff are assigned' : 'Delete role'"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Granular Platform Permissions Matrix by Module -->
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <div>
          <h3 style="font-size: 1.15rem; font-weight: 700; color: #fff; margin-bottom: 0.2rem;">
            Platform Capability Matrix
          </h3>
          <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
            Granular actions grouped by functional subsystem. Click on any role above to configure assignments.
          </p>
        </div>
      </div>

      <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div
          v-for="(perms, moduleName) in permissions_by_module"
          :key="moduleName"
          style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 8px; padding: 1.25rem;"
        >
          <!-- Module Header -->
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.06); padding-bottom: 0.5rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <span style="font-weight: 700; color: #fff; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.05em;">
                {{ formatModuleName(moduleName) }}
              </span>
              <span class="badge badge-neutral" style="font-size: 0.65rem;">
                {{ perms.length }} Actions
              </span>
            </div>
          </div>

          <!-- Permissions Grid -->
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 0.75rem;">
            <div
              v-for="perm in perms"
              :key="perm.id"
              style="padding: 0.75rem; border-radius: 6px; background: rgba(15, 23, 42, 0.4); border: 1px solid rgba(255, 255, 255, 0.05);"
            >
              <div style="font-weight: 600; color: #e2e8f0; font-size: 0.85rem; margin-bottom: 0.2rem;">
                {{ perm.name }}
              </div>
              <div style="font-family: monospace; font-size: 0.7rem; color: #38bdf8; margin-bottom: 0.35rem;">
                {{ perm.slug }}
              </div>
              <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0; line-height: 1.35;">
                {{ perm.description }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Create Custom Role -->
    <div v-if="showCreateModal" class="modal-backdrop">
      <div class="modal" style="max-width: 700px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header">
          <h3 class="modal-title">Create Custom Operational Role</h3>
          <button @click="showCreateModal = false" class="modal-close">&times;</button>
        </div>

        <form @submit.prevent="submitCreate" style="display: flex; flex-direction: column; overflow: hidden; flex: 1;">
          <div class="modal-body" style="overflow-y: auto; padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
              <div>
                <label class="form-label">Role Name *</label>
                <input
                  v-model="createForm.name"
                  type="text"
                  class="form-control"
                  placeholder="e.g. Regional Support Officer"
                  required
                />
                <span v-if="createForm.errors.name" class="form-error">{{ createForm.errors.name }}</span>
              </div>

              <div>
                <label class="form-label">Badge Color</label>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                  <input
                    v-model="createForm.color"
                    type="color"
                    style="width: 38px; height: 38px; border: none; border-radius: 6px; cursor: pointer; background: transparent;"
                  />
                  <input
                    v-model="createForm.color"
                    type="text"
                    class="form-control"
                    style="font-family: monospace;"
                    placeholder="#3b82f6"
                  />
                </div>
              </div>
            </div>

            <div>
              <label class="form-label">Description</label>
              <textarea
                v-model="createForm.description"
                class="form-control"
                rows="2"
                placeholder="Operational purpose and scope for this role..."
              ></textarea>
            </div>

            <!-- Permission Checklists by Module -->
            <div>
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <label class="form-label" style="margin: 0; font-weight: 700;">Assign Granular Capabilities</label>
                <div style="display: flex; gap: 0.5rem;">
                  <button type="button" @click="selectAllCreatePermissions" class="btn btn-secondary" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">
                    Select All
                  </button>
                  <button type="button" @click="createForm.permissions = []" class="btn btn-secondary" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">
                    Deselect All
                  </button>
                </div>
              </div>

              <div style="display: flex; flex-direction: column; gap: 1rem; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 8px; padding: 1rem; max-height: 340px; overflow-y: auto;">
                <div v-for="(perms, mod) in permissions_by_module" :key="mod">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.75rem; font-weight: 700; color: #38bdf8; text-transform: uppercase;">
                      {{ formatModuleName(mod) }}
                    </span>
                    <button
                      type="button"
                      @click="toggleModulePermissions(createForm, perms)"
                      class="btn btn-secondary"
                      style="font-size: 0.65rem; padding: 0.15rem 0.4rem;"
                    >
                      Toggle Group
                    </button>
                  </div>

                  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.5rem;">
                    <label
                      v-for="p in perms"
                      :key="p.id"
                      style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.8rem; color: #e2e8f0; cursor: pointer; padding: 0.35rem; border-radius: 4px; background: rgba(255, 255, 255, 0.02);"
                    >
                      <input
                        type="checkbox"
                        :value="p.id"
                        v-model="createForm.permissions"
                        style="margin-top: 2px;"
                      />
                      <div>
                        <div>{{ p.name }}</div>
                        <div style="font-size: 0.65rem; color: var(--text-muted); font-family: monospace;">{{ p.slug }}</div>
                      </div>
                    </label>
                  </div>
                </div>
              </div>
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
              {{ createForm.processing ? 'Creating...' : 'Save Role' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Edit Role & Permissions -->
    <div v-if="showEditModal" class="modal-backdrop">
      <div class="modal" style="max-width: 700px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header">
          <h3 class="modal-title">Configure Role: {{ editingRole?.name }}</h3>
          <button @click="showEditModal = false" class="modal-close">&times;</button>
        </div>

        <form @submit.prevent="submitEdit" style="display: flex; flex-direction: column; overflow: hidden; flex: 1;">
          <div class="modal-body" style="overflow-y: auto; padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
              <div>
                <label class="form-label">Role Name *</label>
                <input
                  v-model="editForm.name"
                  type="text"
                  class="form-control"
                  :disabled="editingRole?.is_system"
                  required
                />
                <span v-if="editForm.errors.name" class="form-error">{{ editForm.errors.name }}</span>
              </div>

              <div>
                <label class="form-label">Badge Color</label>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                  <input
                    v-model="editForm.color"
                    type="color"
                    style="width: 38px; height: 38px; border: none; border-radius: 6px; cursor: pointer; background: transparent;"
                  />
                  <input
                    v-model="editForm.color"
                    type="text"
                    class="form-control"
                    style="font-family: monospace;"
                  />
                </div>
              </div>
            </div>

            <div>
              <label class="form-label">Description</label>
              <textarea
                v-model="editForm.description"
                class="form-control"
                rows="2"
              ></textarea>
            </div>

            <!-- Permission Checklists by Module -->
            <div>
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <label class="form-label" style="margin: 0; font-weight: 700;">Granular Capabilities</label>
                <div v-if="editingRole?.slug !== 'super_admin'" style="display: flex; gap: 0.5rem;">
                  <button type="button" @click="selectAllEditPermissions" class="btn btn-secondary" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">
                    Select All
                  </button>
                  <button type="button" @click="editForm.permissions = []" class="btn btn-secondary" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">
                    Deselect All
                  </button>
                </div>
              </div>

              <div
                v-if="editingRole?.slug === 'super_admin'"
                style="padding: 1rem; border-radius: 8px; background: rgba(236, 72, 153, 0.1); border: 1px solid rgba(236, 72, 153, 0.3); font-size: 0.85rem; color: #f472b6;"
              >
                The <strong>Super Admin</strong> role inherently holds all platform permissions by system design and cannot be restricted.
              </div>

              <div
                v-else
                style="display: flex; flex-direction: column; gap: 1rem; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 8px; padding: 1rem; max-height: 340px; overflow-y: auto;"
              >
                <div v-for="(perms, mod) in permissions_by_module" :key="mod">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.75rem; font-weight: 700; color: #38bdf8; text-transform: uppercase;">
                      {{ formatModuleName(mod) }}
                    </span>
                    <button
                      type="button"
                      @click="toggleModulePermissions(editForm, perms)"
                      class="btn btn-secondary"
                      style="font-size: 0.65rem; padding: 0.15rem 0.4rem;"
                    >
                      Toggle Group
                    </button>
                  </div>

                  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.5rem;">
                    <label
                      v-for="p in perms"
                      :key="p.id"
                      style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.8rem; color: #e2e8f0; cursor: pointer; padding: 0.35rem; border-radius: 4px; background: rgba(255, 255, 255, 0.02);"
                    >
                      <input
                        type="checkbox"
                        :value="p.id"
                        v-model="editForm.permissions"
                        style="margin-top: 2px;"
                      />
                      <div>
                        <div>{{ p.name }}</div>
                        <div style="font-size: 0.65rem; color: var(--text-muted); font-family: monospace;">{{ p.slug }}</div>
                      </div>
                    </label>
                  </div>
                </div>
              </div>
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
  </SuperAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  roles: {
    type: Array,
    required: true,
  },
  permissions_by_module: {
    type: Object,
    required: true,
  },
  metrics: {
    type: Object,
    default: () => ({}),
  },
});

// Create Modal State
const showCreateModal = ref(false);
const createForm = useForm({
  name: '',
  color: '#3b82f6',
  description: '',
  permissions: [],
});

const openCreateModal = () => {
  createForm.reset();
  createForm.clearErrors();
  createForm.color = '#3b82f6';
  showCreateModal.value = true;
};

const selectAllCreatePermissions = () => {
  const allIds = [];
  Object.values(props.permissions_by_module).forEach(group => {
    group.forEach(p => allIds.push(p.id));
  });
  createForm.permissions = allIds;
};

const toggleModulePermissions = (form, perms) => {
  const permIds = perms.map(p => p.id);
  const allSelected = permIds.every(id => form.permissions.includes(id));
  if (allSelected) {
    form.permissions = form.permissions.filter(id => !permIds.includes(id));
  } else {
    const combined = new Set([...form.permissions, ...permIds]);
    form.permissions = Array.from(combined);
  }
};

const submitCreate = () => {
  createForm.post('/superadmin/roles', {
    onSuccess: () => {
      showCreateModal.value = false;
      createForm.reset();
    },
  });
};

// Edit Modal State
const showEditModal = ref(false);
const editingRole = ref(null);
const editForm = useForm({
  name: '',
  color: '#3b82f6',
  description: '',
  permissions: [],
});

const openEditModal = (role) => {
  editingRole.value = role;
  editForm.reset();
  editForm.clearErrors();
  editForm.name = role.name;
  editForm.color = role.color || '#3b82f6';
  editForm.description = role.description || '';
  editForm.permissions = [...(role.permissions || [])];
  showEditModal.value = true;
};

const selectAllEditPermissions = () => {
  const allIds = [];
  Object.values(props.permissions_by_module).forEach(group => {
    group.forEach(p => allIds.push(p.id));
  });
  editForm.permissions = allIds;
};

const submitEdit = () => {
  if (!editingRole.value) return;
  editForm.put(`/superadmin/roles/${editingRole.value.id}`, {
    onSuccess: () => {
      showEditModal.value = false;
      editingRole.value = null;
    },
  });
};

// Delete Role
const deleteRole = (role) => {
  if (confirm(`Are you sure you want to permanently delete custom role '${role.name}'?`)) {
    router.delete(`/superadmin/roles/${role.id}`, {
      preserveScroll: true,
    });
  }
};

// Visual Helper
const formatModuleName = (mod) => {
  const labels = {
    tenants: 'Merchant Tenants & Branches',
    fbr: 'FBR Fiscalization & IRIS DLQ',
    master_data: 'Master Customs HS Codes & Tariffs',
    terminals: 'POS Terminal Fleet Management',
    releases: 'Desktop Releases & Tauri OTA',
    plans: 'SaaS Monetization & Subscriptions',
    support: 'Customer Support & Helpdesk Hub',
    system: 'Infrastructure Diagnostics & Queues',
    staff: 'Platform Staff Management',
    roles: 'Roles & Access Control Policies',
    audit: 'Immutable Audit Trail',
  };
  return labels[mod] || mod.replace('_', ' ');
};
</script>

