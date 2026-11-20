<template>
  <AdminLayout title="Contractor Project Sites & Khata">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
          <span>🏗️</span> Contractor Project Sites
        </h1>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
          Track multi-site billing, plot numbers, site supervisors (Munshi), and dedicated project ledgers.
        </p>
      </div>
      <div style="display: flex; gap: 0.5rem;">
        <button @click="showCreateModal = true" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          <span>Add Project Site</span>
        </button>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 1.25rem; padding: 1rem;">
      <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
        <div style="flex: 1; min-width: 250px;">
          <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">Filter by Contractor</label>
          <select v-model="selectedCustomerId" @change="onCustomerFilter" class="form-control" style="width: 100%;">
            <option value="">All Contractors / Customers</option>
            <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.phone || 'No Phone' }})</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Project Sites Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
      <div class="table-responsive">
        <table class="table" style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="border-bottom: 1px solid var(--border-color); text-align: left; background: var(--bg-surface-soft);">
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Project / Site Name</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Contractor</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Plot & Address</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Supervisor (Munshi)</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Status</th>
              <th style="padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!projects.data || projects.data.length === 0">
              <td colspan="6" style="padding: 2.5rem; text-align: center; color: var(--text-muted);">
                No contractor project sites found. Click "Add Project Site" to register one.
              </td>
            </tr>
            <tr v-for="p in projects.data" :key="p.id" style="border-bottom: 1px solid var(--border-color);">
              <td style="padding: 0.75rem 1rem;">
                <div style="font-weight: 700; color: var(--text-primary);">{{ p.name }}</div>
                <div v-if="p.city" style="font-size: 0.75rem; color: var(--text-muted);">{{ p.city }}</div>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <div style="font-weight: 600; color: var(--text-primary);">{{ p.customer?.name || '—' }}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ p.customer?.phone || '' }}</div>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <div v-if="p.plot_number" style="font-size: 0.8rem; font-weight: 600; color: #f97316;">Plot # {{ p.plot_number }}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ p.site_address || 'Address not set' }}</div>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <div style="font-size: 0.85rem; font-weight: 600;">{{ p.supervisor_name || 'Not assigned' }}</div>
                <div v-if="p.supervisor_phone" style="font-size: 0.75rem; color: var(--text-muted);">{{ p.supervisor_phone }}</div>
              </td>
              <td style="padding: 0.75rem 1rem;">
                <span
                  class="badge"
                  :style="{
                    background: p.status === 'active' ? 'rgba(16, 185, 129, 0.15)' : p.status === 'completed' ? 'rgba(59, 130, 246, 0.15)' : 'rgba(245, 158, 11, 0.15)',
                    color: p.status === 'active' ? '#10b981' : p.status === 'completed' ? '#3b82f6' : '#f59e0b',
                    padding: '0.2rem 0.5rem',
                    borderRadius: '4px',
                    fontSize: '0.75rem',
                    fontWeight: 700,
                    textTransform: 'uppercase'
                  }"
                >
                  {{ p.status }}
                </span>
              </td>
              <td style="padding: 0.75rem 1rem; text-align: right;">
                <Link :href="`/hardware/projects/${p.id}`" class="btn btn-sm btn-outline" style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                  View Site Ledger &rarr;
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="projects.links && projects.links.length > 3" style="padding: 1rem; display: flex; justify-content: flex-end; gap: 0.35rem;">
        <Link
          v-for="(link, i) in projects.links"
          :key="i"
          :href="link.url || '#'"
          v-html="link.label"
          class="btn btn-outline"
          :class="{ 'active': link.active, 'disabled': !link.url }"
          style="padding: 0.25rem 0.65rem; font-size: 0.75rem;"
        />
      </div>
    </div>

    <!-- Create Project Modal -->
    <div v-if="showCreateModal" class="modal-overlay" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
      <div class="card" style="width: 100%; max-width: 580px; max-height: 90vh; overflow-y: auto; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
          <h2 style="font-size: 1.2rem; font-weight: 800; color: var(--text-primary); margin: 0;">Add New Project Site</h2>
          <button @click="showCreateModal = false" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>

        <form @submit.prevent="submitProject">
          <div style="margin-bottom: 1rem;">
            <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-secondary);">Contractor / Customer *</label>
            <select v-model="form.customer_id" class="form-control" required style="width: 100%;">
              <option value="" disabled>Select Contractor</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.phone || 'No Phone' }})</option>
            </select>
          </div>

          <div style="margin-bottom: 1rem;">
            <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-secondary);">Project / Site Name *</label>
            <input v-model="form.name" type="text" class="form-control" placeholder="e.g. DHA Phase 6 Villa Plot 142 or Bahria Heights" required style="width: 100%;" />
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-secondary);">Plot / Sector #</label>
              <input v-model="form.plot_number" type="text" class="form-control" placeholder="Plot 142, Sector C" style="width: 100%;" />
            </div>
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-secondary);">City</label>
              <input v-model="form.city" type="text" class="form-control" placeholder="e.g. Lahore, Karachi, Islamabad" style="width: 100%;" />
            </div>
          </div>

          <div style="margin-bottom: 1rem;">
            <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-secondary);">Site Delivery Address</label>
            <textarea v-model="form.site_address" class="form-control" rows="2" placeholder="Full delivery address or landmark for truck drivers" style="width: 100%;"></textarea>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-secondary);">Site Supervisor / Munshi Name</label>
              <input v-model="form.supervisor_name" type="text" class="form-control" placeholder="e.g. Ustad Aslam Munshi" style="width: 100%;" />
            </div>
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-secondary);">Supervisor Phone</label>
              <input v-model="form.supervisor_phone" type="text" class="form-control" placeholder="0300-1234567" style="width: 100%;" />
            </div>
          </div>

          <div style="margin-bottom: 1.25rem;">
            <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-secondary);">Notes / Gate Entry Instructions</label>
            <textarea v-model="form.notes" class="form-control" rows="2" placeholder="e.g. Call supervisor 1 hour before truck arrives; Entry gate 2" style="width: 100%;"></textarea>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" @click="showCreateModal = false" class="btn btn-outline">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">Save Project Site</button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  projects: Object,
  customers: Array,
  filters: Object,
});

const showCreateModal = ref(false);
const selectedCustomerId = ref(props.filters?.customer_id || '');

const form = useForm({
  customer_id: '',
  name: '',
  site_address: '',
  plot_number: '',
  city: '',
  supervisor_name: '',
  supervisor_phone: '',
  notes: '',
  status: 'active',
});

function onCustomerFilter() {
  router.get('/hardware/projects', { customer_id: selectedCustomerId.value || undefined }, { preserveState: true, replace: true });
}

function submitProject() {
  form.post('/hardware/projects', {
    onSuccess: () => {
      showCreateModal.value = false;
      form.reset();
    },
  });
}
</script>
