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
}

body {
  margin: 0;
  padding: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  background-color: #0f172a;
  color: #f8fafc;
  overflow: hidden;
  user-select: none;
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
