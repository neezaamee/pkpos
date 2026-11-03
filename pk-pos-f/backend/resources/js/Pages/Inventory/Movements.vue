<template>
  <AdminLayout title="Stock Movement Audit Log">
    <div class="card-table">
      <div class="table-header">
        <div>
          <h2>Immutable Movement Log</h2>
          <div style="font-size: 0.8rem; color: var(--text-muted);">
            Append-only physical inventory movements recorded across sales, restocks, and adjustments
          </div>
        </div>

        <Link href="/inventory/levels" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.85rem;">
          ← Stock Levels
        </Link>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Product</th>
              <th>Location</th>
              <th>Movement Type</th>
              <th>Delta Qty</th>
              <th>Reference</th>
              <th>Note</th>
              <th>Logged By</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in movements.data" :key="m.id">
              <td style="font-size: 0.8rem; font-family: var(--font-mono); color: var(--text-muted);">
                {{ formatDate(m.created_at) }}
              </td>
              <td style="font-weight: 600; color: var(--text-primary);">
                {{ m.product?.name || 'General Product' }}
              </td>
              <td>{{ m.location?.name || 'Store Location' }}</td>
              <td>
                <span :class="['badge', getBadgeClass(m.movement_type)]">
                  <span class="badge-dot"></span>
                  {{ m.movement_type }}
                </span>
              </td>
              <td
                style="font-size: 1.05rem; font-weight: 700; font-family: var(--font-mono);"
                :style="m.quantity_delta > 0 ? 'color: var(--badge-green-text);' : 'color: var(--badge-red-text);'"
              >
                {{ m.quantity_delta > 0 ? `+${m.quantity_delta}` : m.quantity_delta }}
              </td>
              <td style="font-size: 0.8rem; font-family: var(--font-mono); color: var(--text-muted);">
                {{ m.reference_type ? `${m.reference_type}` : '—' }}
                <span v-if="m.reference_id" style="display: block; font-size: 0.7rem; color: var(--text-muted);">
                  {{ m.reference_id.substring(0, 8) }}...
                </span>
              </td>
              <td style="font-size: 0.825rem; color: var(--text-secondary);">
                {{ m.note || '—' }}
              </td>
              <td style="font-size: 0.8rem; color: var(--text-muted);">
                {{ m.creator?.name || 'POS Worker / System' }}
              </td>
            </tr>
            <tr v-if="movements.data.length === 0">
              <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                No stock movements logged yet.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="movements.links && movements.links.length > 3" class="pagination">
        <Link
          v-for="(link, index) in movements.links"
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
  movements: {
    type: Object,
    required: true,
  },
});

function getBadgeClass(type) {
  switch (type) {
    case 'sale':
      return 'badge-failed';
    case 'restock':
      return 'badge-submitted';
    case 'return':
      return 'badge-offline';
    case 'opening':
      return 'badge-neutral';
    default:
      return 'badge-offline';
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}
</script>
