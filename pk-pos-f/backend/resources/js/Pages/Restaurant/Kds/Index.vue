<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface KdsItem {
  id: string;
  product_name: string;
  quantity: number;
  selected_modifiers?: Array<{ group: string; option: string; price_minor?: number }>;
  cooking_notes?: string;
  status: 'pending' | 'preparing' | 'ready' | 'served' | 'cancelled';
}

interface KdsOrder {
  id: string;
  kot_number: string;
  sale_id: string;
  station_name: string;
  order_type: string;
  table_number?: string;
  server_name?: string;
  status: 'pending' | 'preparing' | 'ready' | 'served' | 'cancelled';
  fired_at: string;
  elapsed_minutes: number;
  urgency: 'normal' | 'warning' | 'critical';
  items: KdsItem[];
}

interface StationItem {
  id: string;
  name: string;
}

const props = defineProps<{
  initialOrders: KdsOrder[];
  stations: StationItem[];
  selectedStationId?: string;
  locationId?: string;
}>();

const orders = ref<KdsOrder[]>([...props.initialOrders]);
const activeStationId = ref<string>(props.selectedStationId || 'all');
const isPolling = ref(true);
let pollTimer: any = null;

async function fetchOrders() {
  try {
    const params = new URLSearchParams();
    if (activeStationId.value !== 'all') {
      params.append('station_id', activeStationId.value);
    }
    if (props.locationId) {
      params.append('location_id', props.locationId);
    }

    const res = await fetch(`/api/v1/pos/restaurant/kds/orders?${params.toString()}`);
    if (res.ok) {
      orders.value = await res.json();
    }
  } catch (err) {
    console.warn('Failed to poll KDS orders:', err);
  }
}

async function bumpOrder(order: KdsOrder, targetStatus?: string) {
  try {
    const res = await fetch(`/api/v1/pos/restaurant/kds/orders/${order.id}/bump`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ status: targetStatus }),
    });

    if (res.ok) {
      await fetchOrders();
    }
  } catch (err) {
    console.error('Failed to bump order:', err);
  }
}

function selectStation(stationId: string) {
  activeStationId.value = stationId;
  fetchOrders();
}

