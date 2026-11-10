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

html, body {
  margin: 0;
  padding: 0;
  width: 100%;
  height: 100%;
  overflow: hidden;
  -webkit-tap-highlight-color: transparent;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  background-color: #0f172a;
  color: #f8fafc;
  user-select: none;
  transition: background-color 0.15s ease, color 0.15s ease;
}

button, [role="button"], .product-card, .variant-option-card {
  touch-action: manipulation;
}

input, textarea, select {
  user-select: text;
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

[data-theme="light"] .brand-logo {
  color: #0f172a !important;
}

[data-theme="light"] .station-badge {
  background: #f1f5f9 !important;
  border-color: #cbd5e1 !important;
  color: #475569 !important;
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

[data-theme="light"] .checkout-container,
[data-theme="light"] .history-container,
[data-theme="light"] .settings-container,
[data-theme="light"] .col-catalog,
[data-theme="light"] .col-settlement {
  background-color: #f1f5f9 !important;
  color: #0f172a !important;
}

[data-theme="light"] .col-cart {
  background-color: #f8fafc !important;
  border-color: #e2e8f0 !important;
}

[data-theme="light"] .col-catalog {
  border-color: #e2e8f0 !important;
}

[data-theme="light"] .shortcuts-command-bar,
[data-theme="light"] .search-box,
[data-theme="light"] .cart-header {
  background-color: #ffffff !important;
  border-color: #e2e8f0 !important;
}

[data-theme="light"] .s-pill {
  background-color: #f8fafc !important;
  border-color: #cbd5e1 !important;
  color: #334155 !important;
}

[data-theme="light"] .products-column,
[data-theme="light"] .cart-column,
[data-theme="light"] .calc-column,
[data-theme="light"] .product-card,
[data-theme="light"] .cart-item,
[data-theme="light"] .cart-item-row,
[data-theme="light"] .cart-totals,
[data-theme="light"] .totals-card,
[data-theme="light"] .cash-workflow,
[data-theme="light"] .non-cash-workflow,
[data-theme="light"] .change-display,
[data-theme="light"] .history-table,
[data-theme="light"] .table-container,
[data-theme="light"] .sale-card-mobile,
[data-theme="light"] .stat-pill,
[data-theme="light"] .settings-card,
[data-theme="light"] .modal-card,
[data-theme="light"] .mobile-sticky-bar,
[data-theme="light"] .mobile-bottom-tabs {
  background-color: #ffffff !important;
  border-color: #e2e8f0 !important;
  color: #0f172a !important;
}

[data-theme="light"] .pairing-card {
  background: #ffffff !important;
  border-color: #6366f1 !important;
}

[data-theme="light"] .pairing-card-title {
  color: #4f46e5 !important;
}

[data-theme="light"] .version-box {
  background: #f8fafc !important;
  border-color: #cbd5e1 !important;
}

[data-theme="light"] .version-val:not(.version-latest) {
  color: #0f172a !important;
}

[data-theme="light"] .sales-table th {
  background-color: #f8fafc !important;
  color: #475569 !important;
  border-color: #e2e8f0 !important;
}

[data-theme="light"] .sales-table td {
  border-color: #e2e8f0 !important;
  color: #1e293b !important;
}

[data-theme="light"] .search-input,
[data-theme="light"] .tender-input,
[data-theme="light"] .qty-input,
[data-theme="light"] .pos-input,
[data-theme="light"] .form-input,
[data-theme="light"] .form-select,
[data-theme="light"] input,
[data-theme="light"] select {
  background-color: #ffffff !important;
  color: #0f172a !important;
  border-color: #cbd5e1 !important;
}

[data-theme="light"] .product-name,
[data-theme="light"] .cart-title,
[data-theme="light"] .calc-title,
[data-theme="light"] .settlement-title,
[data-theme="light"] .history-title,
[data-theme="light"] .settings-title,
[data-theme="light"] .card-title,
[data-theme="light"] .modal-title,
[data-theme="light"] .grand-val,
[data-theme="light"] .item-name,
[data-theme="light"] .line-price,
[data-theme="light"] .pill-val {
  color: #0f172a !important;
}

[data-theme="light"] .product-price,
[data-theme="light"] .product-stock,
[data-theme="light"] .item-unit-price,
[data-theme="light"] .form-label {
  color: #475569 !important;
}

[data-theme="light"] .mobile-tab-btn {
  color: #475569 !important;
}

[data-theme="light"] .mobile-tab-btn.active {
  color: #2563eb !important;
  background: rgba(37, 99, 235, 0.08) !important;
}

[data-theme="light"] .item-qty-controls,
[data-theme="light"] .pay-method-btn:not(.active),
[data-theme="light"] .chip-btn:not(.active) {
  background-color: #f8fafc !important;
  border-color: #cbd5e1 !important;
  color: #1e293b !important;
}

[data-theme="light"] .qty-btn {
  color: #1e293b !important;
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
  height: 100dvh;
  width: 100%;
  max-width: 100%;
  overflow: hidden;
}

.main-content-viewport {
  flex: 1;
  min-height: 0;
  width: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
}
</style>
