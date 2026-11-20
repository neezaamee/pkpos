<template>
  <div class="app-layout" :class="{ 'rail-mode': isRailMode }">
    <!-- Sidebar Navigation -->
    <aside class="sidebar" :class="{ 'sidebar-collapsed': isRailMode }">
      <div class="sidebar-header">
        <div class="brand-logo" title="PK POS Merchant Portal">PK</div>
        <div v-if="!isRailMode" class="brand-text">
          <h1>PK POS</h1>
          <span>FBR POS Admin</span>
        </div>
      </div>

      <nav class="sidebar-nav">
        <!-- Platform Super Admin Switcher -->
        <div v-if="user?.is_super_admin && !isRailMode" style="margin-bottom: 0.85rem; padding: 0.75rem; background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(6, 182, 212, 0.2)); border: 1px solid rgba(99, 102, 241, 0.4); border-radius: 8px;">
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
          v-if="user?.is_super_admin && isRailMode"
          href="/superadmin/dashboard"
          class="nav-link"
          title="Super Admin Console"
          style="justify-content: center; color: #a5b4fc; margin-bottom: 0.85rem;"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
        </Link>

        <Link
          href="/dashboard"
          class="nav-link"
          :class="{ active: currentPath === '/dashboard' || currentPath === '/' }"
          :title="isRailMode ? 'Dashboard' : ''"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7"></rect>
            <rect x="14" y="3" width="7" height="7"></rect>
            <rect x="14" y="14" width="7" height="7"></rect>
            <rect x="3" y="14" width="7" height="7"></rect>
          </svg>
          <span v-if="!isRailMode">Dashboard</span>
        </Link>

        <Link
          href="/sales"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/sales') }"
          :title="isRailMode ? 'Sales & Invoices' : ''"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <path d="M16 10a4 4 0 0 1-8 0"></path>
          </svg>
          <span v-if="!isRailMode">Sales &amp; Invoices</span>
        </Link>

        <Link
          href="/returns"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/returns') }"
          :title="isRailMode ? 'Returns & Credit Notes' : ''"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="1 4 1 10 7 10"></polyline>
            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
          </svg>
          <span v-if="!isRailMode">Returns &amp; Credit Notes</span>
        </Link>

        <Link
          href="/shifts"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/shifts') }"
          :title="isRailMode ? 'Register Shifts (Z-Reports)' : ''"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="4" width="20" height="16" rx="2"></rect>
            <line x1="2" y1="10" x2="22" y2="10"></line>
            <line x1="7" y1="15" x2="7.01" y2="15"></line>
            <line x1="11" y1="15" x2="13" y2="15"></line>
          </svg>
          <span v-if="!isRailMode">Register Shifts (Z-Reports)</span>
        </Link>

        <Link
          href="/customers"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/customers') }"
          :title="isRailMode ? 'Customers & Khata' : ''"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <span v-if="!isRailMode">Customers &amp; Khata</span>
        </Link>

        <Link
          v-if="user?.role === 'owner'"
          href="/fbr-credentials"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/fbr-credentials') }"
          :title="isRailMode ? 'FBR Credentials' : ''"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <span v-if="!isRailMode">FBR Credentials</span>
        </Link>

        <div v-if="!isRailMode" style="margin: 1.25rem 0 0.35rem 0.75rem; font-size: 0.68rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em;">
          Catalog
        </div>

        <Link
          href="/products"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/products') }"
          :title="isRailMode ? 'Products' : ''"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
            <line x1="12" y1="22.08" x2="12" y2="12"></line>
          </svg>
          <span v-if="!isRailMode">Products</span>
        </Link>

        <Link
          href="/tax-rules"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/tax-rules') }"
          :title="isRailMode ? 'Tax Rules' : ''"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="19" y1="5" x2="5" y2="19"></line>
            <circle cx="6.5" cy="6.5" r="2.5"></circle>
            <circle cx="17.5" cy="17.5" r="2.5"></circle>
          </svg>
          <span v-if="!isRailMode">Tax Rules</span>
        </Link>

        <Link
          href="/uom"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/uom') }"
          :title="isRailMode ? 'Units & Packaging' : ''"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
          </svg>
          <span v-if="!isRailMode">Units &amp; Packaging</span>
        </Link>

        <Link
          href="/price-lists"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/price-lists') }"
          :title="isRailMode ? 'Tiered Price Lists' : ''"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="1" x2="12" y2="23"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
          </svg>
          <span v-if="!isRailMode">Tiered Price Lists</span>
        </Link>

        <!-- Stock Section -->
        <template v-if="user?.role === 'owner' || user?.role === 'manager'">
          <div v-if="!isRailMode" style="margin: 1.25rem 0 0.35rem 0.75rem; font-size: 0.68rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em;">
            Stock &amp; Inventory
          </div>

          <Link
            href="/inventory/levels"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/inventory/levels') || currentPath.startsWith('/inventory/adjust') }"
            :title="isRailMode ? 'Stock Levels' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
              <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
            <span v-if="!isRailMode">Stock Levels</span>
          </Link>

          <Link
            href="/inventory/movements"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/inventory/movements') }"
            :title="isRailMode ? 'Movements Log' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
              <polyline points="17 6 23 6 23 12"></polyline>
            </svg>
            <span v-if="!isRailMode">Movements Log</span>
          </Link>

          <Link
            href="/purchases"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/purchases') }"
            :title="isRailMode ? 'Purchase Orders (GRN)' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <path d="M16 10a4 4 0 0 1-8 0"></path>
            </svg>
            <span v-if="!isRailMode">Purchase Orders (GRN)</span>
          </Link>

          <Link
            href="/suppliers"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/suppliers') }"
            :title="isRailMode ? 'Suppliers & Payables' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span v-if="!isRailMode">Suppliers &amp; Payables</span>
          </Link>
        </template>

        <!-- Pharmacy & Medical Store Vertical -->
        <template v-if="user?.role === 'owner' || user?.role === 'manager'">
          <div v-if="!isRailMode" style="margin: 1.25rem 0 0.35rem 0.75rem; font-size: 0.68rem; font-weight: 700; color: #10b981; text-transform: uppercase; letter-spacing: 0.08em; display: flex; align-items: center; gap: 0.4rem;">
            <span>💊</span> Pharmacy &amp; Medical
          </div>

          <Link
            href="/pharmacy/batches"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/pharmacy/batches') }"
            :title="isRailMode ? 'Batches & FEFO Expiry' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span v-if="!isRailMode">Batches &amp; Expiry (FEFO)</span>
          </Link>

          <Link
            href="/pharmacy/narcotics"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/pharmacy/narcotics') }"
            :title="isRailMode ? 'Narcotics Register (Schedule D)' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
              <line x1="12" y1="9" x2="12" y2="13"></line>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <span v-if="!isRailMode">Narcotics (Schedule D)</span>
          </Link>

          <Link
            href="/pharmacy/prescriptions"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/pharmacy/prescriptions') }"
            :title="isRailMode ? 'Doctor Prescriptions' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="12" y1="18" x2="12" y2="12"></line>
              <line x1="9" y1="15" x2="15" y2="15"></line>
            </svg>
            <span v-if="!isRailMode">Doctor Prescriptions</span>
          </Link>
        </template>

        <!-- Restaurant & Food Service Vertical -->
        <template v-if="user?.role === 'owner' || user?.role === 'manager'">
          <div v-if="!isRailMode" style="margin: 1.25rem 0 0.35rem 0.75rem; font-size: 0.68rem; font-weight: 700; color: #f59e0b; text-transform: uppercase; letter-spacing: 0.08em; display: flex; align-items: center; gap: 0.4rem;">
            <span>🍽️</span> Restaurant &amp; Food
          </div>

          <Link
            href="/restaurant/tables"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/restaurant/tables') }"
            :title="isRailMode ? 'Floor Plans & Tables' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span v-if="!isRailMode">Floors &amp; Tables</span>
          </Link>

          <Link
            href="/restaurant/stations"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/restaurant/stations') }"
            :title="isRailMode ? 'Kitchen Stations' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2c-4 4-8 7.5-8 12a8 8 0 0 0 16 0c0-4.5-4-8-8-12z"></path>
            </svg>
            <span v-if="!isRailMode">Kitchen Stations</span>
          </Link>

          <Link
            href="/restaurant/modifiers"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/restaurant/modifiers') }"
            :title="isRailMode ? 'Menu Modifiers & Add-ons' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="8" y1="6" x2="21" y2="6"></line>
              <line x1="8" y1="12" x2="21" y2="12"></line>
              <line x1="8" y1="18" x2="21" y2="18"></line>
              <line x1="3" y1="6" x2="3.01" y2="6"></line>
              <line x1="3" y1="12" x2="3.01" y2="12"></line>
              <line x1="3" y1="18" x2="3.01" y2="18"></line>
            </svg>
            <span v-if="!isRailMode">Menu Modifiers</span>
          </Link>

          <Link
            href="/restaurant/kds"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/restaurant/kds') }"
            :title="isRailMode ? 'Live Kitchen Display (KDS)' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
              <line x1="8" y1="21" x2="16" y2="21"></line>
              <line x1="12" y1="17" x2="12" y2="21"></line>
            </svg>
            <span v-if="!isRailMode">Kitchen Display (KDS)</span>
          </Link>
        </template>

        <!-- Hardware, Building Materials & Sanitary Vertical -->
        <template v-if="user?.role === 'owner' || user?.role === 'manager'">
          <div v-if="!isRailMode" style="margin: 1.25rem 0 0.35rem 0.75rem; font-size: 0.68rem; font-weight: 700; color: #f97316; text-transform: uppercase; letter-spacing: 0.08em; display: flex; align-items: center; gap: 0.4rem;">
            <span>🏗️</span> Hardware &amp; Materials
          </div>

          <Link
            href="/hardware/projects"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/hardware/projects') }"
            :title="isRailMode ? 'Contractor Project Sites' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 21h18"></path>
              <path d="M5 21V7l8-4v18"></path>
              <path d="M19 21V11l-6-4"></path>
              <path d="M9 9h1"></path>
              <path d="M9 13h1"></path>
              <path d="M9 17h1"></path>
            </svg>
            <span v-if="!isRailMode">Project Sites (Khata)</span>
          </Link>

          <Link
            href="/hardware/tiers"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/hardware/tiers') }"
            :title="isRailMode ? 'Wholesale Price Tiers' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="1" x2="12" y2="23"></line>
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
            <span v-if="!isRailMode">Wholesale Price Slabs</span>
          </Link>

          <Link
            href="/hardware/challans"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/hardware/challans') }"
            :title="isRailMode ? 'Delivery Challans & Gate Passes' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="1" y="3" width="15" height="13"></rect>
              <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
              <circle cx="5.5" cy="18.5" r="2.5"></circle>
              <circle cx="18.5" cy="18.5" r="2.5"></circle>
            </svg>
            <span v-if="!isRailMode">Delivery Challans / Gate Pass</span>
          </Link>
        </template>

        <!-- Finance & Accounting Section -->
        <template v-if="user?.role === 'owner' || user?.role === 'manager'">
          <div v-if="!isRailMode" style="margin: 1.25rem 0 0.35rem 0.75rem; font-size: 0.68rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em;">
            Finance &amp; Accounts
          </div>

          <Link
            href="/accounts"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/accounts') }"
            :title="isRailMode ? 'Chart of Accounts' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
              <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
            <span v-if="!isRailMode">Chart of Accounts</span>
          </Link>

          <Link
            href="/journal-entries"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/journal-entries') }"
            :title="isRailMode ? 'General Journal' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
              <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
            <span v-if="!isRailMode">General Journal</span>
          </Link>

          <Link
            href="/reports/trial-balance"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/reports/trial-balance') }"
            :title="isRailMode ? 'Trial Balance' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="20" x2="18" y2="10"></line>
              <line x1="12" y1="20" x2="12" y2="4"></line>
              <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
            <span v-if="!isRailMode">Trial Balance</span>
          </Link>

          <Link
            href="/reports/profit-loss"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/reports/profit-loss') }"
            :title="isRailMode ? 'Profit & Loss' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
              <polyline points="17 6 23 6 23 12"></polyline>
            </svg>
            <span v-if="!isRailMode">Profit &amp; Loss (P&amp;L)</span>
          </Link>

          <Link
            href="/reports/balance-sheet"
            class="nav-link"
            :class="{ active: currentPath.startsWith('/reports/balance-sheet') }"
            :title="isRailMode ? 'Balance Sheet' : ''"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
              <line x1="8" y1="21" x2="16" y2="21"></line>
              <line x1="12" y1="17" x2="12" y2="21"></line>
            </svg>
            <span v-if="!isRailMode">Balance Sheet</span>
          </Link>
        </template>

        <div v-if="!isRailMode" style="margin: 1.25rem 0 0.35rem 0.75rem; font-size: 0.68rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em;">
          POS Terminal
        </div>

        <Link
          href="/terminals"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/terminals') }"
          :title="isRailMode ? 'Terminals & Counters' : ''"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
          </svg>
          <span v-if="!isRailMode">Terminals &amp; Counters</span>
        </Link>

        <Link
          v-if="user?.role === 'owner' || user?.role === 'manager'"
          href="/settings/receipt-template"
          class="nav-link"
          :class="{ active: currentPath.startsWith('/settings/receipt-template') }"
          :title="isRailMode ? 'Receipt Template' : ''"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          <span v-if="!isRailMode">Receipt Template</span>
        </Link>

        <a
          href="/downloads/desktop"
          class="nav-link"
          style="color: #38bdf8; font-weight: 600;"
          :title="isRailMode ? 'Download POS (.exe)' : 'Download the latest desktop POS application installer'"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
          </svg>
          <span v-if="!isRailMode">Download POS (.exe)</span>
        </a>
      </nav>

      <div class="sidebar-footer">
        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
          <div class="user-badge">
            <div class="user-avatar">
              {{ user?.name ? user.name.charAt(0).toUpperCase() : 'U' }}
            </div>
            <div v-if="!isRailMode" class="user-info">
              <span class="user-name">{{ user?.name || 'Authorized User' }}</span>
              <span class="user-role">{{ user?.role || 'Staff' }} • Merchant</span>
            </div>
          </div>
          <Link
            v-if="!isRailMode"
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
        <div class="topbar-left" style="display: flex; align-items: center; gap: 0.85rem; min-width: 0;">
          <button
            type="button"
            class="topbar-sidebar-toggle"
            :class="{ active: isRailMode }"
            :title="isRailMode ? 'Expand Sidebar' : 'Collapse Sidebar to Mini-Rail'"
            aria-label="Toggle Sidebar Navigation"
            @click="toggleRailMode"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>
          <h2 class="page-title">{{ title }}</h2>
        </div>
        <div class="topbar-actions" style="display: flex; align-items: center; gap: 0.75rem;">
          <!-- Theme Toggle Button -->
          <button
            class="theme-toggle-btn"
            :title="currentTheme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
            @click="toggleTheme"
          >
            <svg v-if="currentTheme === 'dark'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="5"></circle>
              <line x1="12" y1="1" x2="12" y2="3"></line>
              <line x1="12" y1="21" x2="12" y2="23"></line>
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
              <line x1="1" y1="12" x2="3" y2="12"></line>
              <line x1="21" y1="12" x2="23" y2="12"></line>
              <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
              <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>
            <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
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

// Theme and rail mode management
const currentTheme = ref('dark');
const isRailMode = ref(false);

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

function toggleRailMode() {
  isRailMode.value = !isRailMode.value;
  if (typeof window !== 'undefined') {
    localStorage.setItem('pk_pos_rail_mode', isRailMode.value ? 'true' : 'false');
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
  if (typeof window !== 'undefined') {
    const savedRail = localStorage.getItem('pk_pos_rail_mode');
    if (savedRail !== null) {
      isRailMode.value = savedRail === 'true';
    }
  }
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
.sidebar {
  transition: width 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  height: 100vh;
}

.sidebar.sidebar-collapsed {
  width: 68px;
}

.sidebar.sidebar-collapsed .sidebar-header {
  justify-content: center;
  padding: 1rem 0.5rem;
}

.sidebar.sidebar-collapsed .nav-link {
  justify-content: center;
  padding: 0.75rem 0;
}

.sidebar.sidebar-collapsed .sidebar-footer {
  padding: 0.75rem 0.5rem;
  justify-content: center;
}

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
