<template>
  <div class="app-layout" :class="{ 'rail-mode': isRailMode }">
    <!-- Super Admin Sidebar -->
    <aside class="sidebar" :class="{ 'sidebar-collapsed': isRailMode }">
      <!-- Brand Header -->
      <div class="sidebar-header">
        <div class="brand-logo" style="background: linear-gradient(135deg, #ec4899, #8b5cf6);" title="PK POS Super Admin">SA</div>
        <div v-if="!isRailMode" class="brand-text">
          <h1>PK POS</h1>
          <span style="color: #ec4899;">Platform Admin</span>
        </div>
        <button
          class="rail-toggle-btn"
          :title="isRailMode ? 'Expand Sidebar' : 'Collapse to Mini-Rail'"
          @click="toggleRailMode"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <template v-if="!isRailMode">
              <polyline points="11 17 6 12 11 7"></polyline>
              <polyline points="18 17 13 12 18 7"></polyline>
            </template>
            <template v-else>
              <polyline points="13 17 18 12 13 7"></polyline>
              <polyline points="6 17 11 12 6 7"></polyline>
            </template>
          </svg>
        </button>
      </div>

      <!-- Quick Search / Filter Input inside Sidebar -->
      <div v-if="!isRailMode" class="sidebar-filter-wrapper">
        <div class="sidebar-search-box">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input
            ref="sidebarFilterInput"
            v-model="navFilter"
            type="text"
            placeholder="Filter links... (/)"
            class="sidebar-filter-input"
          />
          <span v-if="navFilter" class="clear-filter" @click="navFilter = ''">&times;</span>
          <span v-else class="hotkey-hint">/</span>
        </div>
      </div>

      <!-- Navigation Links Container -->
      <nav class="sidebar-nav">
        <!-- 1. Core Operations Group -->
        <div v-if="matchesGroup('core')" class="nav-group">
          <div
            v-if="!isRailMode"
            class="nav-group-header"
            @click="toggleGroup('core')"
          >
            <span>Core Operations</span>
            <svg
              class="group-chevron"
              :class="{ collapsed: collapsedGroups.core }"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>

          <div v-show="isRailMode || !collapsedGroups.core" class="nav-group-items">
            <Link
              v-if="matchesFilter('Dashboard', '/superadmin/dashboard')"
              href="/superadmin/dashboard"
              class="nav-link"
              :class="{ active: currentPath === '/superadmin/dashboard' }"
              title="Dashboard"
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
              v-if="matchesFilter('All Tenants', '/superadmin/tenants')"
              href="/superadmin/tenants"
              class="nav-link"
              :class="{ active: currentPath === '/superadmin/tenants' || (currentPath.startsWith('/superadmin/tenants/') && currentPath !== '/superadmin/tenants/create') }"
              title="All Tenants"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
              <span v-if="!isRailMode">All Tenants</span>
            </Link>

            <Link
              v-if="canViewOnboarding && matchesFilter('Onboarding Queue', '/superadmin/onboarding')"
              href="/superadmin/onboarding"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/onboarding') }"
              title="Onboarding Queue"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <polyline points="16 11 18 13 22 9"></polyline>
              </svg>
              <span v-if="!isRailMode">Onboarding</span>
              <span v-if="!isRailMode && badges?.pending_onboarding > 0" class="nav-badge badge-amber">
                {{ badges.pending_onboarding }}
              </span>
            </Link>

            <Link
              v-if="isSuperAdmin && matchesFilter('Create Tenant', '/superadmin/tenants/create')"
              href="/superadmin/tenants/create"
              class="nav-link"
              :class="{ active: currentPath === '/superadmin/tenants/create' }"
              title="Create Tenant"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="16"></line>
                <line x1="8" y1="12" x2="16" y2="12"></line>
              </svg>
              <span v-if="!isRailMode">Create Tenant</span>
            </Link>
          </div>
        </div>

        <!-- 2. Hardware & Terminal Fleet Group -->
        <div v-if="matchesGroup('fleet')" class="nav-group">
          <div
            v-if="!isRailMode"
            class="nav-group-header"
            @click="toggleGroup('fleet')"
          >
            <span>Hardware & Fleet</span>
            <svg
              class="group-chevron"
              :class="{ collapsed: collapsedGroups.fleet }"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>

          <div v-show="isRailMode || !collapsedGroups.fleet" class="nav-group-items">
            <Link
              v-if="canViewTerminals && matchesFilter('Terminal Fleet', '/superadmin/terminals')"
              href="/superadmin/terminals"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/terminals') }"
              title="Terminal Fleet"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
              </svg>
              <span v-if="!isRailMode">Terminal Fleet</span>
              <span v-if="!isRailMode && badges?.offline_terminals > 0" class="nav-badge badge-purple" title="Offline Terminals">
                {{ badges.offline_terminals }}
              </span>
            </Link>

            <Link
              v-if="canViewReleases && matchesFilter('Releases & OTA', '/superadmin/releases')"
              href="/superadmin/releases"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/releases') }"
              title="Releases & OTA"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                <line x1="12" y1="22.08" x2="12" y2="12"></line>
              </svg>
              <span v-if="!isRailMode">Releases & OTA</span>
            </Link>
          </div>
        </div>

        <!-- 3. SaaS & Commercials Group -->
        <div v-if="matchesGroup('saas')" class="nav-group">
          <div
            v-if="!isRailMode"
            class="nav-group-header"
            @click="toggleGroup('saas')"
          >
            <span>SaaS & Commercials</span>
            <svg
              class="group-chevron"
              :class="{ collapsed: collapsedGroups.saas }"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>

          <div v-show="isRailMode || !collapsedGroups.saas" class="nav-group-items">
            <Link
              v-if="isSuperAdmin && matchesFilter('Plans & Quotas', '/superadmin/plans')"
              href="/superadmin/plans"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/plans') }"
              title="Plans & Quotas"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                <line x1="1" y1="10" x2="23" y2="10"></line>
              </svg>
              <span v-if="!isRailMode">Plans & Quotas</span>
            </Link>

            <Link
              v-if="canViewBilling && matchesFilter('Billing & 1Bill', '/superadmin/billing')"
              href="/superadmin/billing"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/billing') }"
              title="Billing & 1Bill"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
              </svg>
              <span v-if="!isRailMode">Billing & 1Bill</span>
            </Link>
          </div>
        </div>

        <!-- 4. Fiscal & Compliance Group -->
        <div v-if="canViewFbr && matchesGroup('fiscal')" class="nav-group">
          <div
            v-if="!isRailMode"
            class="nav-group-header"
            @click="toggleGroup('fiscal')"
          >
            <span>Fiscal & Compliance</span>
            <svg
              class="group-chevron"
              :class="{ collapsed: collapsedGroups.fiscal }"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>

          <div v-show="isRailMode || !collapsedGroups.fiscal" class="nav-group-items">
            <Link
              v-if="matchesFilter('FBR Command Center', '/superadmin/compliance/fbr')"
              href="/superadmin/compliance/fbr"
              class="nav-link"
              :class="{ active: currentPath === '/superadmin/compliance/fbr' }"
              title="FBR Command Center"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
              </svg>
              <span v-if="!isRailMode">FBR Command Center</span>
            </Link>

            <Link
              v-if="matchesFilter('Failed Invoices DLQ', '/superadmin/compliance/fbr/failed')"
              href="/superadmin/compliance/fbr/failed"
              class="nav-link"
              :class="{ active: currentPath === '/superadmin/compliance/fbr/failed' }"
              title="Failed Invoices (DLQ)"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
              </svg>
              <span v-if="!isRailMode">Failed Invoices (DLQ)</span>
              <span v-if="!isRailMode && badges?.dlq_count > 0" class="nav-badge badge-rose">
                {{ badges.dlq_count }}
              </span>
            </Link>

            <Link
              v-if="matchesFilter('24h Provisional Tracker', '/superadmin/compliance/fbr/provisional')"
              href="/superadmin/compliance/fbr/provisional"
              class="nav-link"
              :class="{ active: currentPath === '/superadmin/compliance/fbr/provisional' }"
              title="24h Provisional Tracker"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
              <span v-if="!isRailMode">24h Provisional Tracker</span>
            </Link>
          </div>
        </div>

        <!-- 5. Master Data Group -->
        <div v-if="canViewMasterData && matchesGroup('master')" class="nav-group">
          <div
            v-if="!isRailMode"
            class="nav-group-header"
            @click="toggleGroup('master')"
          >
            <span>Master Data</span>
            <svg
              class="group-chevron"
              :class="{ collapsed: collapsedGroups.master }"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>

          <div v-show="isRailMode || !collapsedGroups.master" class="nav-group-items">
            <Link
              v-if="matchesFilter('HS Codes Catalog', '/superadmin/master-data/hs-codes')"
              href="/superadmin/master-data/hs-codes"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/master-data/hs-codes') }"
              title="HS Codes Catalog"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
              </svg>
              <span v-if="!isRailMode">HS Codes Catalog</span>
            </Link>

            <Link
              v-if="matchesFilter('Tax Templates', '/superadmin/master-data/tax-templates')"
              href="/superadmin/master-data/tax-templates"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/master-data/tax-templates') }"
              title="Tax Templates"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                <line x1="6" y1="8" x2="6" y2="8"></line>
                <line x1="10" y1="8" x2="18" y2="8"></line>
                <line x1="6" y1="12" x2="6" y2="12"></line>
                <line x1="10" y1="12" x2="18" y2="12"></line>
                <line x1="6" y1="16" x2="6" y2="16"></line>
                <line x1="10" y1="16" x2="14" y2="16"></line>
              </svg>
              <span v-if="!isRailMode">Tax Templates</span>
            </Link>
          </div>
        </div>

        <!-- 6. Support & Communication Group -->
        <div v-if="(canViewSupport || canViewBroadcasts) && matchesGroup('support')" class="nav-group">
          <div
            v-if="!isRailMode"
            class="nav-group-header"
            @click="toggleGroup('support')"
          >
            <span>Support & Comms</span>
            <svg
              class="group-chevron"
              :class="{ collapsed: collapsedGroups.support }"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>

          <div v-show="isRailMode || !collapsedGroups.support" class="nav-group-items">
            <Link
              v-if="canViewSupport && matchesFilter('Support Tickets', '/superadmin/support/tickets')"
              href="/superadmin/support/tickets"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/support/tickets') }"
              title="Support Tickets"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
              </svg>
              <span v-if="!isRailMode">Support Tickets</span>
              <span v-if="!isRailMode && badges?.open_tickets > 0" class="nav-badge badge-blue">
                {{ badges.open_tickets }}
              </span>
            </Link>

            <Link
              v-if="canViewSupport && matchesFilter('Knowledge Base', '/superadmin/support/knowledge-base')"
              href="/superadmin/support/knowledge-base"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/support/knowledge-base') }"
              title="Knowledge Base"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
              </svg>
              <span v-if="!isRailMode">Knowledge Base</span>
            </Link>

            <Link
              v-if="canViewBroadcasts && matchesFilter('Broadcasts & Alerts', '/superadmin/broadcasts')"
              href="/superadmin/broadcasts"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/broadcasts') }"
              title="Broadcasts & Alerts"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
              </svg>
              <span v-if="!isRailMode">Broadcasts</span>
            </Link>
          </div>
        </div>

        <!-- 7. System & Telemetry Group -->
        <div v-if="isSuperAdmin && matchesGroup('system')" class="nav-group">
          <div
            v-if="!isRailMode"
            class="nav-group-header"
            @click="toggleGroup('system')"
          >
            <span>System & Telemetry</span>
            <svg
              class="group-chevron"
              :class="{ collapsed: collapsedGroups.system }"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>

          <div v-show="isRailMode || !collapsedGroups.system" class="nav-group-items">
            <Link
              v-if="matchesFilter('System Health', '/superadmin/system/health')"
              href="/superadmin/system/health"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/system/health') }"
              title="System Health"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
              </svg>
              <span v-if="!isRailMode">System Health</span>
            </Link>

            <Link
              v-if="matchesFilter('Queue & Workers', '/superadmin/system/jobs')"
              href="/superadmin/system/jobs"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/system/jobs') }"
              title="Queue & Workers"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
              </svg>
              <span v-if="!isRailMode">Queue & Workers</span>
              <span v-if="!isRailMode && badges?.failed_jobs > 0" class="nav-badge badge-rose">
                {{ badges.failed_jobs }}
              </span>
            </Link>

            <Link
              v-if="matchesFilter('Backups & 6Y Archive', '/superadmin/backups')"
              href="/superadmin/backups"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/backups') }"
              title="Backups & 6Y Archive"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
              </svg>
              <span v-if="!isRailMode">Backups & 6Y Archive</span>
            </Link>
          </div>
        </div>

        <!-- 8. Security & Access Group -->
        <div v-if="matchesGroup('security')" class="nav-group">
          <div
            v-if="!isRailMode"
            class="nav-group-header"
            @click="toggleGroup('security')"
          >
            <span>Security & Access</span>
            <svg
              class="group-chevron"
              :class="{ collapsed: collapsedGroups.security }"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>

          <div v-show="isRailMode || !collapsedGroups.security" class="nav-group-items">
            <Link
              v-if="isSuperAdmin && matchesFilter('Staff Directory', '/superadmin/staff')"
              href="/superadmin/staff"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/staff') }"
              title="Staff Directory"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
              <span v-if="!isRailMode">Staff Directory</span>
            </Link>

            <Link
              v-if="isSuperAdmin && matchesFilter('Roles & Permissions', '/superadmin/roles')"
              href="/superadmin/roles"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/roles') }"
              title="Roles & Permissions"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
              <span v-if="!isRailMode">Roles & Permissions</span>
            </Link>

            <Link
              v-if="canViewAudit && matchesFilter('Audit Logs', '/superadmin/audit-logs')"
              href="/superadmin/audit-logs"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/audit-logs') }"
              title="Audit Logs"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
              </svg>
              <span v-if="!isRailMode">Audit Logs</span>
            </Link>

            <Link
              v-if="matchesFilter('Security & Killswitch', '/superadmin/security')"
              href="/superadmin/security"
              class="nav-link"
              :class="{ active: currentPath.startsWith('/superadmin/security') }"
              title="Security & Killswitch"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <circle cx="12" cy="11" r="3"></circle>
              </svg>
              <span v-if="!isRailMode">Security & Sessions</span>
            </Link>
          </div>
        </div>
      </nav>

      <!-- Sidebar Footer User Profile -->
      <div class="sidebar-footer">
        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
          <div class="user-badge" :title="user?.name">
            <div class="user-avatar" :style="avatarStyle">
              {{ user?.name ? user.name.charAt(0).toUpperCase() : 'S' }}
            </div>
            <div v-if="!isRailMode" class="user-info">
              <span class="user-name">{{ user?.name || 'Staff' }}</span>
              <span class="user-role" :style="{ color: roleColor, fontWeight: '600' }">{{ roleLabel }}</span>
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
      <!-- Impersonation Alert Ribbon -->
      <div v-if="impersonationActive" class="impersonation-ribbon">
        <div class="ribbon-text">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="8.5" cy="7" r="4"></circle>
            <line x1="20" y1="8" x2="20" y2="14"></line>
            <line x1="23" y1="11" x2="17" y2="11"></line>
          </svg>
          <span>
            Operating in <strong>AUDITED IMPERSONATION MODE</strong> for merchant: <u>{{ impersonatedTenantName }}</u>
          </span>
        </div>
        <Link
          href="/superadmin/impersonate/leave"
          class="btn-exit-impersonation"
        >
          Exit Impersonation (Esc)
        </Link>
      </div>

      <!-- Topbar Header -->
      <header class="topbar">
        <div class="topbar-left">
          <h2 class="page-title">{{ title }}</h2>
        </div>

        <!-- Global Search / Command Palette Button -->
        <div class="topbar-center">
          <button class="command-palette-trigger" @click="isPaletteOpen = true">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <span class="trigger-label">Quick search or jump to...</span>
            <span class="trigger-kbd">Ctrl K</span>
          </button>
        </div>

        <div class="topbar-actions">
          <!-- FBR Live Gateway Status Pill -->
          <div class="fbr-gateway-pill" :class="isFbrSandbox ? 'sandbox' : 'live'">
            <span class="pulse-dot"></span>
            <span class="fbr-text">{{ isFbrSandbox ? 'FBR IRIS Sandbox' : 'FBR IRIS Live' }}</span>
          </div>

          <!-- Staff Role Badge -->
          <span class="badge" :style="topbarBadgeStyle">
            <span class="badge-dot" :style="{ backgroundColor: roleColor }"></span>
            {{ roleLabel }}
          </span>
        </div>
      </header>

      <!-- Main Page Slot Container -->
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

      <!-- Fixed Telemetry Footer Status Bar -->
      <footer class="telemetry-bar">
        <div class="telemetry-left">
          <span class="telemetry-chip">
            <span class="clock-icon">🕒</span>
            <span class="clock-time">{{ livePktClock }}</span>
          </span>
          <span class="telemetry-sep">|</span>
          <span class="telemetry-chip">
            <span class="db-dot"></span>
            PostgreSQL: Connected
          </span>
        </div>

        <div class="telemetry-center">
          <span class="scope-indicator">Platform Scope: Global Multi-Tenant Master</span>
        </div>

        <div class="telemetry-right">
          <span class="telemetry-chip" :class="{ 'has-failed': (badges?.failed_jobs || 0) > 0 }">
            Queue: {{ (badges?.failed_jobs || 0) > 0 ? `${badges.failed_jobs} Failed` : 'Workers Online' }}
          </span>
          <span class="telemetry-sep">|</span>
          <span class="telemetry-version">v2.4.0-fiscal</span>
        </div>
      </footer>
    </div>

    <!-- Command Palette Dialog -->
    <CommandPalette
      :is-open="isPaletteOpen"
      :badges="badges"
      @update:is-open="isPaletteOpen = $event"
      @close="isPaletteOpen = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import CommandPalette from '../Components/CommandPalette.vue';

