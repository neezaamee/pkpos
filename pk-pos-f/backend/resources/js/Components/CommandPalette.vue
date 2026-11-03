<template>
  <Teleport to="body">
    <div v-if="isOpen" class="palette-overlay" @click.self="close">
      <div class="palette-modal" role="dialog" aria-modal="true">
        <!-- Search Input Header -->
        <div class="palette-search-header">
          <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input
            ref="inputRef"
            v-model="query"
            type="text"
            class="palette-input"
            placeholder="Type a command, page, or search module... (Esc to exit)"
            @keydown="handleKeydown"
          />
          <span class="esc-badge" @click="close">ESC</span>
        </div>

        <!-- Search Results List -->
        <div class="palette-results">
          <div v-if="filteredItems.length === 0" class="palette-empty">
            <p>No matching commands or pages found for "{{ query }}"</p>
          </div>

          <div v-for="(group, gIdx) in groupedFilteredItems" :key="gIdx" class="palette-group">
            <div class="palette-group-title">{{ group.category }}</div>
            <div
              v-for="item in group.items"
              :key="item.href"
              class="palette-item"
              :class="{ selected: selectedIndex === item.flatIndex }"
              @click="navigate(item.href)"
              @mouseenter="selectedIndex = item.flatIndex"
            >
              <div class="item-icon-box">
                <component :is="item.icon" v-if="typeof item.icon === 'object'" />
                <span v-else class="text-icon">#</span>
              </div>
              <div class="item-text-box">
                <span class="item-title">{{ item.title }}</span>
                <span class="item-desc">{{ item.description }}</span>
              </div>
              <span v-if="item.badge" class="item-badge" :style="{ backgroundColor: item.badgeColor || '#ec4899' }">
                {{ item.badge }}
              </span>
              <span class="item-jump">Jump ↵</span>
            </div>
          </div>
        </div>

        <!-- Palette Footer -->
        <div class="palette-footer">
          <div class="footer-hint">
            <kbd>↑</kbd> <kbd>↓</kbd> to navigate
          </div>
          <div class="footer-hint">
            <kbd>Enter</kbd> to select
          </div>
          <div class="footer-hint">
            <kbd>Esc</kbd> to dismiss
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  badges: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['update:isOpen', 'close']);

const query = ref('');
const selectedIndex = ref(0);
const inputRef = ref(null);

const items = computed(() => [
  // Core Operations
  { category: 'Core Operations', title: 'Dashboard & Global Metrics', description: 'Cross-tenant KPI summary & active counts', href: '/superadmin/dashboard' },
  { category: 'Core Operations', title: 'All Tenants & Branches', description: 'Merchant directory, subscriptions & suspension controls', href: '/superadmin/tenants' },
  { category: 'Core Operations', title: 'Onboarding Applications Queue', description: 'FBR ATL verifications and tenant approvals', href: '/superadmin/onboarding', badge: props.badges?.pending_onboarding ? `${props.badges.pending_onboarding} Pending` : null, badgeColor: '#f59e0b' },
  { category: 'Core Operations', title: 'Create New Merchant Tenant', description: 'Manual onboarding wizard with location & owner', href: '/superadmin/tenants/create' },

  // Hardware & Fleet
  { category: 'Hardware & Fleet', title: 'POS Terminal Fleet Management', description: 'Registered POS machines, heartbeats & pairing keys', href: '/superadmin/terminals', badge: props.badges?.offline_terminals ? `${props.badges.offline_terminals} Offline` : null, badgeColor: '#8b5cf6' },
  { category: 'Hardware & Fleet', title: 'Tauri OTA Releases & Deployments', description: 'Windows/Linux binary updater packages & release channels', href: '/superadmin/releases' },

  // SaaS & Commercials
  { category: 'SaaS & Commercials', title: 'Pricing Tiers & Quota Limits', description: 'Monthly/annual plans, terminal & user limits', href: '/superadmin/plans' },
  { category: 'SaaS & Commercials', title: 'Platform Invoices & 1Bill Billing', description: 'SaaS fee collections, bank wire IBFT slips', href: '/superadmin/billing' },

  // Fiscal & Compliance
  { category: 'Fiscal & Compliance', title: 'FBR Command Center', description: 'IRIS gateway status, provincial compliance metrics', href: '/superadmin/compliance/fbr' },
  { category: 'Fiscal & Compliance', title: 'Failed Invoices Dead Letter Queue (DLQ)', description: 'Retry failed fiscal submissions and error inspection', href: '/superadmin/compliance/fbr/failed', badge: props.badges?.dlq_count ? `${props.badges.dlq_count} Failed` : null, badgeColor: '#ef4444' },
  { category: 'Fiscal & Compliance', title: '24h Provisional Tracker', description: 'Sales Tax Act Section 23 24-hour compliance tracker', href: '/superadmin/compliance/fbr/provisional' },

  // Master Data
  { category: 'Master Data', title: 'Master HS / PCT Codes Catalog', description: 'FBR 8-digit tariff codes, search & bulk import', href: '/superadmin/master-data/hs-codes' },
  { category: 'Master Data', title: 'Provincial Tax Templates', description: 'Standard tax schedules (FBR, PRA, SRB, KPRA, BRA)', href: '/superadmin/master-data/tax-templates' },

  // Support & Helpdesk
  { category: 'Support & Helpdesk', title: 'Support & Helpdesk Hub', description: 'Merchant tickets with machine diagnostic logs', href: '/superadmin/support/tickets', badge: props.badges?.open_tickets ? `${props.badges.open_tickets} Open` : null, badgeColor: '#3b82f6' },
  { category: 'Support & Helpdesk', title: 'Knowledge Base Articles', description: 'Merchant guides, FBR FAQ and video tutorials', href: '/superadmin/support/knowledge-base' },

  // Communication
  { category: 'Communication', title: 'Broadcast Announcements & Alerts', description: 'Global system banners & critical POS alert dispatch', href: '/superadmin/broadcasts' },

  // System & Telemetry
  { category: 'System & Telemetry', title: 'System Health & Metrics', description: 'PostgreSQL connection pools, CPU, RAM and disk usage', href: '/superadmin/system/health' },
  { category: 'System & Telemetry', title: 'Queue Workers & Horizon', description: 'Background queue monitoring and retry failed jobs', href: '/superadmin/system/jobs', badge: props.badges?.failed_jobs ? `${props.badges.failed_jobs} Failed` : null, badgeColor: '#ef4444' },
  { category: 'System & Telemetry', title: 'Backups & 6-Year Fiscal Archive', description: 'Automated database snapshots and Section 22 retention', href: '/superadmin/backups' },

  // Security & Access
  { category: 'Security & Access', title: 'Staff Directory & Access', description: 'Super Admin and operations personnel accounts', href: '/superadmin/staff' },
  { category: 'Security & Access', title: 'Roles & Granular Permissions', description: 'RBAC definitions and custom access matrices', href: '/superadmin/roles' },
  { category: 'Security & Access', title: 'System-wide Audit Logs', description: 'Immutable activity trail with actor and IP addresses', href: '/superadmin/audit-logs' },
  { category: 'Security & Access', title: 'Security Dashboard & Killswitch', description: 'Active sessions, 2FA enforcement and emergency lockdown', href: '/superadmin/security' },
]);

