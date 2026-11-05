<template>
  <div class="app-layout">
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="brand-logo" title="PK POS Merchant Portal">PK</div>
        <div class="brand-text">
          <h1>PK POS</h1>
          <span>FBR POS Admin</span>
        </div>
      </div>

      <nav class="sidebar-nav">
        <!-- Platform Super Admin Switcher -->
        <div v-if="user?.is_super_admin" style="margin-bottom: 0.85rem; padding: 0.75rem; background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(6, 182, 212, 0.2)); border: 1px solid rgba(99, 102, 241, 0.4); border-radius: 8px;">
          <div style="font-size: 0.68rem; font-weight: 700; color: #a5b4fc; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem;">
            Platform Control
          </div>
          <Link
            href="/superadmin/dashboard"
            style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.45rem 0.65rem; background: #6366f1; color: #fff; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-decoration: none;"
          >
            <span>Super Admin Console</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
          </Link>
        </div>

        <Link
          href="/dashboard"
          class="nav-link"
          :class="{ active: currentPath === '/dashboard' || currentPath === '/' }"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7"></rect>
            <rect x="14" y="3" width="7" height="7"></rect>
            <rect x="14" y="14" width="7" height="7"></rect>
            <rect x="3" y="14" width="7" height="7"></rect>
          </svg>
          <span>Dashboard</span>
        </Link>

        <Link
          href="/sales"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/sales') }"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <path d="M16 10a4 4 0 0 1-8 0"></path>
          </svg>
          <span>Sales & Invoices</span>
        </Link>

        <Link
          v-if="user?.role === 'owner'"
          href="/fbr-credentials"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/fbr-credentials') }"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <span>FBR Credentials</span>
        </Link>

        <div style="margin: 1.25rem 0 0.35rem 0.75rem; font-size: 0.68rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em;">
          Catalog
        </div>

        <Link
          href="/products"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/products') }"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
            <line x1="12" y1="22.08" x2="12" y2="12"></line>
          </svg>
          <span>Products</span>
        </Link>

        <Link
          href="/tax-rules"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/tax-rules') }"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="19" y1="5" x2="5" y2="19"></line>
            <circle cx="6.5" cy="6.5" r="2.5"></circle>
            <circle cx="17.5" cy="17.5" r="2.5"></circle>
          </svg>
          <span>Tax Rules</span>
        </Link>

        <!-- Stock Section -->
        <template v-if="user?.role === 'owner' || user?.role === 'manager'">
          <div style="margin: 1.25rem 0 0.35rem 0.75rem; font-size: 0.68rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em;">
            Stock & Inventory
          </div>

          <Link
            href="/inventory/levels"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/inventory/levels') || currentPath.startsWith('/inventory/adjust') }"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
              <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
            <span>Stock Levels</span>
          </Link>

          <Link
            href="/inventory/movements"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/inventory/movements') }"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
              <polyline points="17 6 23 6 23 12"></polyline>
            </svg>
            <span>Movements Log</span>
          </Link>
        </template>

        <div style="margin: 1.25rem 0 0.35rem 0.75rem; font-size: 0.68rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em;">
          POS Terminal
        </div>

        <a
          href="/downloads/desktop"
          class="nav-link"
          style="color: #38bdf8; font-weight: 600;"
          title="Download the latest desktop POS application installer"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
          </svg>
          <span>Download POS (.exe)</span>
        </a>
      </nav>

      <div class="sidebar-footer">
        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
          <div class="user-badge">
            <div class="user-avatar">
              {{ user?.name ? user.name.charAt(0).toUpperCase() : 'U' }}
            </div>
            <div class="user-info">
              <span class="user-name">{{ user?.name || 'Authorized User' }}</span>
              <span class="user-role">{{ user?.role || 'Staff' }} • Merchant</span>
            </div>
          </div>
          <Link
            href="/logout"
            method="post"
            as="button"
            class="modal-close"
            title="Log Out"
            style="font-size: 1rem; padding: 0.25rem; display: flex; align-items: center;"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
              <polyline points="16 17 21 12 16 7"></polyline>
              <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
          </Link>
        </div>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="main-content">
      <!-- Impersonation Banner -->
      <div v-if="$page.props.auth?.impersonation?.active" class="impersonation-banner">
        <div class="impersonation-content">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="8.5" cy="7" r="4"></circle>
            <line x1="20" y1="8" x2="20" y2="14"></line>
            <line x1="23" y1="11" x2="17" y2="11"></line>
          </svg>
          <span>
            Impersonating <strong>{{ $page.props.auth.impersonation.tenant_name }}</strong> (Super Admin Session Active)
          </span>
        </div>
        <Link
          href="/superadmin/impersonate/leave"
          class="btn-return-superadmin"
        >
          Return to Super Admin (Esc)
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="9 18 15 12 9 6"></polyline>
          </svg>
        </Link>
      </div>

      <header class="topbar">
        <h2 class="page-title">{{ title }}</h2>
        <div class="topbar-actions" style="display: flex; align-items: center; gap: 0.75rem;">
          <!-- Theme Toggle Button -->
          <button
            class="theme-toggle-btn"
            :title="currentTheme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
            @click="toggleTheme"
          >
            <span v-if="currentTheme === 'dark'">☀️</span>
            <span v-else>🌙</span>
          </button>

          <!-- Platform Super Admin Console Button -->
          <Link
            v-if="user?.is_super_admin"
            href="/superadmin/dashboard"
            style="background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; font-size: 0.78rem; padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none; box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
            Super Admin Console
          </Link>

          <!-- FBR Status Pill -->
          <div class="fbr-gateway-pill" :class="isFbrSandbox ? 'sandbox' : 'live'">
            <span class="pulse-dot"></span>
            <span class="fbr-text">{{ isFbrSandbox ? 'FBR Sandbox Mode' : 'FBR Production Live' }}</span>
          </div>

          <span class="badge badge-submitted">
            <span class="badge-dot"></span>
            Sync Engine Active
          </span>
        </div>
      </header>

      <main class="page-container">
        <!-- Flash notifications -->
        <div v-if="$page.props.flash?.success" class="alert alert-success">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <span>{{ $page.props.flash.success }}</span>
        </div>

        <div v-if="$page.props.flash?.error" class="alert alert-danger">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span>{{ $page.props.flash.error }}</span>
        </div>

        <slot />
      </main>

      <!-- Bottom Telemetry Bar -->
      <footer class="telemetry-bar">
        <div class="telemetry-left">
          <span class="telemetry-chip">
            <span class="clock-icon">🕒</span>
            <span class="clock-time">{{ livePktClock }}</span>
          </span>
          <span class="telemetry-sep">|</span>
          <span class="telemetry-chip">
            <span class="db-dot"></span>
            FBR Ready
          </span>
        </div>

        <div class="telemetry-center">
          <span class="scope-indicator">{{ user?.name || 'Merchant' }} • Tenant Scoped</span>
        </div>

        <div class="telemetry-right">
          <span class="telemetry-chip">POS Client Sync: Online</span>
          <span class="telemetry-sep">|</span>
          <span class="telemetry-version">v2.4.0-fiscal</span>
        </div>
      </footer>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const props = defineProps({
  title: {
    type: String,
    default: 'Dashboard',
  },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const currentPath = computed(() => page.url);
const telemetry = computed(() => page.props.system_telemetry || {});
const isFbrSandbox = computed(() => telemetry.value?.fbr_sandbox_mode ?? true);

const livePktClock = ref('');
let clockTimer = null;

// Theme management
const currentTheme = ref('dark');

function initTheme() {
  if (typeof window !== 'undefined') {
    const saved = localStorage.getItem('pk_pos_theme') || 'dark';
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

function updateClock() {
  const d = new Date();
  livePktClock.value = d.toLocaleTimeString('en-US', {
    timeZone: 'Asia/Karachi',
    hour12: false,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }) + ' PKT';
}

function handleKeyDown(e) {
  // If in impersonation mode and hits Escape, leave impersonation
  if (e.key === 'Escape' && page.props.auth?.impersonation?.active) {
    router.visit('/superadmin/impersonate/leave');
  }
}

onMounted(() => {
  initTheme();
  updateClock();
  clockTimer = setInterval(updateClock, 1000);
  window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer);
  window.removeEventListener('keydown', handleKeyDown);
});
</script>

<style scoped>
.theme-toggle-btn {
  background: var(--bg-tertiary);
  border: 1px solid var(--border-color);
  color: var(--text-primary);
  border-radius: var(--radius-md);
  padding: 0.3rem 0.6rem;
  font-size: 0.95rem;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.theme-toggle-btn:hover {
  background: var(--bg-card-hover);
  border-color: var(--accent-primary);
  transform: translateY(-1px);
}

.fbr-gateway-pill {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.25rem 0.65rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.fbr-gateway-pill.live {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.fbr-gateway-pill.sandbox {
  background: rgba(6, 182, 212, 0.15);
  color: #38bdf8;
  border: 1px solid rgba(6, 182, 212, 0.3);
}

.pulse-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
  box-shadow: 0 0 6px currentColor;
  animation: pulse-glow 2s infinite;
}

@keyframes pulse-glow {
  0% { transform: scale(0.9); opacity: 0.8; }
  50% { transform: scale(1.15); opacity: 1; }
  100% { transform: scale(0.9); opacity: 0.8; }
}

.telemetry-bar {
  height: 28px;
  background: #090e18;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.25rem;
  font-size: 0.72rem;
  color: var(--text-muted);
  flex-shrink: 0;
  z-index: 40;
  user-select: none;
}

.telemetry-left, .telemetry-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.telemetry-center {
  font-weight: 600;
  letter-spacing: 0.03em;
  color: #64748b;
}

.telemetry-chip {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.db-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10b981;
}

.telemetry-sep {
  opacity: 0.3;
}

.clock-time {
  font-family: var(--font-mono);
  color: var(--text-secondary);
  font-weight: 600;
}

.telemetry-version {
  font-family: var(--font-mono);
  color: var(--text-muted);
}
</style>
