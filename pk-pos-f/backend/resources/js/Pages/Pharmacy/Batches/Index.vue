<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  batches: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({ filter: 'all', search: '' }),
  },
  metrics: {
    type: Object,
    required: true,
  },
  products: {
    type: Array,
    default: () => [],
  },
  locations: {
    type: Array,
    default: () => [],
  },
  suppliers: {
    type: Array,
    default: () => [],
  },
})

const search = ref(props.filters.search || '')
const activeFilter = ref(props.filters.filter || 'all')
const showCreateModal = ref(false)
const selectedBatchForEdit = ref(null)

const form = useForm({
  product_id: '',
  location_id: props.locations[0]?.id || '',
  supplier_id: '',
  batch_number: '',
  manufacturing_date: '',
  expiry_date: '',
  quantity_on_hand: 100,
  mrp_minor: 0,
  cost_minor: 0,
  status: 'active',
})

const applySearch = () => {
  router.get('/pharmacy/batches', {
    search: search.value,
    filter: activeFilter.value,
  }, { preserveState: true, replace: true })
}

const setFilter = (f) => {
  activeFilter.value = f
  router.get('/pharmacy/batches', {
    search: search.value,
    filter: f,
  }, { preserveState: true, replace: true })
}

const submitCreate = () => {
  form.post('/pharmacy/batches', {
    onSuccess: () => {
      showCreateModal.value = false
      form.reset()
    },
  })
}

const quarantineAllExpired = () => {
  if (confirm('Are you sure you want to quarantine all expired medicine batches?')) {
    router.post('/pharmacy/batches/quarantine-expired')
  }
}

const formatRupees = (minor) => {
  if (!minor) return '0.00'
  return (minor / 100).toLocaleString('en-PK', { minimumFractionDigits: 2 })
}
</script>