const props = defineProps({
  title: {
    type: String,
    default: 'Super Admin',
  },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const currentPath = computed(() => page.url);
const badges = computed(() => page.props.superadmin_badges || {});
const telemetry = computed(() => page.props.system_telemetry || {});

const isPaletteOpen = ref(false);
const isRailMode = ref(false);
const navFilter = ref('');
const sidebarFilterInput = ref(null);

// Collapsible Group States
const collapsedGroups = ref({
  core: false,
  fleet: false,
  saas: false,
  fiscal: false,
  master: false,
  support: false,
  system: false,
  security: false,
});

function toggleGroup(groupKey) {
  collapsedGroups.value[groupKey] = !collapsedGroups.value[groupKey];
}

function toggleRailMode() {
  isRailMode.value = !isRailMode.value;
}

// Live PKT (UTC+5) Clock
const livePktClock = ref('');
let clockTimer = null;

function updateClock() {
  const d = new Date();
  // Format in Asia/Karachi
  livePktClock.value = d.toLocaleTimeString('en-US', {
    timeZone: 'Asia/Karachi',
    hour12: false,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }) + ' PKT (UTC+5)';
}

const staffRole = computed(() => user.value?.staff_role || 'super_admin');
const isSuperAdmin = computed(() => staffRole.value === 'super_admin');
const canViewSupport = computed(() => ['super_admin', 'support_agent'].includes(staffRole.value));
const canViewOnboarding = computed(() => ['super_admin', 'compliance_auditor', 'support_agent'].includes(staffRole.value));
const canViewBilling = computed(() => ['super_admin', 'billing_specialist'].includes(staffRole.value));
const canViewBroadcasts = computed(() => ['super_admin', 'support_agent', 'compliance_auditor', 'billing_specialist', 'release_engineer'].includes(staffRole.value));
const canViewTerminals = computed(() => ['super_admin', 'support_agent'].includes(staffRole.value));
const canViewReleases = computed(() => ['super_admin', 'support_agent'].includes(staffRole.value));
const canViewFbr = computed(() => ['super_admin', 'compliance_auditor'].includes(staffRole.value));
const canViewMasterData = computed(() => ['super_admin', 'compliance_auditor'].includes(staffRole.value));
const canViewAudit = computed(() => ['super_admin', 'compliance_auditor'].includes(staffRole.value));

const isFbrSandbox = computed(() => telemetry.value?.fbr_sandbox_mode ?? true);
const impersonationActive = computed(() => page.props.auth?.impersonation?.active);
const impersonatedTenantName = computed(() => page.props.auth?.impersonation?.tenant_name || 'Tenant');

const roleLabel = computed(() => {
  switch (staffRole.value) {
    case 'support_agent':
      return 'Support Agent';
    case 'compliance_auditor':
      return 'Compliance Auditor';
    case 'billing_specialist':
      return 'Billing Specialist';
    case 'release_engineer':
      return 'Release Engineer';
    default:
      return 'Super Admin';
  }
});

const roleColor = computed(() => {
  switch (staffRole.value) {
    case 'support_agent':
      return '#60a5fa';
    case 'compliance_auditor':
      return '#fbbf24';
    case 'billing_specialist':
      return '#34d399';
    case 'release_engineer':
      return '#a78bfa';
    default:
      return '#ec4899';
  }
});

const avatarStyle = computed(() => ({
  color: roleColor.value,
  borderColor: `${roleColor.value}66`,
}));

const topbarBadgeStyle = computed(() => ({
  background: `${roleColor.value}22`,
  color: roleColor.value,
  borderColor: `${roleColor.value}44`,
}));

// Filter helper
function matchesFilter(title, href) {
  if (!navFilter.value) return true;
  const q = navFilter.value.trim().toLowerCase();
  return title.toLowerCase().includes(q) || href.toLowerCase().includes(q);
}

function matchesGroup(groupKey) {
  if (!navFilter.value) return true;
  const q = navFilter.value.trim().toLowerCase();
  // If filter is active, check if group contains matches
  if (groupKey === 'core') return matchesFilter('Dashboard', '') || matchesFilter('Tenants', '') || matchesFilter('Onboarding', '');
  if (groupKey === 'fleet') return matchesFilter('Terminal', '') || matchesFilter('Releases', '');
  if (groupKey === 'saas') return matchesFilter('Plans', '') || matchesFilter('Billing', '');
  if (groupKey === 'fiscal') return matchesFilter('FBR', '') || matchesFilter('Failed', '') || matchesFilter('Provisional', '');
  if (groupKey === 'master') return matchesFilter('HS Codes', '') || matchesFilter('Tax Templates', '');
  if (groupKey === 'support') return matchesFilter('Support', '') || matchesFilter('Knowledge', '') || matchesFilter('Broadcasts', '');
  if (groupKey === 'system') return matchesFilter('Health', '') || matchesFilter('Queue', '') || matchesFilter('Backups', '');
  if (groupKey === 'security') return matchesFilter('Staff', '') || matchesFilter('Roles', '') || matchesFilter('Audit', '') || matchesFilter('Security', '');
  return true;
}

function handleGlobalKey(e) {
  // Shortcut '/' to focus sidebar filter if not inside an input
  if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
    e.preventDefault();
    if (isRailMode.value) isRailMode.value = false;
    sidebarFilterInput.value?.focus();
  }
}

onMounted(() => {
  updateClock();
  clockTimer = setInterval(updateClock, 1000);
  window.addEventListener('keydown', handleGlobalKey);
});

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer);
  window.removeEventListener('keydown', handleGlobalKey);
});
</script>