const filteredItems = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return items.value;
  return items.value.filter(
    (item) =>
      item.title.toLowerCase().includes(q) ||
      item.description.toLowerCase().includes(q) ||
      item.category.toLowerCase().includes(q)
  );
});

const groupedFilteredItems = computed(() => {
  const groups = {};
  let flatIndex = 0;

  filteredItems.value.forEach((item) => {
    if (!groups[item.category]) {
      groups[item.category] = { category: item.category, items: [] };
    }
    groups[item.category].items.push({
      ...item,
      flatIndex: flatIndex++,
    });
  });

  return Object.values(groups);
});

watch(
  () => props.isOpen,
  (val) => {
    if (val) {
      query.value = '';
      selectedIndex.value = 0;
      nextTick(() => {
        inputRef.value?.focus();
      });
    }
  }
);

function close() {
  emit('update:isOpen', false);
  emit('close');
}

function navigate(href) {
  close();
  router.visit(href);
}

function handleKeydown(e) {
  const total = filteredItems.value.length;
  if (total === 0) return;

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    selectedIndex.value = (selectedIndex.value + 1) % total;
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    selectedIndex.value = (selectedIndex.value - 1 + total) % total;
  } else if (e.key === 'Enter') {
    e.preventDefault();
    const item = filteredItems.value[selectedIndex.value];
    if (item) {
      navigate(item.href);
    }
  } else if (e.key === 'Escape') {
    e.preventDefault();
    close();
  }
}

// Global shortcut Ctrl+K / Cmd+K listener
function handleGlobalKeydown(e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault();
    emit('update:isOpen', !props.isOpen);
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<style scoped>
.palette-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.75);
  backdrop-filter: blur(8px);
  z-index: 9999;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 10vh;
}

.palette-modal {
  width: 100%;
  max-width: 640px;
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 1rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.05);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  max-height: 75vh;
}

.palette-search-header {
  display: flex;
  align-items: center;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #334155;
  gap: 0.75rem;
  background: #0f172a;
}

.search-icon {
  color: #94a3b8;
  flex-shrink: 0;
}

.palette-input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  color: #ffffff;
  font-size: 1.05rem;
  font-weight: 500;
}

.palette-input::placeholder {
  color: #64748b;
}

.esc-badge {
  background: #1e293b;
  border: 1px solid #475569;
  color: #94a3b8;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.2rem 0.5rem;
  border-radius: 0.375rem;
  cursor: pointer;
}

.esc-badge:hover {
  color: #ffffff;
  border-color: #64748b;
}

.palette-results {
  flex: 1;
  overflow-y: auto;
  padding: 0.75rem 0.5rem;
}

.palette-empty {
  padding: 3rem 1.5rem;
  text-align: center;
  color: #64748b;
  font-size: 0.95rem;
}

.palette-group {
  margin-bottom: 0.75rem;
}

.palette-group-title {
  padding: 0.35rem 0.75rem;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #94a3b8;
}

.palette-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 0.75rem;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.1s ease;
  user-select: none;
}

.palette-item.selected {
  background: #334155;
}

.item-icon-box {
  width: 28px;
  height: 28px;
  border-radius: 0.375rem;
  background: rgba(236, 72, 153, 0.15);
  color: #ec4899;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  font-weight: 700;
}

.item-text-box {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.item-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: #f1f5f9;
}

.item-desc {
  font-size: 0.75rem;
  color: #94a3b8;
}

.item-badge {
  font-size: 0.68rem;
  font-weight: 700;
  color: #ffffff;
  padding: 0.15rem 0.45rem;
  border-radius: 9999px;
}

.item-jump {
  font-size: 0.72rem;
  color: #64748b;
  font-weight: 600;
}

.palette-item.selected .item-jump {
  color: #38bdf8;
}

.palette-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 1.25rem;
  padding: 0.65rem 1.25rem;
  background: #0f172a;
  border-top: 1px solid #334155;
  font-size: 0.75rem;
  color: #64748b;
}

.footer-hint {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

kbd {
  background: #1e293b;
  border: 1px solid #475569;
  border-radius: 0.25rem;
  padding: 0.1rem 0.35rem;
  color: #cbd5e1;
  font-size: 0.7rem;
  font-family: inherit;
}
</style>