<template>
  <AdminLayout>
    <div class="page-container" style="padding: 1.5rem; max-width: 1400px; margin: 0 auto;">
      <!-- Page Header -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
          <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <span style="font-size: 0.8rem; font-weight: 700; color: #10b981; text-transform: uppercase; letter-spacing: 0.05em;">Pharmacy Vertical</span>
            <span style="color: var(--text-muted);">&bull;</span>
            <span style="font-size: 0.8rem; color: var(--text-muted);">DRAP Compliance &amp; Inventory</span>
          </div>
          <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0 0 0.25rem 0;">
            Batches &amp; Expiry Tracking (FEFO)
          </h1>
          <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;">
            Automated First-Expired, First-Out (FEFO) dispensing, batch lot tracking, DRAP registration numbers, and quarantine control.
          </p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
          <button
            type="button"
            @click="quarantineAllExpired"
            style="padding: 0.6rem 1rem; border-radius: 8px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; font-weight: 600; cursor: pointer; font-size: 0.85rem;"
          >
            Quarantine Expired Batches
          </button>
          <button
            type="button"
            @click="showCreateModal = true"
            style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; font-weight: 600; border-radius: 8px; background: #059669; color: #fff; border: none; cursor: pointer;"
          >
            + Register Batch
          </button>
        </div>
      </div>

      <!-- Flash Notification -->
      <div v-if="$page.props.flash?.success" style="padding: 0.875rem 1.25rem; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; color: #10b981; margin-bottom: 1.5rem; font-size: 0.875rem;">
        ✓ {{ $page.props.flash.success }}
      </div>

      <!-- Metric Cards -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 10px; padding: 1.25rem;">
          <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Active Lots &amp; Batches</div>
          <div style="font-size: 1.75rem; font-weight: 800; color: #10b981; margin-top: 0.25rem;">
            {{ metrics.total_active }}
          </div>
          <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Ready for FEFO dispatch</div>
        </div>

        <div class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 10px; padding: 1.25rem;">
          <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Expiring in 90 Days</div>
          <div style="font-size: 1.75rem; font-weight: 800; color: #f59e0b; margin-top: 0.25rem;">
            {{ metrics.expiring_90_days }}
          </div>
          <div style="font-size: 0.75rem; color: #f59e0b; margin-top: 0.25rem;">Eligible for distributor return</div>
        </div>

        <div class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 10px; padding: 1.25rem;">
          <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Expired / Quarantined</div>
          <div style="font-size: 1.75rem; font-weight: 800; color: #ef4444; margin-top: 0.25rem;">
            {{ metrics.expired_count }}
          </div>
          <div style="font-size: 0.75rem; color: #ef4444; margin-top: 0.25rem;">Strictly blocked at checkout</div>
        </div>
      </div>

      <!-- Filters & Search Toolbar -->
      <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
        <!-- Filter Tabs -->
        <div style="display: flex; gap: 0.5rem; background: var(--bg-elevated); padding: 0.25rem; border-radius: 8px; border: 1px solid var(--border-color);">
          <button
            type="button"
            @click="setFilter('all')"
            :style="{
              padding: '0.4rem 0.85rem',
              borderRadius: '6px',
              border: 'none',
              background: activeFilter === 'all' ? '#059669' : 'transparent',
              color: activeFilter === 'all' ? '#fff' : 'var(--text-secondary)',
              fontWeight: '600',
              fontSize: '0.8rem',
              cursor: 'pointer'
            }"
          >
            All Batches
          </button>
          <button
            type="button"
            @click="setFilter('expiring_30')"
            :style="{
              padding: '0.4rem 0.85rem',
              borderRadius: '6px',
              border: 'none',
              background: activeFilter === 'expiring_30' ? '#ef4444' : 'transparent',
              color: activeFilter === 'expiring_30' ? '#fff' : 'var(--text-secondary)',
              fontWeight: '600',
              fontSize: '0.8rem',
              cursor: 'pointer'
            }"
          >
            Expiring &le; 30 Days
          </button>
          <button
            type="button"
            @click="setFilter('expiring_90')"
            :style="{
              padding: '0.4rem 0.85rem',
              borderRadius: '6px',
              border: 'none',
              background: activeFilter === 'expiring_90' ? '#f59e0b' : 'transparent',
              color: activeFilter === 'expiring_90' ? '#fff' : 'var(--text-secondary)',
              fontWeight: '600',
              fontSize: '0.8rem',
              cursor: 'pointer'
            }"
          >
            Expiring &le; 90 Days
          </button>
          <button
            type="button"
            @click="setFilter('expired')"
            :style="{
              padding: '0.4rem 0.85rem',
              borderRadius: '6px',
              border: 'none',
              background: activeFilter === 'expired' ? '#ef4444' : 'transparent',
              color: activeFilter === 'expired' ? '#fff' : 'var(--text-secondary)',
              fontWeight: '600',
              fontSize: '0.8rem',
              cursor: 'pointer'
            }"
          >
            Expired
          </button>
          <button
            type="button"
            @click="setFilter('quarantined')"
            :style="{
              padding: '0.4rem 0.85rem',
              borderRadius: '6px',
              border: 'none',
              background: activeFilter === 'quarantined' ? '#6b7280' : 'transparent',
              color: activeFilter === 'quarantined' ? '#fff' : 'var(--text-secondary)',
              fontWeight: '600',
              fontSize: '0.8rem',
              cursor: 'pointer'
            }"
          >
            Quarantined
          </button>
        </div>

        <!-- Search Input -->
        <div style="display: flex; gap: 0.5rem;">
          <input
            type="text"
            v-model="search"
            placeholder="Search Batch #, Generic Name, DRAP #..."
            @keydown.enter="applySearch"
            style="padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.85rem; width: 320px;"
          />
          <button
            type="button"
            @click="applySearch"
            style="padding: 0.5rem 1rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); cursor: pointer;"
          >
            Search
          </button>
        </div>
      </div>

      <!-- Batches Table -->
      <div class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
          <thead>
            <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-elevated); color: var(--text-secondary); text-align: left;">
              <th style="padding: 0.75rem 1rem;">Medicine / Brand</th>
              <th style="padding: 0.75rem 1rem;">Generic &amp; DRAP #</th>
              <th style="padding: 0.75rem 1rem;">Batch #</th>
              <th style="padding: 0.75rem 1rem;">Expiry Date (FEFO)</th>
              <th style="padding: 0.75rem 1rem; text-align: right;">Stock on Hand</th>
              <th style="padding: 0.75rem 1rem; text-align: right;">DRAP MRP</th>
              <th style="padding: 0.75rem 1rem; text-align: center;">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="b in batches.data"
              :key="b.id"
              style="border-bottom: 1px solid var(--border-color);"
            >
              <td style="padding: 0.85rem 1rem;">
                <div style="font-weight: 700; color: var(--text-primary);">
                  {{ b.product?.name }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem; margin-top: 0.15rem;">
                  <span v-if="b.product?.is_prescription_required" style="background: rgba(2, 132, 199, 0.2); color: #38bdf8; padding: 0.1rem 0.35rem; border-radius: 4px; font-weight: 700;">Rx</span>
                  <span v-if="b.product?.is_narcotic" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; padding: 0.1rem 0.35rem; border-radius: 4px; font-weight: 700;">Controlled Drug</span>
                  <span v-if="b.product?.cold_chain" style="background: rgba(56, 189, 248, 0.2); color: #38bdf8; padding: 0.1rem 0.35rem; border-radius: 4px;">❄️ 2-8°C</span>
                  <span>{{ b.location?.name }}</span>
                </div>
              </td>
              <td style="padding: 0.85rem 1rem;">
                <div style="color: var(--text-primary); font-weight: 500;">
                  {{ b.product?.generic_name || 'N/A' }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                  DRAP: {{ b.product?.drap_registration_number || 'N/A' }}
                </div>
              </td>
              <td style="padding: 0.85rem 1rem; font-family: monospace; font-weight: 700; color: #38bdf8;">
                {{ b.batch_number }}
              </td>
              <td style="padding: 0.85rem 1rem;">
                <div style="font-weight: 700; font-family: monospace;" :style="{
                  color: new Date(b.expiry_date) < new Date() ? '#ef4444' : (b.is_nearing_expiry ? '#f59e0b' : '#10b981')
                }">
                  {{ b.expiry_date }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                  <span v-if="new Date(b.expiry_date) < new Date()" style="color: #ef4444; font-weight: 700;">EXPIRED</span>
                  <span v-else>{{ b.days_until_expiry }} days left</span>
                </div>
              </td>
              <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 700; font-family: monospace; color: var(--text-primary);">
                {{ b.quantity_on_hand }} {{ b.product?.unit || 'units' }}
              </td>
              <td style="padding: 0.85rem 1rem; text-align: right; font-family: monospace; color: var(--text-primary);">
                Rs. {{ formatRupees(b.mrp_minor) }}
              </td>
              <td style="padding: 0.85rem 1rem; text-align: center;">
                <span
                  style="padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;"
                  :style="{
                    background: b.status === 'active' ? 'rgba(16, 185, 129, 0.15)' : (b.status === 'expired' ? 'rgba(239, 68, 68, 0.15)' : 'rgba(107, 114, 128, 0.2)'),
                    color: b.status === 'active' ? '#10b981' : (b.status === 'expired' ? '#ef4444' : '#9ca3af')
                  }"
                >
                  {{ b.status }}
                </span>
              </td>
            </tr>

            <tr v-if="batches.data.length === 0">
              <td colspan="7" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                No medicine batches found matching the selected filter.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Register Batch Modal -->
      <div v-if="showCreateModal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
        <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; max-width: 600px; width: 100%; padding: 1.5rem;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-primary);">
              Register Medicine Batch (FEFO Lot)
            </h3>
            <button type="button" @click="showCreateModal = false" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">&times;</button>
          </div>

          <form @submit.prevent="submitCreate" style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                Medicine / Drug *
              </label>
              <select v-model="form.product_id" required style="width: 100%; padding: 0.55rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                <option value="">Select Medicine</option>
                <option v-for="p in products" :key="p.id" :value="p.id">
                  {{ p.name }} ({{ p.generic_name || 'No Generic' }}) - {{ p.drap_registration_number || 'No DRAP' }}
                </option>
              </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  Batch Number *
                </label>
                <input type="text" v-model="form.batch_number" required placeholder="e.g. B-2026-X8" style="width: 100%; padding: 0.55rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);" />
              </div>

              <div>
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  Physical Location *
                </label>
                <select v-model="form.location_id" required style="width: 100%; padding: 0.55rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                  <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                </select>
              </div>

              <div>
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  Manufacturing Date
                </label>
                <input type="date" v-model="form.manufacturing_date" style="width: 100%; padding: 0.55rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);" />
              </div>

              <div>
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  Expiry Date * (FEFO)
                </label>
                <input type="date" v-model="form.expiry_date" required style="width: 100%; padding: 0.55rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);" />
              </div>

              <div>
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  Quantity on Hand *
                </label>
                <input type="number" v-model.number="form.quantity_on_hand" required min="0" step="1" style="width: 100%; padding: 0.55rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);" />
              </div>

              <div>
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  DRAP MRP (Paisas)
                </label>
                <input type="number" v-model.number="form.mrp_minor" placeholder="e.g. 50000 = Rs. 500" style="width: 100%; padding: 0.55rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);" />
              </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
              <button type="button" @click="showCreateModal = false" style="padding: 0.55rem 1rem; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; color: var(--text-secondary); cursor: pointer;">
                Cancel
              </button>
              <button type="submit" :disabled="form.processing" style="padding: 0.55rem 1.25rem; border-radius: 6px; background: #059669; color: #fff; border: none; font-weight: 700; cursor: pointer;">
                {{ form.processing ? 'Saving...' : 'Register Batch' }}
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
