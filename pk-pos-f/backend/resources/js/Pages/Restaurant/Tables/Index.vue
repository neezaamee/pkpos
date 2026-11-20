<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface TableItem {
  id: string;
  table_number: string;
  capacity: number;
  status: 'available' | 'occupied' | 'billed' | 'reserved' | 'cleaning';
  shape: 'square' | 'round' | 'rectangle';
  floor_id?: string;
  current_sale_id?: string;
}

interface FloorItem {
  id: string;
  name: string;
  floor_number: number;
  sort_order: number;
  tables: TableItem[];
}

interface LocationItem {
  id: string;
  name: string;
}

const props = defineProps<{
  floors: FloorItem[];
  unassignedTables: TableItem[];
  locations: LocationItem[];
  selectedLocationId?: string;
}>();

const activeFloorId = ref<string>('all');
const showFloorModal = ref(false);
const showTableModal = ref(false);

const floorForm = useForm({
  location_id: props.selectedLocationId || props.locations[0]?.id || '',
  name: '',
  floor_number: 1,
});

const tableForm = useForm({
  location_id: props.selectedLocationId || props.locations[0]?.id || '',
  floor_id: '',
  table_number: '',
  capacity: 4,
  shape: 'square',
});

function submitFloor() {
  floorForm.post(route('restaurant.floors.store'), {
    onSuccess: () => {
      showFloorModal.value = false;
      floorForm.reset();
    },
  });
}

function submitTable() {
  tableForm.post(route('restaurant.tables.store'), {
    onSuccess: () => {
      showTableModal.value = false;
      tableForm.reset();
    },
  });
}

function updateTableStatus(table: TableItem, newStatus: string) {
  router.put(route('restaurant.tables.update', table.id), {
    status: newStatus,
  });
}

function deleteTable(table: TableItem) {
  if (confirm(`Remove Table ${table.table_number}?`)) {
    router.delete(route('restaurant.tables.destroy', table.id));
  }
}

const displayedTables = computed(() => {
  if (activeFloorId.value === 'all') {
    const all: TableItem[] = [];
    props.floors.forEach(f => all.push(...f.tables));
    all.push(...props.unassignedTables);
    return all;
  }
  if (activeFloorId.value === 'unassigned') {
    return props.unassignedTables;
  }
  const floor = props.floors.find(f => f.id === activeFloorId.value);
  return floor ? floor.tables : [];
});

const occupancyStats = computed(() => {
  let total = 0;
  let occupied = 0;
  let available = 0;
  let billed = 0;

  displayedTables.value.forEach(t => {
    total++;
    if (t.status === 'occupied') occupied++;
    else if (t.status === 'billed') billed++;
    else if (t.status === 'available') available++;
  });

  return { total, occupied, available, billed };
});
</script>

