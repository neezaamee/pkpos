<template>
  <SuperAdminLayout title="Support Knowledge Base">
    <!-- Header Summary -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h2 style="font-size: 1.25rem; font-weight: 700;">Support Knowledge Base & Playbooks</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          Standard operating procedures, canned resolutions, and hardware debugging playbooks for Pakistani retail operations.
        </p>
      </div>
      <div>
        <Link href="/superadmin/support/tickets" class="btn btn-secondary">
          &larr; Back to Tickets Queue
        </Link>
      </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card-table" style="padding: 1.25rem; margin-bottom: 1.5rem;">
      <form @submit.prevent="applyFilters" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0; min-width: 280px; flex: 1;">
          <label class="form-label" style="font-size: 0.78rem;">Search Knowledge Articles</label>
          <input
            v-model="filterForm.search"
            type="text"
            class="form-input"
            placeholder="Search FBR 401, ESC/POS baud rate, 24h sync..."
          />
        </div>

        <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
          <label class="form-label" style="font-size: 0.78rem;">Category</label>
          <select v-model="filterForm.category" class="form-input">
            <option value="">All Categories</option>
            <option value="fbr_fiscal">FBR Fiscal Integration</option>
            <option value="hardware_printers">Hardware & Printers</option>
            <option value="taxes">Provincial Taxes</option>
            <option value="general">General Guides</option>
          </select>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
          <button type="submit" class="btn btn-primary" style="padding: 0.55rem 0.85rem;">
            Search
          </button>
          <button type="button" @click="resetFilters" class="btn btn-secondary" style="padding: 0.55rem 0.85rem;">
            Reset
          </button>
        </div>
      </form>
    </div>

    <!-- Articles Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.25rem;">
      <div
        v-for="art in articles"
        :key="art.id"
        class="card-table"
        style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;"
      >
        <div>
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
            <span class="badge" :class="getCategoryBadgeClass(art.category)">
              {{ formatCategory(art.category) }}
            </span>
            <span style="font-size: 0.72rem; color: var(--text-muted);">
              Updated: {{ art.updated_at }}
            </span>
          </div>

          <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0 0 0.75rem 0; color: #38bdf8;">
            {{ art.title }}
          </h3>

          <div style="background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.85rem; font-size: 0.8rem; line-height: 1.6; white-space: pre-wrap; font-family: inherit; color: var(--text-main); max-height: 260px; overflow-y: auto;">
            {{ art.content_markdown }}
          </div>
        </div>

        <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
          <button @click="copyCannedText(art.content_markdown)" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.3rem 0.65rem;">
            Copy Canned Reply
          </button>
        </div>
      </div>

      <div v-if="articles.length === 0" class="card-table" style="grid-column: 1 / -1; padding: 3rem; text-align: center; color: var(--text-muted);">
        No knowledge base articles match your search criteria.
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  articles: Array,
  filters: Object,
});

const filterForm = reactive({
  category: props.filters?.category || '',
  search: props.filters?.search || '',
});

function applyFilters() {
  router.get('/superadmin/support/knowledge-base', filterForm, { preserveState: true, replace: true });
}

function resetFilters() {
  filterForm.category = '';
  filterForm.search = '';
  applyFilters();
}

function copyCannedText(text) {
  navigator.clipboard.writeText(text);
  alert('Canned playbook response copied to clipboard!');
}

function formatCategory(c) {
  const map = {
    fbr_fiscal: 'FBR Fiscalization',
    hardware_printers: 'Printers & ESC/POS',
    taxes: 'Provincial Tax Rules',
    general: 'General Operations',
  };
  return map[c] || c;
}

function getCategoryBadgeClass(c) {
  switch (c) {
    case 'fbr_fiscal': return 'badge-success';
    case 'hardware_printers': return 'badge-warning';
    case 'taxes': return 'badge-neutral';
    default: return 'badge-neutral';
  }
}
</script>

