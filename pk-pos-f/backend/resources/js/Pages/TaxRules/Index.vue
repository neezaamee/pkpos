<template>
  <AdminLayout title="Tax Rule Engine">
    <div class="card-table">
      <div class="table-header">
        <div>
          <h2>Tax Categories & Authority Rules</h2>
          <div style="font-size: 0.8rem; color: var(--text-muted);">
            Configurable province, payment method, and authority GST/SST rates
          </div>
        </div>

        <Link href="/tax-rules/create" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
          + New Rule
        </Link>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Rule Name</th>
              <th>Category</th>
              <th>Province</th>
              <th>Payment</th>
              <th>Rate (%)</th>
              <th>Authority</th>
              <th>Effective Dates</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rule in taxRules.data" :key="rule.id">
              <td style="font-weight: 600; color: var(--text-primary);">
                {{ rule.name }}
              </td>
              <td>
                <span class="badge badge-neutral">
                  {{ rule.tax_category }}
                </span>
              </td>
              <td style="text-transform: capitalize;">
                {{ rule.province }}
              </td>
              <td style="text-transform: capitalize;">
                {{ rule.payment_method }}
              </td>
              <td style="font-weight: 700; color: var(--badge-green-text); font-family: var(--font-mono);">
                {{ (rule.rate_basis_points / 100).toFixed(2) }}%
              </td>
              <td>
                <span class="badge badge-submitted" style="text-transform: uppercase;">
                  {{ rule.authority }}
                </span>
              </td>
              <td style="font-size: 0.8rem; color: var(--text-muted); font-family: var(--font-mono);">
                {{ rule.effective_from }} → {{ rule.effective_to || 'Ongoing' }}
              </td>
              <td>
                <span :class="['badge', rule.is_active ? 'badge-submitted' : 'badge-failed']">
                  <span class="badge-dot"></span>
                  {{ rule.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
            </tr>
            <tr v-if="taxRules.data.length === 0">
              <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                No tax rules configured. Click "New Rule" to set up rates.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="taxRules.links && taxRules.links.length > 3" class="pagination">
        <Link
          v-for="(link, index) in taxRules.links"
          :key="index"
          :href="link.url || '#'"
          class="page-btn"
          :class="{ active: link.active, disabled: !link.url }"
          v-html="link.label"
        />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
  taxRules: {
    type: Object,
    required: true,
  },
});
</script>