<style scoped>
/* Sidebar Modifications */
.sidebar {
  transition: width 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  height: 100vh;
  max-height: 100vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.sidebar.sidebar-collapsed {
  width: 68px;
}

.sidebar-nav {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}

.sidebar-nav::-webkit-scrollbar {
  width: 5px;
}

.sidebar-nav::-webkit-scrollbar-track {
  background: transparent;
}

.sidebar-nav::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 9999px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.4);
}

.rail-toggle-btn {
  background: transparent;
  border: 1px solid var(--border-color);
  color: var(--text-muted);
  width: 26px;
  height: 26px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  margin-left: auto;
  transition: all 0.15s ease;
}

.rail-toggle-btn:hover {
  color: var(--text-primary);
  border-color: var(--text-secondary);
}

.sidebar-filter-wrapper {
  padding: 0 1rem 0.5rem;
}

.sidebar-search-box {
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  display: flex;
  align-items: center;
  padding: 0.35rem 0.6rem;
  gap: 0.5rem;
  color: var(--text-muted);
}

.sidebar-filter-input {
  background: transparent;
  border: none;
  outline: none;
  color: var(--text-primary);
  font-size: 0.8rem;
  width: 100%;
}

.sidebar-filter-input::placeholder {
  color: var(--text-muted);
}

