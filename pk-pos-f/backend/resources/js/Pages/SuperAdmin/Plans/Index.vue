<template>
  <SuperAdminLayout title="SaaS Subscriptions & Quota Enforcement">
    <!-- Header Summary -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h2 style="font-size: 1.25rem; font-weight: 700;">SaaS Subscriptions & Pricing Tiers</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          Configure pricing packages, track Monthly Recurring Revenue (MRR), and enforce branch & terminal hardware quotas.
        </p>
      </div>
      <div style="display: flex; gap: 0.75rem; align-items: center;">
        <button @click="openAssignModal(null)" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.4rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <polyline points="16 11 18 13 22 9"></polyline>
          </svg>
          Assign Subscription
        </button>
        <button @click="openCreatePlanModal" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.4rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="16"></line>
            <line x1="8" y1="12" x2="16" y2="12"></line>
          </svg>
          New Plan Tier
        </button>
      </div>
    </div>

    <!-- Revenue & Subscription Metrics KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Monthly Recurring Revenue (MRR)
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #10b981;">
          PKR {{ metrics.mrr_pkr?.toLocaleString() || '0' }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Contracted active subscription volume
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Active Subscribers
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #38bdf8;">
          {{ metrics.active_subscribers_count }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Merchants on active / trialing plans
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Available Plan Tiers
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: var(--text-main);">
          {{ metrics.total_plans_count }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Configured monetization tiers
        </div>
      </div>
    </div>

    <!-- Plan Tiers Grid (Starter, Growth, Enterprise) -->
    <div style="margin-bottom: 2rem;">
      <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 1rem;">Standard Pricing Tiers & Hard Quotas</h3>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem;">
        <div
          v-for="plan in plans"
          :key="plan.id"
          class="card-table"
          style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid #6366f1;"
        >
          <div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
              <div>
                <h4 style="font-size: 1.15rem; font-weight: 700; margin: 0;">{{ plan.name }}</h4>
                <span class="badge badge-neutral" style="margin-top: 0.35rem; font-size: 0.7rem;">{{ plan.code }}</span>
              </div>
              <div style="text-align: right;">
                <div style="font-size: 1.25rem; font-weight: 800; color: #10b981;">
                  PKR {{ plan.price_pkr?.toLocaleString() }}
                </div>
                <div style="font-size: 0.72rem; color: var(--text-muted);">per {{ plan.billing_interval }}</div>
              </div>
            </div>

            <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0.85rem 0; line-height: 1.4;">
              {{ plan.description }}
            </p>

            <!-- Quota Badges -->
            <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 0.85rem; margin-bottom: 1rem;">
              <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 0.4rem;">
                <span style="color: var(--text-muted);">Max Branches:</span>
                <strong style="color: #38bdf8;">{{ plan.max_locations === null ? 'Unlimited' : plan.max_locations + ' Location(s)' }}</strong>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 0.8rem;">
                <span style="color: var(--text-muted);">Max POS Registers:</span>
                <strong style="color: #818cf8;">{{ plan.max_terminals === null ? 'Unlimited' : plan.max_terminals + ' Terminal(s)' }}</strong>
              </div>
            </div>

            <!-- Features Tags -->
            <div style="display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 1.25rem;">
              <span
                v-for="(f, i) in plan.features"
                :key="i"
                class="badge badge-neutral"
                style="font-size: 0.68rem; text-transform: lowercase;"
              >
                &bull; {{ f.replace(/_/g, ' ') }}
              </span>
            </div>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); pt-3; padding-top: 0.85rem;">
            <span style="font-size: 0.78rem; color: var(--text-muted);">
              <strong>{{ plan.active_subscribers_count }}</strong> merchants subscribed
            </span>
            <button @click="openEditPlanModal(plan)" class="btn btn-secondary" style="padding: 0.3rem 0.65rem; font-size: 0.75rem;">
              Edit Tier
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Active Merchant Subscriptions Table -->
    <div class="card-table">
      <div class="table-header">
        <h2>Merchant Subscriptions & Quota Consumption</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">
          Showing {{ subscriptions.from || 0 }} to {{ subscriptions.to || 0 }} of {{ subscriptions.total }} records
        </span>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Merchant</th>
              <th>Assigned Plan</th>
              <th>Branch Quota</th>
              <th>Terminal Quota</th>
              <th>Subscription Status</th>
              <th>Period</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in subscriptions.data" :key="item.id">
              <td>
                <div style="font-weight: 600;">{{ item.tenant_name }}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">
                  {{ item.tenant_province }}
                </div>
              </td>
              <td>
                <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.3);">
                  {{ item.plan_name }}
                </span>
              </td>
              <td>
                <span style="font-size: 0.85rem; font-weight: 600;" :style="{ color: isQuotaWarning(item.locations_usage.used, item.locations_usage.max) ? '#ef4444' : 'var(--text-main)' }">
                  {{ item.locations_usage.used }} / {{ item.locations_usage.max === null ? '∞' : item.locations_usage.max }}
                </span>
                <div style="font-size: 0.7rem; color: var(--text-muted);">branches</div>
              </td>
              <td>
                <span style="font-size: 0.85rem; font-weight: 600;" :style="{ color: isQuotaWarning(item.terminals_usage.used, item.terminals_usage.max) ? '#ef4444' : 'var(--text-main)' }">
                  {{ item.terminals_usage.used }} / {{ item.terminals_usage.max === null ? '∞' : item.terminals_usage.max }}
                </span>
                <div style="font-size: 0.7rem; color: var(--text-muted);">active registers</div>
              </td>
              <td>
                <span class="badge" :class="getStatusBadgeClass(item.status)">
                  {{ item.status }}
                </span>
              </td>
              <td>
                <div style="font-size: 0.8rem;">From: {{ item.starts_at }}</div>
                <div style="font-size: 0.72rem; color: var(--text-muted);">Until: {{ item.ends_at }}</div>
              </td>
              <td style="text-align: right;">
                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                  <button @click="openAssignModal(item)" class="btn btn-secondary" style="padding: 0.25rem 0.55rem; font-size: 0.75rem;">
                    Change Plan
                  </button>
                  <button
                    v-if="item.status === 'active' || item.status === 'trialing'"
                    @click="cancelSubscription(item)"
                    class="btn btn-danger"
                    style="padding: 0.25rem 0.55rem; font-size: 0.75rem;"
                  >
                    Cancel
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="subscriptions.data.length === 0">
              <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                No subscriptions found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="subscriptions.links && subscriptions.links.length > 3" style="display: flex; justify-content: flex-end; padding: 1rem; gap: 0.35rem; border-top: 1px solid var(--border-color);">
        <Link
          v-for="(link, i) in subscriptions.links"
          :key="i"
          :href="link.url || '#'"
          class="btn btn-secondary"
          :class="{ 'btn-primary': link.active, disabled: !link.url }"
          style="padding: 0.35rem 0.65rem; font-size: 0.75rem;"
          v-html="link.label"
        />
      </div>
    </div>

    <!-- Assign / Upgrade Subscription Modal -->
    <div v-if="showAssignModal" class="modal-overlay" @click.self="showAssignModal = false">
      <div class="modal-container" style="max-width: 520px;">
        <div class="modal-header">
          <h3>Assign / Upgrade Subscription</h3>
          <button @click="showAssignModal = false" class="close-btn">&times;</button>
        </div>
        <form @submit.prevent="submitAssignForm">
          <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
            <div class="form-group">
              <label class="form-label">Merchant Tenant *</label>
              <select v-model="assignForm.tenant_id" class="form-input" required>
                <option value="" disabled>Select Merchant...</option>
                <option v-for="t in tenants" :key="t.id" :value="t.id">
                  {{ t.name }} ({{ t.province.toUpperCase() }})
                </option>
              </select>
              <span v-if="assignForm.errors.tenant_id" class="form-error">{{ assignForm.errors.tenant_id }}</span>
            </div>

            <div class="form-group">
              <label class="form-label">Pricing Plan Tier *</label>
              <select v-model="assignForm.plan_id" class="form-input" required>
                <option value="" disabled>Select Plan...</option>
                <option v-for="p in plans" :key="p.id" :value="p.id">
                  {{ p.name }} — PKR {{ p.price_pkr?.toLocaleString() }}/mo
                </option>
              </select>
              <span v-if="assignForm.errors.plan_id" class="form-error">{{ assignForm.errors.plan_id }}</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Status *</label>
                <select v-model="assignForm.status" class="form-input" required>
                  <option value="active">Active</option>
                  <option value="trialing">Trialing</option>
                  <option value="past_due">Past Due</option>
                  <option value="canceled">Canceled</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Starts At *</label>
                <input
                  v-model="assignForm.starts_at"
                  type="date"
                  class="form-input"
                  required
                />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Ends At (Optional)</label>
              <input
                v-model="assignForm.ends_at"
                type="date"
                class="form-input"
              />
              <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem; display: block;">
                Leave empty for ongoing monthly recurring subscription.
              </span>
            </div>

            <div class="form-group">
              <label class="form-label">Billing Notes</label>
              <textarea
                v-model="assignForm.notes"
                rows="2"
                class="form-input"
                placeholder="Direct bank transfer, Raast payment, or special discount terms..."
              ></textarea>
            </div>
          </div>

          <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--border-color);">
            <button type="button" @click="showAssignModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="assignForm.processing">
              {{ assignForm.processing ? 'Assigning...' : 'Assign Subscription' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Create / Edit Plan Modal -->
    <div v-if="showPlanModal" class="modal-overlay" @click.self="showPlanModal = false">
      <div class="modal-container" style="max-width: 580px;">
        <div class="modal-header">
          <h3>{{ editingPlan ? 'Edit Pricing Plan' : 'Create Pricing Plan' }}</h3>
          <button @click="showPlanModal = false" class="close-btn">&times;</button>
        </div>
        <form @submit.prevent="submitPlanForm">
          <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Plan Code *</label>
                <input
                  v-model="planForm.code"
                  type="text"
                  class="form-input"
                  placeholder="e.g. starter, retail_pro"
                  required
                />
                <span v-if="planForm.errors.code" class="form-error">{{ planForm.errors.code }}</span>
              </div>

              <div class="form-group">
                <label class="form-label">Plan Name *</label>
                <input
                  v-model="planForm.name"
                  type="text"
                  class="form-input"
                  placeholder="e.g. Retail Growth Tier"
                  required
                />
                <span v-if="planForm.errors.name" class="form-error">{{ planForm.errors.name }}</span>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Monthly Price (PKR) *</label>
                <input
                  v-model.number="pricePkrInput"
                  type="number"
                  class="form-input"
                  min="0"
                  placeholder="8500"
                  required
                />
                <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem; display: block;">
                  {{ (pricePkrInput * 100).toLocaleString() }} paisa
                </span>
              </div>

              <div class="form-group">
                <label class="form-label">Billing Interval</label>
                <select v-model="planForm.billing_interval" class="form-input">
                  <option value="month">Monthly</option>
                  <option value="year">Yearly</option>
                </select>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Max Branches (Locations)</label>
                <input
                  v-model.number="planForm.max_locations"
                  type="number"
                  class="form-input"
                  placeholder="Leave empty for unlimited"
                />
                <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem; display: block;">
                  Empty = Unlimited locations
                </span>
              </div>

              <div class="form-group">
                <label class="form-label">Max POS Terminals</label>
                <input
                  v-model.number="planForm.max_terminals"
                  type="number"
                  class="form-input"
                  placeholder="Leave empty for unlimited"
                />
                <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem; display: block;">
                  Empty = Unlimited cash registers
                </span>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Description</label>
              <textarea
                v-model="planForm.description"
                rows="2"
                class="form-input"
                placeholder="Short description highlighting who this tier is designed for..."
              ></textarea>
            </div>

            <div class="form-group">
              <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" v-model="planForm.is_active" />
                <span style="font-size: 0.85rem;">Active tier available for merchant subscription</span>
              </label>
            </div>
          </div>

          <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--border-color);">
            <button type="button" @click="showPlanModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="planForm.processing">
              {{ planForm.processing ? 'Saving...' : (editingPlan ? 'Update Plan' : 'Save Plan') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  plans: Array,
  subscriptions: Object,
  tenants: Array,
  metrics: Object,
  filters: Object,
});

// Assign Subscription Modal
const showAssignModal = ref(false);
const assignForm = useForm({
  tenant_id: '',
  plan_id: '',
  status: 'active',
  starts_at: new Date().toISOString().substring(0, 10),
  ends_at: '',
  notes: '',
});

function openAssignModal(subscriptionItem = null) {
  assignForm.reset();
  assignForm.clearErrors();
  assignForm.tenant_id = subscriptionItem?.tenant_id || props.tenants?.[0]?.id || '';
  assignForm.plan_id = subscriptionItem?.plan_id || props.plans?.[0]?.id || '';
  assignForm.status = subscriptionItem?.status || 'active';
  assignForm.starts_at = new Date().toISOString().substring(0, 10);
  assignForm.ends_at = '';
  assignForm.notes = '';
  showAssignModal.value = true;
}

function submitAssignForm() {
  assignForm.post('/superadmin/subscriptions/assign', {
    onSuccess: () => {
      showAssignModal.value = false;
    },
  });
}

function cancelSubscription(item) {
  if (confirm(`Are you sure you want to cancel the subscription for '${item.tenant_name}'?`)) {
    router.post(`/superadmin/subscriptions/${item.id}/cancel`);
  }
}

// Plan Create/Edit Modal
const showPlanModal = ref(false);
const editingPlan = ref(null);
const pricePkrInput = ref(3500);

const planForm = useForm({
  code: '',
  name: '',
  description: '',
  price_minor: 350000,
  billing_interval: 'month',
  max_locations: 1,
  max_terminals: 1,
  features: ['fbr_integration', 'offline_sales', 'thermal_receipts'],
  is_active: true,
});

function openCreatePlanModal() {
  editingPlan.value = null;
  planForm.reset();
  planForm.clearErrors();
  planForm.code = '';
  planForm.name = '';
  planForm.description = '';
  pricePkrInput.value = 3500;
  planForm.billing_interval = 'month';
  planForm.max_locations = 1;
  planForm.max_terminals = 1;
  planForm.is_active = true;
  showPlanModal.value = true;
}

function openEditPlanModal(plan) {
  editingPlan.value = plan;
  planForm.clearErrors();
  planForm.code = plan.code;
  planForm.name = plan.name;
  planForm.description = plan.description || '';
  pricePkrInput.value = Math.round(plan.price_minor / 100);
  planForm.billing_interval = plan.billing_interval;
  planForm.max_locations = plan.max_locations;
  planForm.max_terminals = plan.max_terminals;
  planForm.is_active = plan.is_active;
  showPlanModal.value = true;
}

function submitPlanForm() {
  planForm.price_minor = Math.round(pricePkrInput.value * 100);

  if (editingPlan.value) {
    planForm.put(`/superadmin/plans/${editingPlan.value.id}`, {
      onSuccess: () => {
        showPlanModal.value = false;
      },
    });
  } else {
    planForm.post('/superadmin/plans', {
      onSuccess: () => {
        showPlanModal.value = false;
      },
    });
  }
}

function isQuotaWarning(used, max) {
  if (max === null) return false;
  return used >= max;
}

function getStatusBadgeClass(status) {
  switch (status) {
    case 'active': return 'badge-success';
    case 'trialing': return 'badge-warning';
    case 'past_due': return 'badge-danger';
    case 'canceled': return 'badge-neutral';
    default: return 'badge-neutral';
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 1.5rem;
}

.modal-container {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  width: 100%;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
  animation: modalIn 0.15s ease-out;
}

@keyframes modalIn {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0;
}

.close-btn {
  background: transparent;
  border: none;
  font-size: 1.5rem;
  line-height: 1;
  color: var(--text-muted);
  cursor: pointer;
}

.close-btn:hover {
  color: var(--text-main);
}

.modal-body {
  padding: 1.5rem;
}

.form-error {
  color: #ef4444;
  font-size: 0.75rem;
  margin-top: 0.25rem;
  display: block;
}
</style>

