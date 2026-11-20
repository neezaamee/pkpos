<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface ModifierOptionItem {
  id?: string;
  name: string;
  price_override_minor: number;
}

interface ModifierGroupItem {
  id: string;
  name: string;
  min_selections: number;
  max_selections: number;
  options: ModifierOptionItem[];
  products_count: number;
}

const props = defineProps<{
  groups: ModifierGroupItem[];
}>();

const showModal = ref(false);

const form = useForm({
  name: '',
  min_selections: 0,
  max_selections: 1,
  options: [
    { name: '', price_override_minor: 0 },
    { name: '', price_override_minor: 0 },
  ],
});

function addOptionRow() {
  form.options.push({ name: '', price_override_minor: 0 });
}

function removeOptionRow(idx: number) {
  if (form.options.length > 1) {
    form.options.splice(idx, 1);
  }
}

function submitGroup() {
  form.post(route('restaurant.modifiers.store'), {
    onSuccess: () => {
      showModal.value = false;
      form.reset();
    },
  });
}

function deleteGroup(group: ModifierGroupItem) {
  if (confirm(`Delete modifier group "${group.name}"?`)) {
    router.delete(route('restaurant.modifiers.destroy', group.id));
  }
}
</script>

<template>
  <Head title="Menu Modifiers & Add-ons - Restaurant Vertical" />

  <AdminLayout>
    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-700/60 pb-5">
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
            <span>🌶️</span> Menu Modifiers & Add-ons
          </h1>
          <p class="text-sm text-slate-400 mt-1">
            Spice levels, cooking instructions, meat temperatures, extra cheese, and sides with optional price surcharges.
          </p>
        </div>

        <button
          @click="showModal = true"
          class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm shadow-lg shadow-emerald-900/30 transition"
        >
          + Create Modifier Group
        </button>
      </div>

      <div v-if="groups.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div
          v-for="grp in groups"
          :key="grp.id"
          class="bg-slate-900/90 border border-slate-800 rounded-xl p-5 flex flex-col justify-between hover:border-slate-700 transition"
        >
          <div>
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-mono px-2 py-0.5 rounded bg-indigo-950 text-indigo-400">
                {{ grp.max_selections === 1 ? 'SINGLE CHOICE' : `MULTI CHOICE (Max ${grp.max_selections})` }}
              </span>
              <button @click="deleteGroup(grp)" class="text-slate-500 hover:text-rose-400 text-sm">
                &times;
              </button>
            </div>

            <h3 class="text-lg font-bold text-white">{{ grp.name }}</h3>
            <div class="text-xs text-slate-400 mt-1">
              {{ grp.min_selections > 0 ? 'Mandatory selection required' : 'Optional modifier' }}
            </div>

            <!-- Options list -->
            <div class="mt-4 space-y-1.5 border-t border-slate-800/80 pt-3">
              <div
                v-for="opt in grp.options"
                :key="opt.name"
                class="flex items-center justify-between text-xs py-1 px-2 rounded bg-slate-800/50"
              >
                <span class="text-slate-200">{{ opt.name }}</span>
                <span class="font-mono text-emerald-400 font-semibold">
                  {{ opt.price_override_minor > 0 ? `+Rs. ${(opt.price_override_minor / 100).toFixed(0)}` : 'Free' }}
                </span>
              </div>
            </div>
          </div>

          <div class="mt-4 pt-3 border-t border-slate-800 text-xs text-slate-400">
            Linked to <strong class="text-slate-200">{{ grp.products_count }} Menu Items</strong>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-16 bg-slate-900/50 border border-slate-800 rounded-xl">
        <div class="text-4xl mb-3">📋</div>
        <h3 class="text-lg font-bold text-white">No Modifier Groups Configured</h3>
        <p class="text-sm text-slate-400 max-w-md mx-auto mt-1 mb-4">
          Create modifier groups such as "Spice Level" (Mild, Medium, Spicy) or "Add-on Sauces" to customize restaurant dishes.
        </p>
        <button
          @click="showModal = true"
          class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm transition"
        >
          Create First Group
        </button>
      </div>

      <!-- Create Modifier Group Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
        <div class="bg-slate-900 border border-slate-700 rounded-xl max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
          <h3 class="text-lg font-bold text-white">Create Modifier Group</h3>

          <form @submit.prevent="submitGroup" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Group Name *</label>
              <input v-model="form.name" type="text" placeholder="e.g. Spice Level, Extra Toppings" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" required />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Min Selections</label>
                <input v-model.number="form.min_selections" type="number" min="0" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" />
                <span class="text-[11px] text-slate-500">0 = Optional, 1 = Mandatory</span>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Max Selections</label>
                <input v-model.number="form.max_selections" type="number" min="1" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" />
                <span class="text-[11px] text-slate-500">1 = Radio button, >1 = Checkboxes</span>
              </div>
            </div>

            <!-- Options builder -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="text-xs font-semibold text-slate-300 uppercase">Modifier Options & Add-on Prices</label>
                <button type="button" @click="addOptionRow" class="text-xs text-indigo-400 hover:underline">+ Add Option</button>
              </div>

              <div class="space-y-2">
                <div v-for="(opt, idx) in form.options" :key="idx" class="flex gap-2 items-center">
                  <input
                    v-model="opt.name"
                    type="text"
                    placeholder="Option name (e.g. Extra Cheese)"
                    class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-white text-sm"
                    required
                  />
                  <div class="w-32 flex items-center bg-slate-800 border border-slate-700 rounded-lg px-2">
                    <span class="text-xs text-slate-500 mr-1">Rs.</span>
                    <input
                      :value="opt.price_override_minor / 100"
                      @input="opt.price_override_minor = Math.round(Number(($event.target as HTMLInputElement).value) * 100)"
                      type="number"
                      step="any"
                      placeholder="0"
                      class="w-full bg-transparent text-white text-sm py-1.5 focus:outline-none"
                    />
                  </div>
                  <button type="button" @click="removeOptionRow(idx)" class="text-slate-500 hover:text-rose-400 p-1">
                    &times;
                  </button>
                </div>
              </div>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
              <button type="button" @click="showModal = false" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-sm">Cancel</button>
              <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm">Save Modifier Group</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
