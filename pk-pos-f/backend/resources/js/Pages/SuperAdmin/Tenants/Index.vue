<template>
  <SuperAdminLayout title="Tenant Management">
    <!-- Header with Search & Create Button -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap;">
      <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1; max-width: 480px;">
        <div style="position: relative; width: 100%;">
          <input
            v-model="searchQuery"
            @keyup.enter="handleSearch"
            type="text"
            placeholder="Search by tenant name or NTN..."
            class="form-input"
            style="padding-left: 2.5rem;"
          />
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"
          >
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
        </div>
        <button @click="handleSearch" class="btn btn-secondary" style="padding: 0.75rem 1rem;">
          Search
        </button>
        <button v-if="filters.search" @click="clearSearch" class="btn btn-secondary" style="padding: 0.75rem 0.85rem;" title="Clear search">
          Clear
        </button>
      </div>

      <Link href="/superadmin/tenants/create" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Create New Tenant
      </Link>
    </div>

    <!-- Tenants Table -->
    <div class="card-table">
      <div class="table-header">
        <h2>Tenants ({{ tenants.total }})</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">
          Showing {{ tenants.from || 0 }} to {{ tenants.to || 0 }} of {{ tenants.total }}
        </span>
      </div>

      <table class="data-table">
        <thead>
          <tr>
            <th>Tenant Name</th>
            <th>NTN</th>
            <th>Province</th>
            <th>Owner Email</th>
            <th>Created At</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="tenants.data.length === 0">
            <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
              No tenants found matching your query.
            </td>
          </tr>
          <tr v-for="tenant in tenants.data" :key="tenant.id">
            <td style="font-weight: 600; color: var(--text-primary);">
              <Link :href="`/superadmin/tenants/${tenant.id}`" style="color: inherit; text-decoration: none;">
                {{ tenant.name }}
              </Link>
            </td>
            <td>
              <code>{{ tenant.ntn }}</code>
            </td>
            <td>
              <span class="badge badge-neutral" style="text-transform: uppercase;">
                {{ tenant.province }}
              </span>
            </td>
            <td style="font-size: 0.825rem;">
              {{ tenant.owner_email }}
            </td>
            <td style="font-size: 0.8rem; color: var(--text-muted);">
              {{ tenant.created_at }}
            </td>
            <td>
              <span v-if="tenant.status === 'suspended'" class="badge badge-failed">
                <span class="badge-dot"></span>
                Suspended
              </span>
              <span v-else class="badge badge-submitted">
                <span class="badge-dot"></span>
                Active
              </span>
            </td>
            <td style="text-align: right;">
              <div style="display: inline-flex; gap: 0.4rem; align-items: center;">
                <!-- View Detail -->
                <Link
                  :href="`/superadmin/tenants/${tenant.id}`"
                  class="btn btn-secondary"
                  style="padding: 0.35rem 0.65rem; font-size: 0.775rem;"
                  title="View Tenant"
                >
                  View
                </Link>

                <!-- Suspend / Reactivate -->
                <button
                  @click="toggleSuspend(tenant)"
                  class="btn"
                  :class="tenant.status === 'suspended' ? 'btn-secondary' : 'btn-secondary'"
                  :style="tenant.status === 'suspended' ? 'color: #34d399;' : 'color: #fb7185;'"
                  style="padding: 0.35rem 0.65rem; font-size: 0.775rem;"
                  :title="tenant.status === 'suspended' ? 'Reactivate Tenant' : 'Suspend Tenant'"
                >
                  {{ tenant.status === 'suspended' ? 'Activate' : 'Suspend' }}
                </button>

                <!-- Impersonate Owner -->
                <button
                  v-if="tenant.owner_id && tenant.status !== 'suspended'"
                  @click="impersonate(tenant.owner_id, tenant.name)"
                  class="btn btn-secondary"
                  style="padding: 0.35rem 0.65rem; font-size: 0.775rem; color: #fbbf24;"
                  title="Impersonate Owner"
                >
                  Impersonate
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

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
  filters: {
    type: Object,
    default: () => ({ search: '' }),
  },
});

const searchQuery = ref(props.filters.search || '');

function handleSearch() {
  router.get('/superadmin/tenants', { search: searchQuery.value }, { preserveState: true, replace: true });
}

function clearSearch() {
  searchQuery.value = '';
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
  if (confirm(`Impersonate owner for tenant "${tenantName}"? You will be signed in as this tenant.`)) {
    router.post(`/superadmin/impersonate/${ownerId}`);
  }
}
</script>
