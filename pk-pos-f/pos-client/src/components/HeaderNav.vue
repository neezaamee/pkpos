<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { getPendingOutboxCount, syncCatalog } from '../services/posService';

defineProps<{
  activeTab: 'checkout' | 'history' | 'settings';
}>();

const emit = defineEmits<{
  (e: 'navigate', tab: 'checkout' | 'history' | 'settings'): void;
}>();

const pendingOutbox = ref<number>(0);
const isSyncing = ref<boolean>(false);
const isOnline = ref<boolean>(navigator.onLine);
const syncFeedback = ref<string | null>(null);

// Theme management
const currentTheme = ref<'dark' | 'light'>('dark');

function initTheme() {
  if (typeof window !== 'undefined') {
    const saved = (localStorage.getItem('pk_pos_theme') as 'dark' | 'light') || 'dark';
    currentTheme.value = saved;
    document.documentElement.setAttribute('data-theme', saved);
  }
}

function toggleTheme() {
  const next = currentTheme.value === 'dark' ? 'light' : 'dark';
  currentTheme.value = next;
  if (typeof window !== 'undefined') {
    localStorage.setItem('pk_pos_theme', next);
    document.documentElement.setAttribute('data-theme', next);
  }
}

let pollTimer: number | null = null;

async function refreshPendingCount() {
  try {
    pendingOutbox.value = await getPendingOutboxCount();
  } catch (e) {
    // ignore in background
  }
}

async function triggerCatalogSync() {
  if (isSyncing.value) return;
  isSyncing.value = true;
  syncFeedback.value = null;
  try {
    const res = await syncCatalog();
    syncFeedback.value = res.message;
    setTimeout(() => {
      syncFeedback.value = null;
    }, 4000);
  } catch (err: any) {
    syncFeedback.value = 'Sync failed: ' + (err?.message || String(err));
  } finally {
    isSyncing.value = false;
    await refreshPendingCount();
  }
}

function handleOnline() {
  isOnline.value = true;
}

function handleOffline() {
  isOnline.value = false;
}

function handleKeydown(e: KeyboardEvent) {
  if ((e.altKey || e.ctrlKey) && (e.key === '1' || e.key === 'c' || e.key === 'C')) {
    e.preventDefault();
    emit('navigate', 'checkout');
  } else if ((e.altKey || e.ctrlKey) && (e.key === '2' || e.key === 'h' || e.key === 'H')) {
    e.preventDefault();
    emit('navigate', 'history');
  } else if ((e.altKey || e.ctrlKey) && (e.key === '3' || e.key === 's' || e.key === 'S')) {
    e.preventDefault();
    emit('navigate', 'settings');
  }
}

onMounted(() => {
  initTheme();
  refreshPendingCount();
  pollTimer = window.setInterval(refreshPendingCount, 5000);
  window.addEventListener('online', handleOnline);
  window.addEventListener('offline', handleOffline);
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
  window.removeEventListener('online', handleOnline);
  window.removeEventListener('offline', handleOffline);
  window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
  <header class="header-nav">
    <div class="brand-section">
      <div class="brand-logo">
        <span class="brand-accent">PK</span>POS
      </div>
      <span class="station-badge">POS-001</span>
      <div class="network-badge" :class="isOnline ? 'online' : 'offline'">
        <span class="dot"></span>
        {{ isOnline ? 'Online' : 'Offline Mode' }}
      </div>
    </div>

    <nav class="nav-tabs">
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'checkout' }"
        @click="emit('navigate', 'checkout')"
      >
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
        </svg>
        Checkout <span class="shortcut">Alt 1</span>
      </button>

      <button
        class="tab-btn"
        :class="{ active: activeTab === 'history' }"
        @click="emit('navigate', 'history')"
      >
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Sale History <span class="shortcut">Alt 2</span>
      </button>

      <button
        class="tab-btn"
        :class="{ active: activeTab === 'settings' }"
        @click="emit('navigate', 'settings')"
      >
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        Settings <span class="shortcut">Alt 3</span>
      </button>
    </nav>

    <div class="actions-section">
      <!-- Theme Toggle Button -->
      <button
        class="theme-toggle-btn"
        :title="currentTheme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
        @click="toggleTheme"
      >
        <span v-if="currentTheme === 'dark'">☀️</span>
        <span v-else>🌙</span>
      </button>

      <div v-if="syncFeedback" class="sync-toast">
        {{ syncFeedback }}
      </div>

      <div class="outbox-indicator" title="Pending sales waiting to sync to Laravel">
        <span class="outbox-label">Outbox</span>
        <span class="outbox-badge" :class="{ pending: pendingOutbox > 0 }">
          {{ pendingOutbox }}
        </span>
      </div>

      <button
        class="sync-btn"
        :disabled="isSyncing"
        @click="triggerCatalogSync"
        title="Sync product catalog and tax rules"
      >
        <svg
          class="icon"
          :class="{ spin: isSyncing }"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        {{ isSyncing ? 'Syncing...' : 'Sync Catalog' }}
      </button>
    </div>
  </header>
</template>

<style scoped>
.header-nav {
  height: 64px;
  background: #111827;
  border-bottom: 1px solid #1f2937;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.25rem;
  color: #f3f4f6;
  user-select: none;
}

.brand-section {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.brand-logo {
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: -0.025em;
  color: #ffffff;
}

.brand-accent {
  color: #10b981;
}

.station-badge {
  background: #1f2937;
  border: 1px solid #374151;
  color: #9ca3af;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.2rem 0.5rem;
  border-radius: 0.375rem;
}

.network-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.6rem;
  border-radius: 9999px;
}

.network-badge.online {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.network-badge.offline {
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.nav-tabs {
  display: flex;
  gap: 0.5rem;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: transparent;
  color: #9ca3af;
  border: 1px solid transparent;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.tab-btn:hover {
  color: #f3f4f6;
  background: #1f2937;
}

.tab-btn.active {
  color: #ffffff;
  background: #1e3a8a;
  border-color: #2563eb;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}

.shortcut {
  font-size: 0.7rem;
  background: rgba(0, 0, 0, 0.3);
  padding: 0.1rem 0.35rem;
  border-radius: 0.25rem;
  color: #93c5fd;
}

.actions-section {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.sync-toast {
  font-size: 0.75rem;
  color: #34d399;
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.2);
  padding: 0.25rem 0.6rem;
  border-radius: 0.375rem;
}

.theme-toggle-btn {
  background: #1f2937;
  border: 1px solid #374151;
  color: #e5e7eb;
  padding: 0.35rem 0.65rem;
  border-radius: 0.5rem;
  font-size: 0.95rem;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.theme-toggle-btn:hover {
  background: #374151;
  border-color: #60a5fa;
  transform: translateY(-1px);
}

.outbox-indicator {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.75rem;
  color: #9ca3af;
}

.outbox-badge {
  background: #374151;
  color: #d1d5db;
  font-weight: 700;
  padding: 0.15rem 0.5rem;
  border-radius: 9999px;
  min-width: 1.25rem;
  text-align: center;
}

.outbox-badge.pending {
  background: #d97706;
  color: #ffffff;
  box-shadow: 0 0 8px rgba(217, 119, 6, 0.5);
}

.sync-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: #1f2937;
  color: #e5e7eb;
  border: 1px solid #374151;
  padding: 0.45rem 0.85rem;
  border-radius: 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.sync-btn:hover:not(:disabled) {
  background: #374151;
  color: #ffffff;
}

.sync-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.icon {
  width: 1rem;
  height: 1rem;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
