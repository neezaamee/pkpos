<template>
  <AdminLayout title="Create Price List">
    <div style="max-width: 640px; margin: 0 auto;">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
        <div>
          <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-primary); margin: 0;">
            Create Price List
          </h2>
          <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
            Define a specialized pricing schedule (e.g. Wholesale, Dealer, Corporate)
          </div>
        </div>
        <Link href="/price-lists" class="btn btn-outline" style="padding: 0.4rem 0.85rem;">
          &larr; Back to Price Lists
        </Link>
      </div>

      <div class="card" style="padding: 2rem;">
        <form @submit.prevent="submit">
          <div style="margin-bottom: 1.25rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Price List Name <span style="color: #ef4444;">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              class="form-control"
              placeholder="e.g. Wholesale Tier A, Dealer Rates, VIP Club"
              required
            />
            <div v-if="form.errors.name" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
              {{ form.errors.name }}
            </div>
          </div>

          <div style="margin-bottom: 1.25rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Unique Code Identifier <span style="color: #ef4444;">*</span>
            </label>
            <input
              v-model="form.code"
              type="text"
              class="form-control"
              placeholder="e.g. wholesale, dealer_tier_1, corporate"
              required
            />
            <div v-if="form.errors.code" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
              {{ form.errors.code }}
            </div>
          </div>

          <div style="margin-bottom: 1.25rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Description
            </label>
            <textarea
              v-model="form.description"
              class="form-control"
              rows="2"
              placeholder="Rules, eligible customer categories, or discount policy..."
            ></textarea>
          </div>

          <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem;">
            <input
              id="is_default"
              v-model="form.is_default"
              type="checkbox"
              style="width: 17px; height: 17px; cursor: pointer;"
            />
            <label for="is_default" style="font-size: 0.85rem; color: var(--text-primary); cursor: pointer;">
              Set as Default Price List (Applies to all walk-in retail transactions)
            </label>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <Link href="/price-lists" class="btn btn-outline">
              Cancel
            </Link>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="form.processing"
              style="padding: 0.5rem 1.5rem;"
            >
              <span v-if="form.processing">Creating...</span>
              <span v-else>Save &amp; Configure Tiers</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const form = useForm({
  name: '',
  code: '',
  description: '',
  is_default: false,
});

function submit() {
  form.post('/price-lists');
}
</script>
