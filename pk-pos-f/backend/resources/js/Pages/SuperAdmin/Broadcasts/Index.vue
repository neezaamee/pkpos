<template>
  <SuperAdminLayout title="Platform Broadcasts & Emergency Alerts">
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
      <!-- Top Action & Overview Header -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
        <div>
          <h1 style="font-size: 1.5rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.25rem;">
            Emergency Banners & Platform Broadcasts
          </h1>
          <p style="color: #94a3b8; font-size: 0.875rem;">
            Dispatch real-time emergency advisories, scheduled FBR maintenance banners, and critical POS firmware notifications to merchants across Pakistan.
          </p>
        </div>
        <button
          v-if="isSuperAdmin"
          @click="openCreateModal"
          class="btn btn-primary"
          style="display: inline-flex; align-items: center; gap: 0.5rem;"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          New Platform Broadcast
        </button>
      </div>

      <!-- Telemetry KPI Cards -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Total Broadcasts
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #f8fafc; margin-top: 0.25rem;">
                {{ metrics.total_broadcasts }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(59, 130, 246, 0.15); border-radius: 8px; color: #60a5fa;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
            Lifetime system advisories
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Active & Live Alerts
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #34d399; margin-top: 0.25rem;">
                {{ metrics.active_live }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(52, 211, 153, 0.15); border-radius: 8px; color: #34d399;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 14 14"></polyline>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #34d399; margin-top: 0.5rem;">
            Currently showing on devices
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Critical Emergencies
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #f87171; margin-top: 0.25rem;">
                {{ metrics.critical_emergency }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(239, 68, 68, 0.15); border-radius: 8px; color: #f87171;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #f87171; margin-top: 0.5rem;">
            Non-dismissible high urgency
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Desktop POS Only
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #c084fc; margin-top: 0.25rem;">
                {{ metrics.desktop_targeted }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(192, 132, 252, 0.15); border-radius: 8px; color: #c084fc;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
            Tauri client exclusive alerts
          </div>
        </div>
      </div>

      <!-- Filter & Search Toolbar -->
      <div class="card" style="padding: 1rem;">
        <form @submit.prevent="applyFilters" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
          <div style="flex: 1; min-width: 240px; position: relative;">
            <input
              type="text"
              v-model="filterForm.q"
              placeholder="Search by title, subject or announcement text..."
              class="form-control"
              style="width: 100%; padding-left: 2.25rem;"
            />
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #64748b;"
            >
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </div>

          <select v-model="filterForm.level" class="form-control" style="width: 180px;">
            <option value="">All Urgency Levels</option>
            <option value="info">Info Advisory</option>
            <option value="warning">Warning / Notice</option>
            <option value="critical_emergency">Critical Emergency</option>
          </select>

          <select v-model="filterForm.status" class="form-control" style="width: 160px;">
            <option value="">All Statuses</option>
            <option value="active">Active & Live</option>
            <option value="inactive">Inactive</option>
            <option value="expired">Expired</option>
          </select>

          <button type="submit" class="btn btn-secondary">
            Filter
          </button>
          <button
            v-if="filterForm.q || filterForm.level || filterForm.status"
            type="button"
            @click="resetFilters"
            class="btn btn-secondary"
            style="color: #94a3b8;"
          >
            Clear
          </button>
        </form>
      </div>

      <!-- Broadcast Announcements Directory Table -->
      <div class="card" style="overflow: hidden;">
        <div style="overflow-x: auto;">
          <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.02);">
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Severity & Title</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Target Audience</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Status / Schedule</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Policy</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Author</th>
                <th v-if="isSuperAdmin" style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8; text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="b in broadcasts.data"
                :key="b.id"
                style="border-bottom: 1px solid rgba(255, 255, 255, 0.05); transition: background-color 0.15s;"
              >
                <!-- Severity & Title -->
                <td style="padding: 1rem;">
                  <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                    <span
                      class="badge"
                      :style="getLevelBadgeStyle(b.level)"
                      style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;"
                    >
                      <span v-if="b.level === 'critical_emergency'" style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #ef4444; margin-right: 4px; animation: pulse 1.5s infinite;"></span>
                      {{ formatLevel(b.level) }}
                    </span>
                    <div>
                      <div style="font-weight: 600; color: #f8fafc; font-size: 0.9375rem;">
                        {{ b.title }}
                      </div>
                      <div style="color: #94a3b8; font-size: 0.8125rem; margin-top: 0.25rem; max-width: 480px; white-space: pre-line;">
                        {{ truncateText(b.message, 120) }}
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Target Audience -->
                <td style="padding: 1rem;">
                  <div v-if="b.target_audience === 'all'">
                    <span class="badge" style="background: rgba(148, 163, 184, 0.15); color: #cbd5e1;">
                      All Merchants & Terminals
                    </span>
                  </div>
                  <div v-else-if="b.target_audience === 'province'">
                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; text-transform: uppercase;">
                      {{ b.target_province || 'Province' }} Province
                    </span>
                  </div>
                  <div v-else-if="b.target_audience === 'desktop_terminals_only'">
                    <span class="badge" style="background: rgba(192, 132, 252, 0.15); color: #c084fc;">
                      Desktop POS Devices
                    </span>
                  </div>
                  <div v-else-if="b.target_audience === 'tenant'">
                    <span class="badge" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                      {{ b.target_tenant?.name || 'Specific Merchant' }}
                    </span>
                  </div>
                </td>

                <!-- Status / Schedule -->
                <td style="padding: 1rem;">
                  <div v-if="b.is_live" style="display: flex; align-items: center; gap: 0.4rem;">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
                    <span style="color: #34d399; font-weight: 600; font-size: 0.8125rem;">Live Now</span>
                  </div>
                  <div v-else-if="!b.is_active" style="display: flex; align-items: center; gap: 0.4rem;">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #64748b;"></span>
                    <span style="color: #94a3b8; font-size: 0.8125rem;">Disabled</span>
                  </div>
                  <div v-else style="display: flex; align-items: center; gap: 0.4rem;">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #f59e0b;"></span>
                    <span style="color: #fbbf24; font-size: 0.8125rem;">Scheduled / Expired</span>
                  </div>

                  <div v-if="b.expires_at" style="font-size: 0.75rem; color: #64748b; margin-top: 0.2rem;">
                    Expires: {{ formatDate(b.expires_at) }}
                  </div>
                </td>

                <!-- Policy -->
                <td style="padding: 1rem; font-size: 0.8125rem;">
                  <span v-if="b.dismissible" style="color: #94a3b8;">
                    Dismissible
                  </span>
                  <span v-else style="color: #f87171; font-weight: 600;">
                    Sticky / Mandatory
                  </span>
                </td>

                <!-- Author -->
                <td style="padding: 1rem; font-size: 0.8125rem; color: #94a3b8;">
                  <div>{{ b.creator?.name || 'System Staff' }}</div>
                  <div style="font-size: 0.72rem; color: #64748b;">{{ formatDate(b.created_at) }}</div>
                </td>

                <!-- Actions -->
                <td v-if="isSuperAdmin" style="padding: 1rem; text-align: right;">
                  <div style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    <button
                      @click="toggleActive(b)"
                      class="btn btn-secondary"
                      style="padding: 0.25rem 0.6rem; font-size: 0.75rem;"
                      :title="b.is_active ? 'Deactivate broadcast' : 'Activate broadcast'"
                    >
                      {{ b.is_active ? 'Pause' : 'Activate' }}
                    </button>
                    <button
                      @click="openEditModal(b)"
                      class="btn btn-secondary"
                      style="padding: 0.25rem 0.6rem; font-size: 0.75rem;"
                    >
                      Edit
                    </button>
                    <button
                      @click="deleteBroadcast(b)"
                      class="btn btn-danger"
                      style="padding: 0.25rem 0.6rem; font-size: 0.75rem;"
                    >
                      Delete
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="broadcasts.data.length === 0">
                <td :colspan="isSuperAdmin ? 6 : 5" style="text-align: center; padding: 3rem 1rem; color: #64748b;">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 0.75rem; color: #475569;">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                  </svg>
                  <div style="font-weight: 600; font-size: 0.9375rem; color: #94a3b8;">No Broadcast Announcements Found</div>
                  <div style="font-size: 0.8125rem; margin-top: 0.25rem;">
                    Create a platform broadcast to notify merchants of maintenance windows, tax updates, or urgent notices.
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Live Broadcast Banner Visual Preview -->
      <div class="card" style="padding: 1.25rem;">
        <h3 style="font-size: 0.875rem; font-weight: 600; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; margin-bottom: 0.75rem;">
          Live Merchant POS Banner Simulation Preview
        </h3>
        <p style="font-size: 0.8125rem; color: #64748b; margin-bottom: 1rem;">
          This visual banner demonstrates how active notifications render at the top of merchant cash registers and web portals in real-time:
        </p>

        <div
          v-if="samplePreview"
          :style="getPreviewBannerStyle(samplePreview.level)"
          style="padding: 1rem 1.25rem; border-radius: 8px; display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;"
        >
          <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
            <svg v-if="samplePreview.level === 'critical_emergency'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
              <line x1="12" y1="9" x2="12" y2="13"></line>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <svg v-else-if="samplePreview.level === 'warning'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <div>
              <div style="font-weight: 700; font-size: 0.9375rem;">
                {{ samplePreview.title }}
              </div>
              <div style="font-size: 0.8125rem; margin-top: 0.25rem; opacity: 0.95;">
                {{ samplePreview.message }}
              </div>
            </div>
          </div>
          <div v-if="samplePreview.dismissible" style="opacity: 0.7; font-size: 0.75rem; border: 1px solid currentColor; border-radius: 4px; padding: 2px 6px; cursor: pointer;">
            Dismiss
          </div>
        </div>

        <div v-else style="padding: 1.5rem; text-align: center; color: #64748b; background: rgba(255, 255, 255, 0.02); border-radius: 8px;">
          No active announcement currently broadcasting to preview.
        </div>
      </div>

      <!-- Create / Edit Announcement Modal -->
      <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
        <div class="card" style="width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; padding: 1.5rem; border-color: rgba(255, 255, 255, 0.15);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h2 style="font-size: 1.25rem; font-weight: 700; color: #f8fafc;">
              {{ isEditing ? 'Edit Broadcast Announcement' : 'Publish Platform Broadcast' }}
            </h2>
            <button @click="closeModal" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 1rem;">
              ✕
            </button>
          </div>

          <form @submit.prevent="submitModal" style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                Announcement Title *
              </label>
              <input
                type="text"
                v-model="modalForm.title"
                required
                placeholder="e.g. FBR IRIS Live Gateway Maintenance Notice"
                class="form-control"
                style="width: 100%;"
              />
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                  Urgency Severity *
                </label>
                <select v-model="modalForm.level" class="form-control" style="width: 100%;" required>
                  <option value="info">Info (Blue)</option>
                  <option value="warning">Warning Notice (Amber)</option>
                  <option value="critical_emergency">Critical Emergency (Red)</option>
                </select>
              </div>

              <div>
                <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                  Target Audience *
                </label>
                <select v-model="modalForm.target_audience" class="form-control" style="width: 100%;" required>
                  <option value="all">All Merchants & POS</option>
                  <option value="province">Specific Province</option>
                  <option value="desktop_terminals_only">Desktop POS Terminals Only</option>
                  <option value="tenant">Single Specific Tenant</option>
                </select>
              </div>
            </div>

            <!-- Province Dropdown if audience == province -->
            <div v-if="modalForm.target_audience === 'province'">
              <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                Select Target Province *
              </label>
              <select v-model="modalForm.target_province" class="form-control" style="width: 100%;" required>
                <option value="">-- Choose Province --</option>
                <option value="punjab">Punjab (PRA)</option>
                <option value="sindh">Sindh (SRB)</option>
                <option value="kpk">Khyber Pakhtunkhwa (KPRA)</option>
                <option value="balochistan">Balochistan (BRA)</option>
                <option value="islamabad">Islamabad Capital Territory</option>
              </select>
            </div>

            <!-- Tenant Dropdown if audience == tenant -->
            <div v-if="modalForm.target_audience === 'tenant'">
              <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                Select Merchant Tenant *
              </label>
              <select v-model="modalForm.target_tenant_id" class="form-control" style="width: 100%;" required>
                <option value="">-- Choose Merchant --</option>
                <option v-for="t in tenants" :key="t.id" :value="t.id">
                  {{ t.name }} ({{ t.province || 'National' }})
                </option>
              </select>
            </div>

            <div>
              <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                Advisory Message Details *
              </label>
              <textarea
                v-model="modalForm.message"
                required
                rows="4"
                placeholder="Describe the maintenance window, statutory guidance, or required merchant actions..."
                class="form-control"
                style="width: 100%; resize: vertical;"
              ></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                  Scheduled Start Time
                </label>
                <input
                  type="datetime-local"
                  v-model="modalForm.starts_at"
                  class="form-control"
                  style="width: 100%;"
                />
              </div>

              <div>
                <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                  Expiration Time
                </label>
                <input
                  type="datetime-local"
                  v-model="modalForm.expires_at"
                  class="form-control"
                  style="width: 100%;"
                />
              </div>
            </div>

            <div style="display: flex; gap: 1.5rem; align-items: center; padding: 0.5rem 0;">
              <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.875rem; color: #cbd5e1;">
                <input type="checkbox" v-model="modalForm.dismissible" />
                <span>Allow Merchants to Dismiss</span>
              </label>

              <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.875rem; color: #cbd5e1;">
                <input type="checkbox" v-model="modalForm.is_active" />
                <span>Active Broadcast</span>
              </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
              <button type="button" @click="closeModal" class="btn btn-secondary">
                Cancel
              </button>
              <button
                type="submit"
                class="btn btn-primary"
                :disabled="isSubmitting"
              >
                {{ isSubmitting ? 'Publishing...' : (isEditing ? 'Save Changes' : 'Broadcast Alert') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  broadcasts: {
    type: Object,
    required: true,
  },
  metrics: {
    type: Object,
    required: true,
  },
  tenants: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const page = usePage();
const staffRole = computed(() => page.props.auth?.user?.staff_role || 'super_admin');
const isSuperAdmin = computed(() => staffRole.value === 'super_admin');

const filterForm = ref({
  q: props.filters.q || '',
  level: props.filters.level || '',
  status: props.filters.status || '',
});

function applyFilters() {
  router.get('/superadmin/broadcasts', filterForm.value, {
    preserveState: true,
    replace: true,
  });
}

function resetFilters() {
  filterForm.value = { q: '', level: '', status: '' };
  applyFilters();
}

// Sample preview banner: first active broadcast in the list or null
const samplePreview = computed(() => {
  return props.broadcasts.data.find(b => b.is_live) || props.broadcasts.data[0] || null;
});

// Modal state
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const isSubmitting = ref(false);

const modalForm = ref({
  title: '',
  message: '',
  level: 'info',
  target_audience: 'all',
  target_province: '',
  target_tenant_id: '',
  dismissible: true,
  is_active: true,
  starts_at: '',
  expires_at: '',
});

function openCreateModal() {
  isEditing.value = false;
  editingId.value = null;
  modalForm.value = {
    title: '',
    message: '',
    level: 'info',
    target_audience: 'all',
    target_province: '',
    target_tenant_id: '',
    dismissible: true,
    is_active: true,
    starts_at: '',
    expires_at: '',
  };
  showModal.value = true;
}

function openEditModal(b) {
  isEditing.value = true;
  editingId.value = b.id;
  modalForm.value = {
    title: b.title,
    message: b.message,
    level: b.level,
    target_audience: b.target_audience,
    target_province: b.target_province || '',
    target_tenant_id: b.target_tenant?.id || '',
    dismissible: b.dismissible,
    is_active: b.is_active,
    starts_at: b.starts_at ? b.starts_at.slice(0, 16) : '',
    expires_at: b.expires_at ? b.expires_at.slice(0, 16) : '',
  };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}

function submitModal() {
  isSubmitting.value = true;

  if (isEditing.value) {
    router.put(`/superadmin/broadcasts/${editingId.value}`, modalForm.value, {
      onSuccess: () => closeModal(),
      onFinish: () => { isSubmitting.value = false; },
    });
  } else {
    router.post('/superadmin/broadcasts', modalForm.value, {
      onSuccess: () => closeModal(),
      onFinish: () => { isSubmitting.value = false; },
    });
  }
}

function toggleActive(b) {
  router.post(`/superadmin/broadcasts/${b.id}/toggle-active`, {}, {
    preserveScroll: true,
  });
}

function deleteBroadcast(b) {
  if (confirm(`Are you sure you want to delete broadcast "${b.title}"?`)) {
    router.delete(`/superadmin/broadcasts/${b.id}`, {
      preserveScroll: true,
    });
  }
}

function formatLevel(level) {
  switch (level) {
    case 'critical_emergency':
      return 'Critical Emergency';
    case 'warning':
      return 'Warning Notice';
    default:
      return 'Information';
  }
}

function getLevelBadgeStyle(level) {
  switch (level) {
    case 'critical_emergency':
      return {
        background: 'rgba(239, 68, 68, 0.2)',
        color: '#f87171',
        border: '1px solid rgba(239, 68, 68, 0.4)',
      };
    case 'warning':
      return {
        background: 'rgba(245, 158, 11, 0.2)',
        color: '#fbbf24',
        border: '1px solid rgba(245, 158, 11, 0.4)',
      };
    default:
      return {
        background: 'rgba(59, 130, 246, 0.2)',
        color: '#60a5fa',
        border: '1px solid rgba(59, 130, 246, 0.4)',
      };
  }
}

function getPreviewBannerStyle(level) {
  switch (level) {
    case 'critical_emergency':
      return {
        background: 'linear-gradient(135deg, rgba(239, 68, 68, 0.95), rgba(185, 28, 28, 0.95))',
        color: '#ffffff',
        border: '1px solid #ef4444',
      };
    case 'warning':
      return {
        background: 'linear-gradient(135deg, rgba(217, 119, 6, 0.95), rgba(180, 83, 9, 0.95))',
        color: '#ffffff',
        border: '1px solid #f59e0b',
      };
    default:
      return {
        background: 'linear-gradient(135deg, rgba(37, 99, 235, 0.95), rgba(29, 78, 216, 0.95))',
        color: '#ffffff',
        border: '1px solid #3b82f6',
      };
  }
}

function truncateText(text, length) {
  if (!text) return '';
  return text.length > length ? text.substring(0, length) + '...' : text;
}

function formatDate(isoStr) {
  if (!isoStr) return '-';
  const d = new Date(isoStr);
  return d.toLocaleString('en-PK', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  z-index: 1000;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.4;
    transform: scale(1.3);
  }
}
</style>

