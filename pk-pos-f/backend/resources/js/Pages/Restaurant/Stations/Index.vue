<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface StationItem {
  id: string;
  name: string;
  printer_ip_or_name?: string;
  is_active: boolean;
  products_count: number;
}

interface LocationItem {
  id: string;
  name: string;
}

const props = defineProps<{
  stations: StationItem[];
  locations: LocationItem[];
  selectedLocationId?: string;
}>();

const showModal = ref(false);

const form = useForm({
  location_id: props.selectedLocationId || props.locations[0]?.id || '',
  name: '',
  printer_ip_or_name: '',
});

function submitStation() {
  form.post(route('restaurant.stations.store'), {
    onSuccess: () => {
      showModal.value = false;
      form.reset();
    },
  });
}

function deleteStation(station: StationItem) {
  if (confirm(`Remove station ${station.name}?`)) {
    router.delete(route('restaurant.stations.destroy', station.id));
  }
}
</script>

<template>
  <Head title="Kitchen Preparation Stations - Restaurant Vertical" />

  <AdminLayout>
    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-700/60 pb-5">
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
            <span>🔥</span> Kitchen Preparation Stations
          </h1>
          <p class="text-sm text-slate-400 mt-1">
            Configure kitchen stations (BBQ, Tandoor, Main Kitchen, Drinks Bar) and assign dedicated KOT thermal printers.
          </p>
        </div>

        <button
          @click="showModal = true"
          class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm shadow-lg shadow-emerald-900/30 transition"
        >
          + Add Kitchen Station
        </button>
      </div>

      <div v-if="stations.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
        <div
          v-for="st in stations"
          :key="st.id"
          class="bg-slate-900/90 border border-slate-800 rounded-xl p-5 flex flex-col justify-between hover:border-slate-700 transition"
        >
          <div>
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-mono px-2 py-0.5 rounded bg-amber-950 text-amber-400">
                KITCHEN STATION
              </span>
              <button @click="deleteStation(st)" class="text-slate-500 hover:text-rose-400 text-sm">
                &times;
              </button>
            </div>

            <h3 class="text-lg font-bold text-white">{{ st.name }}</h3>

            <div class="text-xs text-slate-400 mt-2 space-y-1">
              <div>
                🖨️ Printer:
                <span class="font-mono text-slate-300">{{ st.printer_ip_or_name || 'Network Default' }}</span>
              </div>
              <div>
                🍲 Assigned Items:
                <span class="font-semibold text-indigo-400">{{ st.products_count }} Products</span>
              </div>
            </div>
          </div>

          <div class="mt-4 pt-3 border-t border-slate-800 flex justify-between items-center text-xs">
            <span class="text-emerald-400">● Active Station</span>
            <a :href="route('restaurant.kds.index', { station_id: st.id })" class="text-indigo-400 hover:underline">
              Open KDS Screen &rarr;
            </a>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-16 bg-slate-900/50 border border-slate-800 rounded-xl">
        <div class="text-4xl mb-3">🍳</div>
        <h3 class="text-lg font-bold text-white">No Kitchen Stations Configured</h3>
        <p class="text-sm text-slate-400 max-w-md mx-auto mt-1 mb-4">
          Create kitchen preparation stations to route food orders automatically to specific kitchen printers or chef display screens.
        </p>
        <button
          @click="showModal = true"
          class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm transition"
        >
          Add First Station
        </button>
      </div>

      <!-- Add Station Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
        <div class="bg-slate-900 border border-slate-700 rounded-xl max-w-md w-full p-6 space-y-4">
          <h3 class="text-lg font-bold text-white">Add Kitchen Station</h3>

          <form @submit.prevent="submitStation" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Branch / Location</label>
              <select v-model="form.location_id" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm">
                <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Station Name *</label>
              <input v-model="form.name" type="text" placeholder="e.g. Grill & BBQ Section, Tandoor, Beverage Bar" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" required />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Printer IP or Network Name</label>
              <input v-model="form.printer_ip_or_name" type="text" placeholder="e.g. 192.168.1.200 or PRINTER-GRILL" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" />
            </div>

            <div class="flex justify-end gap-3 pt-3">
              <button type="button" @click="showModal = false" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-sm">Cancel</button>
              <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm">Create Station</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
