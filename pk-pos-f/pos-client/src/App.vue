<script setup lang="ts">
import { ref } from 'vue';
import HeaderNav from './components/HeaderNav.vue';
import CheckoutView from './views/CheckoutView.vue';
import SaleHistoryView from './views/SaleHistoryView.vue';
import SettingsView from './views/SettingsView.vue';

const currentTab = ref<'checkout' | 'history' | 'settings'>('checkout');

function handleNavigate(tab: 'checkout' | 'history' | 'settings') {
  currentTab.value = tab;
}
</script>

<template>
  <div class="pos-app-root">
    <HeaderNav :active-tab="currentTab" @navigate="handleNavigate" />

    <main class="main-content-viewport">
      <CheckoutView v-show="currentTab === 'checkout'" />
      <SaleHistoryView v-if="currentTab === 'history'" />
      <SettingsView v-if="currentTab === 'settings'" />
    </main>
  </div>
</template>

<style>
:root {
  color-scheme: dark;
  --bg-app: #0f172a;
  --bg-surface: #1e293b;
  --bg-card: #1e293b;
  --bg-hover: #334155;
  --text-main: #f8fafc;
  --text-sub: #94a3b8;
  --text-muted: #64748b;
  --border-color: #334155;
}

[data-theme="light"] {
  color-scheme: light;
  --bg-app: #f1f5f9;
  --bg-surface: #ffffff;
  --bg-card: #ffffff;
  --bg-hover: #e2e8f0;
  --text-main: #0f172a;
  --text-sub: #334155;
  --text-muted: #64748b;
  --border-color: #cbd5e1;
}

body {
  margin: 0;
  padding: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  background-color: #0f172a;
  color: #f8fafc;
  overflow: hidden;
  user-select: none;
  transition: background-color 0.15s ease, color 0.15s ease;
}

[data-theme="light"] body {
  background-color: #f1f5f9;
  color: #0f172a;
}

[data-theme="light"] .header-nav {
  background: #ffffff !important;
  border-bottom: 1px solid #e2e8f0 !important;
  color: #0f172a !important;
}

[data-theme="light"] .tab-btn {
  color: #475569 !important;
}

[data-theme="light"] .tab-btn:hover {
  color: #0f172a !important;
  background: #f1f5f9 !important;
}

[data-theme="light"] .tab-btn.active {
  color: #ffffff !important;
  background: #2563eb !important;
}

[data-theme="light"] .sync-btn,
[data-theme="light"] .theme-toggle-btn {
  background: #f1f5f9 !important;
  border-color: #cbd5e1 !important;
  color: #1e293b !important;
}

[data-theme="light"] .sync-btn:hover,
[data-theme="light"] .theme-toggle-btn:hover {
  background: #e2e8f0 !important;
}

[data-theme="light"] .products-column,
[data-theme="light"] .cart-column,
[data-theme="light"] .calc-column,
[data-theme="light"] .product-card,
[data-theme="light"] .cart-item,
[data-theme="light"] .totals-card,
[data-theme="light"] .cash-workflow,
[data-theme="light"] .history-table,
[data-theme="light"] .settings-card {
  background-color: #ffffff !important;
  border-color: #e2e8f0 !important;
  color: #0f172a !important;
}

[data-theme="light"] .search-input,
[data-theme="light"] .tender-input,
[data-theme="light"] .qty-input,
[data-theme="light"] input,
[data-theme="light"] select {
  background-color: #ffffff !important;
  color: #0f172a !important;
  border-color: #cbd5e1 !important;
}

[data-theme="light"] .product-name,
[data-theme="light"] .cart-title,
[data-theme="light"] .calc-title,
[data-theme="light"] .grand-val,
[data-theme="light"] .item-name {
  color: #0f172a !important;
}

[data-theme="light"] .product-price,
[data-theme="light"] .product-stock,
[data-theme="light"] .item-unit-price {
  color: #475569 !important;
}

* {
  box-sizing: border-box;
}

/* Custom modern scrollbars */
::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}

::-webkit-scrollbar-track {
  background: #0f172a;
}

::-webkit-scrollbar-thumb {
  background: #334155;
  border-radius: 9999px;
}

::-webkit-scrollbar-thumb:hover {
  background: #475569;
}
</style>

<style scoped>
.pos-app-root {
  display: flex;
  flex-direction: column;
  height: 100vh;
  width: 100vw;
  overflow: hidden;
}

.main-content-viewport {
  flex: 1;
  overflow: hidden;
  position: relative;
}
</style>