onMounted(() => {
  pollTimer = setInterval(() => {
    if (isPolling.value) {
      fetchOrders();
    }
  }, 10000); // 10s auto-refresh
});

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
  <Head title="Kitchen Display System (KDS) - Live Chef Screen" />

  <AdminLayout>
    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-700/60 pb-5">
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
            <span>📺</span> Kitchen Display System (KDS)
          </h1>
          <p class="text-sm text-slate-400 mt-1">
            Real-time chef screen with preparation timers, bump bar statuses, and table/server dispatch.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <div class="flex items-center gap-2 bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-700 text-xs">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <span class="text-slate-300">Live KDS Sync (10s)</span>
          </div>

          <button
            @click="fetchOrders"
            class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-600 text-xs font-semibold"
          >
            Refresh Now
          </button>
        </div>
      </div>

      <!-- Station Filter Tabs -->
      <div class="flex items-center gap-2 border-b border-slate-800 pb-3 overflow-x-auto">
        <button
          @click="selectStation('all')"
          class="px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap"
          :class="activeStationId === 'all' ? 'bg-amber-600 text-white' : 'text-slate-400 hover:text-white bg-slate-800/60'"
        >
          All Stations ({{ orders.length }})
        </button>

        <button
          v-for="st in stations"
          :key="st.id"
          @click="selectStation(st.id)"
          class="px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap"
          :class="activeStationId === st.id ? 'bg-amber-600 text-white' : 'text-slate-400 hover:text-white bg-slate-800/60'"
        >
          {{ st.name }}
        </button>
      </div>

      <!-- Live KOT Cards Grid -->
      <div v-if="orders.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div
          v-for="order in orders"
          :key="order.id"
          class="bg-slate-900 border rounded-xl overflow-hidden flex flex-col justify-between shadow-lg transition"
          :class="{
            'border-emerald-600/60': order.urgency === 'normal',
            'border-amber-500/80 shadow-amber-950/20': order.urgency === 'warning',
            'border-rose-600 shadow-rose-950/30': order.urgency === 'critical',
          }"
        >
          <!-- Card Top Bar -->
          <div
            class="px-4 py-2.5 flex items-center justify-between text-xs font-semibold"
            :class="{
              'bg-emerald-950/60 text-emerald-300': order.urgency === 'normal',
              'bg-amber-950/60 text-amber-300': order.urgency === 'warning',
              'bg-rose-950/80 text-rose-200 animate-pulse': order.urgency === 'critical',
            }"
          >
            <span class="font-mono">{{ order.kot_number }}</span>
            <span class="flex items-center gap-1 font-mono">
              ⏱️ {{ order.elapsed_minutes }} min ago
            </span>
          </div>

          <!-- Order Info -->
          <div class="p-4 space-y-3 flex-1">
            <div class="flex items-center justify-between text-xs pb-2 border-b border-slate-800">
              <span class="font-bold text-white uppercase">
                {{ order.order_type === 'dine_in' ? `🍽️ Table ${order.table_number || 'N/A'}` : (order.order_type === 'takeaway' ? '🥡 Takeaway' : '🛵 Delivery') }}
              </span>
              <span class="text-slate-400 font-mono">
                Server: {{ order.server_name || 'Front Counter' }}
              </span>
            </div>

            <!-- Items List -->
            <div class="space-y-2">
              <div
                v-for="item in order.items"
                :key="item.id"
                class="bg-slate-800/40 border border-slate-800 rounded-lg p-2.5 text-xs"
              >
                <div class="flex items-start justify-between">
                  <span class="font-bold text-white text-sm">
                    {{ item.quantity }}x {{ item.product_name }}
                  </span>
                  <span
                    class="font-mono text-[10px] px-1.5 py-0.5 rounded uppercase font-semibold"
                    :class="{
                      'bg-slate-700 text-slate-300': item.status === 'pending',
                      'bg-amber-950 text-amber-300': item.status === 'preparing',
                      'bg-emerald-950 text-emerald-300': item.status === 'ready',
                    }"
                  >
                    {{ item.status }}
                  </span>
                </div>

                <!-- Modifiers & Add-ons -->
                <div v-if="item.selected_modifiers && item.selected_modifiers.length > 0" class="mt-1 space-y-0.5">
                  <div
                    v-for="(mod, mIdx) in item.selected_modifiers"
                    :key="mIdx"
                    class="text-[11px] text-amber-400 font-medium"
                  >
                    + {{ mod.group }}: {{ mod.option }}
                  </div>
                </div>

                <!-- Cooking Notes / Dietary Instructions -->
                <div v-if="item.cooking_notes" class="mt-1 text-[11px] text-rose-300 italic font-semibold">
                  ⚠️ Note: {{ item.cooking_notes }}
                </div>
              </div>
            </div>
          </div>

          <!-- Card Bottom Bump Actions -->
          <div class="p-3 bg-slate-950 border-t border-slate-800 flex gap-2">
            <button
              v-if="order.status === 'pending'"
              @click="bumpOrder(order, 'preparing')"
              class="w-full py-2 rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs uppercase tracking-wider transition"
            >
              Start Preparing
            </button>

            <button
              v-else-if="order.status === 'preparing'"
              @click="bumpOrder(order, 'ready')"
              class="w-full py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase tracking-wider transition"
            >
              Mark Ready
            </button>

            <button
              v-else-if="order.status === 'ready'"
              @click="bumpOrder(order, 'served')"
              class="w-full py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase tracking-wider transition"
            >
              Mark Served
            </button>

            <button
              @click="bumpOrder(order, 'cancelled')"
              class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-rose-400 text-xs transition"
              title="Cancel Order"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-20 bg-slate-900/40 border border-slate-800 rounded-xl">
        <div class="text-4xl mb-3">👨‍🍳</div>
        <h3 class="text-lg font-bold text-white">All Kitchen Orders Cleared!</h3>
        <p class="text-sm text-slate-400 max-w-sm mx-auto mt-1">
          No pending or preparing kitchen tickets right now. When cashiers ring Dine-In or Takeaway orders, tickets will appear here automatically.
        </p>
      </div>
    </div>
  </AdminLayout>
</template>