.clear-filter {
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
}

.hotkey-hint {
  font-size: 0.7rem;
  background: var(--bg-tertiary);
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
}

/* Nav Groups & Headers */
.nav-group {
  margin-bottom: 0.5rem;
}

.nav-group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.65rem 0.75rem 0.25rem;
  font-size: 0.68rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  cursor: pointer;
  user-select: none;
}

.nav-group-header:hover {
  color: var(--text-secondary);
}

.group-chevron {
  transition: transform 0.2s ease;
}

.group-chevron.collapsed {
  transform: rotate(-90deg);
}

.nav-link {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.nav-badge {
  margin-left: auto;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.1rem 0.45rem;
  border-radius: 9999px;
  line-height: 1.2;
}

.badge-amber {
  background: rgba(245, 158, 11, 0.2);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.4);
}

.badge-rose {
  background: rgba(244, 63, 94, 0.2);
  color: #fb7185;
  border: 1px solid rgba(244, 63, 94, 0.4);
}

.badge-blue {
  background: rgba(59, 130, 246, 0.2);
  color: #60a5fa;
  border: 1px solid rgba(59, 130, 246, 0.4);
}

.badge-purple {
  background: rgba(139, 92, 246, 0.2);
  color: #a78bfa;
  border: 1px solid rgba(139, 92, 246, 0.4);
}

