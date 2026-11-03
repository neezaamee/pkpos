<template>
  <SuperAdminLayout title="Release Management & Desktop OTA">
    <!-- Header Summary -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h2 style="font-size: 1.25rem; font-weight: 700;">Releases, Changelog & Tauri OTA Updates</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          Publish desktop POS binaries, manage version changelogs, distribute OTA manifests, and enforce minimum client versions.
        </p>
      </div>
      <div style="display: flex; gap: 0.75rem; align-items: center;">
        <button @click="openCreateModal" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.4rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="16"></line>
            <line x1="8" y1="12" x2="16" y2="12"></line>
          </svg>
          Publish New Release
        </button>
      </div>
    </div>

    <!-- Release Metrics KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Latest Desktop Version
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #38bdf8; font-family: monospace;">
          v{{ metrics.latest_desktop_version }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Active production distribution
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Min Supported Version
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: #10b981; font-family: monospace;">
          v{{ metrics.min_supported_version }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Mandatory compliance baseline
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Outdated Fleet Registers
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem;" :style="{ color: metrics.outdated_terminals_count > 0 ? '#f59e0b' : '#10b981' }">
          {{ metrics.outdated_terminals_count }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Terminals running older releases
        </div>
      </div>

      <div class="card-table" style="padding: 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">
          Total Releases
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; margin-top: 0.35rem; color: var(--text-main);">
          {{ metrics.total_releases }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
          Published release artifacts
        </div>
      </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card-table" style="padding: 1.25rem; margin-bottom: 1.5rem;">
      <form @submit.prevent="applyFilters" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Search Version / Keyword</label>
          <input
            v-model="filterForm.search"
            type="text"
            class="form-input"
            placeholder="e.g. 0.2.0, FBR, hotfix..."
          />
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Platform</label>
          <select v-model="filterForm.target_platform" class="form-input">
            <option value="">All Platforms</option>
            <option value="desktop_windows">Desktop Windows (Tauri)</option>
            <option value="desktop_linux">Desktop Linux</option>
            <option value="desktop_macos">Desktop macOS</option>
            <option value="cloud_backend">Cloud Backend API</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Release Channel</label>
          <select v-model="filterForm.release_channel" class="form-input">
            <option value="">All Channels</option>
            <option value="stable">Stable (Production)</option>
            <option value="beta">Beta / Pilot</option>
            <option value="nightly">Nightly Dev</option>
          </select>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
          <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.55rem 0.85rem;">
            Filter
          </button>
          <button type="button" @click="resetFilters" class="btn btn-secondary" style="padding: 0.55rem 0.85rem;">
            Reset
          </button>
        </div>
      </form>
    </div>

    <!-- Releases Table Card -->
    <div class="card-table">
      <div class="table-header">
        <h2>Published Release Packages</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">
          Showing {{ releases.from || 0 }} to {{ releases.to || 0 }} of {{ releases.total }} releases
        </span>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Version</th>
              <th>Release Details & Changelog</th>
              <th>Platform / Target</th>
              <th>Min Required</th>
              <th>Artifact & OTA</th>
              <th>Status</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in releases.data" :key="item.id">
              <td>
                <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                  <span style="font-family: monospace; font-size: 1rem; font-weight: 700; color: #38bdf8;">
                    v{{ item.version }}
                  </span>
                  <div style="display: flex; gap: 0.3rem; flex-wrap: wrap;">
                    <span class="badge" :class="item.release_channel === 'stable' ? 'badge-success' : 'badge-warning'" style="font-size: 0.68rem;">
                      {{ item.release_channel }}
                    </span>
                    <span v-if="item.is_critical" class="badge badge-danger" style="font-size: 0.68rem;">
                      Critical
                    </span>
                  </div>
                </div>
              </td>
              <td>
                <div style="font-weight: 600; font-size: 0.92rem;">{{ item.title }}</div>
                <div v-if="item.summary" style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem; max-width: 440px;">
                  {{ item.summary }}
                </div>
                <button
                  v-if="item.changelog_markdown"
                  @click="openChangelogModal(item)"
                  class="btn btn-secondary"
                  style="margin-top: 0.4rem; padding: 0.15rem 0.45rem; font-size: 0.7rem;"
                >
                  View Release Notes
                </button>
              </td>
              <td>
                <div style="font-weight: 500; font-size: 0.85rem;">
                  {{ formatPlatform(item.target_platform) }}
                </div>
                <div v-if="item.tauri_target" style="font-size: 0.72rem; color: var(--text-muted); font-family: monospace;">
                  {{ item.tauri_target }}
                </div>
              </td>
              <td>
                <span v-if="item.min_supported_version" style="font-family: monospace; font-size: 0.82rem; color: #10b981; font-weight: 600;">
                  ≥ v{{ item.min_supported_version }}
                </span>
                <span v-else style="color: var(--text-muted); font-size: 0.75rem;">None</span>
              </td>
              <td>
                <div v-if="item.download_url" style="display: flex; flex-direction: column; gap: 0.2rem;">
                  <a :href="item.download_url" target="_blank" style="font-size: 0.78rem; color: #60a5fa; text-decoration: underline; display: flex; align-items: center; gap: 0.3rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                      <polyline points="7 10 12 15 17 10"></polyline>
                      <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Installer (.msi)
                  </a>
                  <span v-if="item.signature" style="font-size: 0.68rem; color: #10b981;">
                    &bull; Signed Package
                  </span>
                </div>
                <span v-else style="font-size: 0.75rem; color: var(--text-muted);">No binary URL</span>
              </td>
              <td>
                <span class="badge" :class="item.is_published ? 'badge-success' : 'badge-neutral'">
                  {{ item.is_published ? 'Published' : 'Draft' }}
                </span>
                <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.2rem;">
                  {{ item.published_at }}
                </div>
              </td>
              <td style="text-align: right;">
                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                  <button
                    @click="togglePublish(item)"
                    class="btn btn-secondary"
                    style="padding: 0.25rem 0.55rem; font-size: 0.75rem;"
                  >
                    {{ item.is_published ? 'Unpublish' : 'Publish' }}
                  </button>
                  <button @click="openEditModal(item)" class="btn btn-secondary" style="padding: 0.25rem 0.55rem; font-size: 0.75rem;">
                    Edit
                  </button>
                  <button @click="deleteRelease(item)" class="btn btn-secondary" style="padding: 0.25rem 0.45rem; font-size: 0.75rem; color: #ef4444;">
                    &times;
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="releases.data.length === 0">
              <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                No release packages found matching the selected filters.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="releases.links && releases.links.length > 3" style="display: flex; justify-content: flex-end; padding: 1rem; gap: 0.35rem; border-top: 1px solid var(--border-color);">
        <Link
          v-for="(link, i) in releases.links"
          :key="i"
          :href="link.url || '#'"
          class="btn btn-secondary"
          :class="{ 'btn-primary': link.active, disabled: !link.url }"
          style="padding: 0.35rem 0.65rem; font-size: 0.75rem;"
          v-html="link.label"
        />
      </div>
    </div>

    <!-- Create / Edit Release Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-container" style="max-width: 640px;">
        <div class="modal-header">
          <h3>{{ editingItem ? 'Edit Release Package' : 'Publish New Version Release' }}</h3>
          <button @click="showModal = false" class="close-btn">&times;</button>
        </div>
        <form @submit.prevent="submitForm">
          <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem; max-height: 75vh; overflow-y: auto;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Version String (e.g. 0.2.1) *</label>
                <input
                  v-model="form.version"
                  type="text"
                  class="form-input"
                  placeholder="0.2.1"
                  required
                />
                <span v-if="form.errors.version" class="form-error">{{ form.errors.version }}</span>
              </div>

              <div class="form-group">
                <label class="form-label">Release Channel *</label>
                <select v-model="form.release_channel" class="form-input" required>
                  <option value="stable">Stable (Production)</option>
                  <option value="beta">Beta / Preview</option>
                  <option value="nightly">Nightly Dev</option>
                </select>
                <span v-if="form.errors.release_channel" class="form-error">{{ form.errors.release_channel }}</span>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Target Platform *</label>
                <select v-model="form.target_platform" class="form-input" required>
                  <option value="desktop_windows">Desktop Windows (Tauri)</option>
                  <option value="desktop_linux">Desktop Linux</option>
                  <option value="desktop_macos">Desktop macOS</option>
                  <option value="cloud_backend">Cloud Backend API</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Tauri Target Arch</label>
                <input
                  v-model="form.tauri_target"
                  type="text"
                  class="form-input"
                  placeholder="windows-x86_64"
                />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Release Title *</label>
              <input
                v-model="form.title"
                type="text"
                class="form-input"
                placeholder="e.g. FBR SRO Compliance Patch & Offline Sync"
                required
              />
              <span v-if="form.errors.title" class="form-error">{{ form.errors.title }}</span>
            </div>

            <div class="form-group">
              <label class="form-label">Brief Summary</label>
              <textarea
                v-model="form.summary"
                rows="2"
                class="form-input"
                placeholder="Short 1-2 sentence description for notification badges..."
              ></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Changelog (Markdown)</label>
              <textarea
                v-model="form.changelog_markdown"
                rows="5"
                class="form-input"
                style="font-family: monospace; font-size: 0.8rem;"
                placeholder="### Features&#10;- New feature details...&#10;&#10;### FBR Fiscal Updates&#10;- Tax rule updates...&#10;&#10;### Bug Fixes&#10;- Fix details..."
              ></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Download Installer URL</label>
              <input
                v-model="form.download_url"
                type="url"
                class="form-input"
                placeholder="https://releases.pkpos.pk/downloads/pk-pos-setup-0.2.1.msi"
              />
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Tauri Signature (minisign)</label>
                <input
                  v-model="form.signature"
                  type="text"
                  class="form-input"
                  placeholder="dW50cnVzdGVkIGNvbW1lbnQ..."
                />
              </div>

              <div class="form-group">
                <label class="form-label">Min Supported Version</label>
                <input
                  v-model="form.min_supported_version"
                  type="text"
                  class="form-input"
                  placeholder="0.1.0"
                />
              </div>
            </div>

            <div style="display: flex; gap: 1.5rem; margin-top: 0.5rem; flex-wrap: wrap;">
              <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" v-model="form.is_critical" />
                <span style="font-size: 0.85rem; color: #ef4444; font-weight: 600;">Critical mandatory update</span>
              </label>

              <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" v-model="form.is_published" />
                <span style="font-size: 0.85rem;">Publish immediately</span>
              </label>
            </div>
          </div>

          <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--border-color);">
            <button type="button" @click="showModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">
              {{ form.processing ? 'Saving...' : (editingItem ? 'Update Release' : 'Publish Release') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- View Changelog Modal -->
    <div v-if="showChangelogModal" class="modal-overlay" @click.self="showChangelogModal = false">
      <div class="modal-container" style="max-width: 580px;">
        <div class="modal-header">
          <h3>Release Notes — v{{ viewingItem?.version }}</h3>
          <button @click="showChangelogModal = false" class="close-btn">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 65vh; overflow-y: auto;">
          <h4 style="margin-top: 0; color: #38bdf8;">{{ viewingItem?.title }}</h4>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
            Published: {{ viewingItem?.published_at }} &bull; Platform: {{ formatPlatform(viewingItem?.target_platform) }}
          </p>
          <pre style="white-space: pre-wrap; font-family: inherit; font-size: 0.85rem; line-height: 1.6; background: rgba(0,0,0,0.2); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">{{ viewingItem?.changelog_markdown }}</pre>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; padding: 1rem; border-top: 1px solid var(--border-color);">
          <button @click="showChangelogModal = false" class="btn btn-secondary">Close</button>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  releases: Object,
  metrics: Object,
  filters: Object,
});

const filterForm = reactive({
  target_platform: props.filters?.target_platform || '',
  release_channel: props.filters?.release_channel || '',
  search: props.filters?.search || '',
});

function applyFilters() {
  router.get('/superadmin/releases', filterForm, { preserveState: true, replace: true });
}

function resetFilters() {
  filterForm.target_platform = '';
  filterForm.release_channel = '';
  filterForm.search = '';
  applyFilters();
}

// Modal State
const showModal = ref(false);
const editingItem = ref(null);

const form = useForm({
  version: '',
  release_channel: 'stable',
  target_platform: 'desktop_windows',
  tauri_target: 'windows-x86_64',
  title: '',
  summary: '',
  changelog_markdown: '',
  download_url: '',
  signature: '',
  sha256_checksum: '',
  min_supported_version: '0.1.0',
  is_critical: false,
  is_published: true,
});

function openCreateModal() {
  editingItem.value = null;
  form.reset();
  form.clearErrors();
  form.version = '';
  form.release_channel = 'stable';
  form.target_platform = 'desktop_windows';
  form.tauri_target = 'windows-x86_64';
  form.title = '';
  form.summary = '';
  form.changelog_markdown = "### Features\n- \n\n### FBR Fiscal Updates\n- \n\n### Bug Fixes\n- ";
  form.download_url = '';
  form.signature = '';
  form.sha256_checksum = '';
  form.min_supported_version = props.metrics?.min_supported_version || '0.1.0';
  form.is_critical = false;
  form.is_published = true;
  showModal.value = true;
}

function openEditModal(item) {
  editingItem.value = item;
  form.clearErrors();
  form.version = item.version;
  form.release_channel = item.release_channel;
  form.target_platform = item.target_platform;
  form.tauri_target = item.tauri_target || 'windows-x86_64';
  form.title = item.title;
  form.summary = item.summary || '';
  form.changelog_markdown = item.changelog_markdown || '';
  form.download_url = item.download_url || '';
  form.signature = item.signature || '';
  form.sha256_checksum = item.sha256_checksum || '';
  form.min_supported_version = item.min_supported_version || '';
  form.is_critical = item.is_critical;
  form.is_published = item.is_published;
  showModal.value = true;
}

function submitForm() {
  if (editingItem.value) {
    form.put(`/superadmin/releases/${editingItem.value.id}`, {
      onSuccess: () => {
        showModal.value = false;
      },
    });
  } else {
    form.post('/superadmin/releases', {
      onSuccess: () => {
        showModal.value = false;
      },
    });
  }
}

function togglePublish(item) {
  router.post(`/superadmin/releases/${item.id}/publish`);
}

function deleteRelease(item) {
  if (confirm(`Are you sure you want to delete release 'v${item.version}'?`)) {
    router.delete(`/superadmin/releases/${item.id}`);
  }
}

// Changelog view modal
const showChangelogModal = ref(false);
const viewingItem = ref(null);

function openChangelogModal(item) {
  viewingItem.value = item;
  showChangelogModal.value = true;
}

function formatPlatform(p) {
  const map = {
    desktop_windows: 'Desktop (Windows)',
    desktop_linux: 'Desktop (Linux)',
    desktop_macos: 'Desktop (macOS)',
    cloud_backend: 'Cloud Backend API',
  };
  return map[p] || p;
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 1.5rem;
}

.modal-container {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  width: 100%;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
  animation: modalIn 0.15s ease-out;
}

@keyframes modalIn {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0;
}

.close-btn {
  background: transparent;
  border: none;
  font-size: 1.5rem;
  line-height: 1;
  color: var(--text-muted);
  cursor: pointer;
}

.close-btn:hover {
  color: var(--text-main);
}

.modal-body {
  padding: 1.5rem;
}

.form-error {
  color: #ef4444;
  font-size: 0.75rem;
  margin-top: 0.25rem;
  display: block;
}
</style>