<template>
  <Head title="Dining Floors & Tables - Restaurant Vertical" />

  <AdminLayout>
    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-700/60 pb-5">
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
            <span>🍽️</span> Floor Plans & Dining Tables
          </h1>
          <p class="text-sm text-slate-400 mt-1">
            Real-time table occupancy, floor zones, covers, and dine-in management.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <button
            @click="showFloorModal = true"
            class="px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-600 text-sm font-semibold transition"
          >
            + New Floor / Zone
          </button>
          <button
            @click="showTableModal = true"
            class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm shadow-lg shadow-emerald-900/30 transition"
          >
            + Add Table
          </button>
        </div>
      </div>

      <!-- Stats Banner -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4">
          <div class="text-xs uppercase font-semibold text-slate-400">Total Tables</div>
          <div class="text-2xl font-bold text-white mt-1">{{ occupancyStats.total }}</div>
        </div>
        <div class="bg-emerald-950/30 border border-emerald-900/50 rounded-xl p-4">
          <div class="text-xs uppercase font-semibold text-emerald-400">Available Tables</div>
          <div class="text-2xl font-bold text-emerald-400 mt-1">{{ occupancyStats.available }}</div>
        </div>
        <div class="bg-amber-950/30 border border-amber-900/50 rounded-xl p-4">
          <div class="text-xs uppercase font-semibold text-amber-400">Occupied (Dining)</div>
          <div class="text-2xl font-bold text-amber-400 mt-1">{{ occupancyStats.occupied }}</div>
        </div>
        <div class="bg-sky-950/30 border border-sky-900/50 rounded-xl p-4">
          <div class="text-xs uppercase font-semibold text-sky-400">Billed / Awaiting Pay</div>
          <div class="text-2xl font-bold text-sky-400 mt-1">{{ occupancyStats.billed }}</div>
        </div>
      </div>

      <!-- Floor Tabs -->
      <div class="flex items-center gap-2 border-b border-slate-800 pb-3 overflow-x-auto">
        <button
          @click="activeFloorId = 'all'"
          class="px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap"
          :class="activeFloorId === 'all' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white bg-slate-800/60'"
        >
          All Floors ({{ occupancyStats.total }})
        </button>

        <button
          v-for="floor in floors"
          :key="floor.id"
          @click="activeFloorId = floor.id"
          class="px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap"
          :class="activeFloorId === floor.id ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white bg-slate-800/60'"
        >
          {{ floor.name }} ({{ floor.tables.length }})
        </button>

        <button
          v-if="unassignedTables.length > 0"
          @click="activeFloorId = 'unassigned'"
          class="px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap"
          :class="activeFloorId === 'unassigned' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white bg-slate-800/60'"
        >
          Unassigned ({{ unassignedTables.length }})
        </button>
      </div>

      <!-- Table Visual Grid -->
      <div v-if="displayedTables.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <div
          v-for="table in displayedTables"
          :key="table.id"
          class="bg-slate-900/90 border rounded-xl p-4 flex flex-col justify-between transition hover:shadow-lg relative group"
          :class="{
            'border-emerald-600/50 hover:border-emerald-500': table.status === 'available',
            'border-amber-500/60 bg-amber-950/20 hover:border-amber-400': table.status === 'occupied',
            'border-sky-500/60 bg-sky-950/20 hover:border-sky-400': table.status === 'billed',
            'border-purple-500/50 hover:border-purple-400': table.status === 'reserved',
            'border-slate-700': table.status === 'cleaning',
          }"
        >
          <div class="flex items-center justify-between">
            <span class="text-xs font-mono px-2 py-0.5 rounded"
              :class="{
                'bg-emerald-950 text-emerald-400': table.status === 'available',
                'bg-amber-950 text-amber-400': table.status === 'occupied',
                'bg-sky-950 text-sky-400': table.status === 'billed',
                'bg-purple-950 text-purple-400': table.status === 'reserved',
                'bg-slate-800 text-slate-300': table.status === 'cleaning',
              }"
            >
              {{ table.status.toUpperCase() }}
            </span>

            <button
              @click="deleteTable(table)"
              class="opacity-0 group-hover:opacity-100 text-slate-500 hover:text-rose-400 text-xs transition"
              title="Delete table"
            >
              &times;
            </button>
          </div>

          <div class="my-4 text-center">
            <div class="text-2xl font-black text-white font-mono">{{ table.table_number }}</div>
            <div class="text-xs text-slate-400 mt-1 flex items-center justify-center gap-1">
              <span>👥</span> {{ table.capacity }} Covers ({{ table.shape }})
            </div>
          </div>

          <!-- Quick Status Actions -->
          <div class="pt-2 border-t border-slate-800 flex items-center justify-between text-xs">
            <button
              v-if="table.status !== 'available'"
              @click="updateTableStatus(table, 'available')"
              class="text-emerald-400 hover:underline"
            >
              Make Available
            </button>
            <button
              v-else
              @click="updateTableStatus(table, 'occupied')"
              class="text-amber-400 hover:underline"
            >
              Occupy
            </button>

            <button
              v-if="table.status === 'occupied'"
              @click="updateTableStatus(table, 'billed')"
              class="text-sky-400 hover:underline"
            >
              Print Bill
            </button>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-16 bg-slate-900/50 border border-slate-800 rounded-xl">
        <div class="text-4xl mb-3">🪑</div>
        <h3 class="text-lg font-bold text-white">No Dining Tables Configured</h3>
        <p class="text-sm text-slate-400 max-w-md mx-auto mt-1 mb-4">
          Add tables to this floor layout to enable Dine-In table tracking, KOT firing, and bill splitting.
        </p>
        <button
          @click="showTableModal = true"
          class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm transition"
        >
          Add First Table
        </button>
      </div>

      <!-- Add Floor Modal -->
      <div v-if="showFloorModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
        <div class="bg-slate-900 border border-slate-700 rounded-xl max-w-md w-full p-6 space-y-4">
          <h3 class="text-lg font-bold text-white">Add New Floor / Dining Zone</h3>

          <form @submit.prevent="submitFloor" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Branch / Location</label>
              <select v-model="floorForm.location_id" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm">
                <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Floor Name *</label>
              <input v-model="floorForm.name" type="text" placeholder="e.g. Ground Floor, Rooftop Terrace" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" required />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Floor Number</label>
              <input v-model.number="floorForm.floor_number" type="number" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" />
            </div>

            <div class="flex justify-end gap-3 pt-3">
              <button type="button" @click="showFloorModal = false" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-sm">Cancel</button>
              <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm">Create Floor</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Add Table Modal -->
      <div v-if="showTableModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
        <div class="bg-slate-900 border border-slate-700 rounded-xl max-w-md w-full p-6 space-y-4">
          <h3 class="text-lg font-bold text-white">Add New Dining Table</h3>

          <form @submit.prevent="submitTable" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Floor / Zone</label>
              <select v-model="tableForm.floor_id" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm">
                <option value="">Unassigned</option>
                <option v-for="fl in floors" :key="fl.id" :value="fl.id">{{ fl.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Table Number / Code *</label>
              <input v-model="tableForm.table_number" type="text" placeholder="e.g. T-01, Rooftop-05" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" required />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Capacity (Seats) *</label>
                <input v-model.number="tableForm.capacity" type="number" min="1" max="50" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Shape</label>
                <select v-model="tableForm.shape" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm">
                  <option value="square">Square</option>
                  <option value="round">Round</option>
                  <option value="rectangle">Rectangle</option>
                </select>
              </div>
            </div>

            <div class="flex justify-end gap-3 pt-3">
              <button type="button" @click="showTableModal = false" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-sm">Cancel</button>
              <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm">Add Table</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