/* Topbar Center Command Palette */
.topbar-center {
  flex: 1;
  max-width: 420px;
  margin: 0 1.5rem;
}

.command-palette-trigger {
  width: 100%;
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 0.45rem 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-muted);
  cursor: pointer;
  transition: all 0.15s ease;
}

.command-palette-trigger:hover {
  border-color: var(--text-secondary);
  color: var(--text-primary);
}

.trigger-label {
  flex: 1;
  text-align: left;
  font-size: 0.85rem;
}

.trigger-kbd {
  font-size: 0.7rem;
  background: var(--bg-tertiary);
  border: 1px solid var(--border-color);
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  color: var(--text-secondary);
}

/* FBR Gateway Pill */
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

/* Impersonation Ribbon */
.impersonation-ribbon {
  background: linear-gradient(90deg, #d97706, #b45309);
  color: #ffffff;
  padding: 0.5rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.85rem;
}

.ribbon-text {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-exit-impersonation {
  background: #ffffff;
  color: #b45309;
  border: none;
  font-weight: 700;
  font-size: 0.75rem;
  padding: 0.25rem 0.75rem;
  border-radius: 4px;
  cursor: pointer;
  text-decoration: none;
}

/* Telemetry Footer Status Bar */
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

.telemetry-chip.has-failed {
  color: #fb7185;
  font-weight: 700;
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
