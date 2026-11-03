<template>
  <SuperAdminLayout title="Platform Security Hardening & Session Killswitch">
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
      <!-- Emergency Security Command Header -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
        <div>
          <h1 style="font-size: 1.5rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.25rem;">
            Security Hardening & Session Killswitch Terminal
          </h1>
          <p style="color: #94a3b8; font-size: 0.875rem;">
            Inspect live administrative sessions, enforce TOTP two-factor authentication, and execute platform-wide authentication killswitches.
          </p>
        </div>
        <div v-if="isSuperAdmin">
          <button
            @click="confirmKillAll"
            class="btn btn-danger"
            style="display: inline-flex; align-items: center; gap: 0.5rem; background: #dc2626; border-color: #b91c1c; padding: 0.6rem 1rem; font-weight: 700;"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
              <line x1="12" y1="2" x2="12" y2="12"></line>
            </svg>
            Emergency Killswitch: Revoke All Other Sessions
          </button>
        </div>
      </div>

      <!-- Security Telemetry KPI Cards -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Live Active Sessions
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #38bdf8; margin-top: 0.25rem;">
                {{ metrics.active_sessions_count }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(56, 189, 248, 0.15); border-radius: 8px; color: #38bdf8;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
            Current authenticated devices
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Staff 2FA Adoption
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #34d399; margin-top: 0.25rem;">
                {{ metrics.adoption_percentage }}%
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(52, 211, 153, 0.15); border-radius: 8px; color: #34d399;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #34d399; margin-top: 0.5rem;">
            {{ metrics.staff_2fa_count }} of {{ metrics.staff_count }} staff protected
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Platform 2FA Policy
              </div>
              <div style="font-size: 1.35rem; font-weight: 700; margin-top: 0.4rem;" :style="{ color: metrics.is_mandatory_enforced ? '#34d399' : '#fbbf24' }">
                {{ metrics.is_mandatory_enforced ? 'Mandatory' : 'Optional' }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(245, 158, 11, 0.15); border-radius: 8px; color: #fbbf24;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
            </div>
          </div>
          <div style="margin-top: 0.5rem;">
            <button
              v-if="isSuperAdmin"
              @click="toggleEnforcePolicy"
              class="btn btn-secondary"
              style="padding: 0.2rem 0.5rem; font-size: 0.72rem;"
            >
              Toggle Policy
            </button>
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                My Account 2FA Status
              </div>
              <div style="font-size: 1.35rem; font-weight: 700; margin-top: 0.4rem;" :style="{ color: user2fa.is_enabled ? '#34d399' : '#f87171' }">
                {{ user2fa.is_enabled ? 'Active (TOTP)' : 'Disabled' }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(239, 68, 68, 0.15); border-radius: 8px; color: #f87171;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
            {{ user2fa.is_enabled ? 'Protected with Authenticator' : 'Requires setup' }}
          </div>
        </div>
      </div>

      <!-- Tab Switcher -->
      <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding-bottom: 0.25rem;">
        <button
          @click="activeTab = 'sessions'"
          class="btn"
          :style="activeTab === 'sessions' ? 'background: rgba(56, 189, 248, 0.2); color: #38bdf8; border-color: rgba(56, 189, 248, 0.4);' : 'background: transparent; color: #94a3b8; border-color: transparent;'"
        >
          Live Sessions & Killswitch Terminal
        </button>

        <button
          @click="activeTab = '2fa'"
          class="btn"
          :style="activeTab === '2fa' ? 'background: rgba(52, 211, 153, 0.2); color: #34d399; border-color: rgba(52, 211, 153, 0.4);' : 'background: transparent; color: #94a3b8; border-color: transparent;'"
        >
          Two-Factor Authentication (TOTP)
        </button>
      </div>

      <!-- TAB 1: SESSIONS & KILLSWITCH -->
      <div v-if="activeTab === 'sessions'" class="card" style="overflow: hidden;">
        <div style="overflow-x: auto;">
          <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.02);">
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">User & Identity</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Role</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">IP Address</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Device / Browser</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Last Activity</th>
                <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8; text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="s in sessions"
                :key="s.id"
                style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);"
              >
                <!-- User -->
                <td style="padding: 1rem;">
                  <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="font-weight: 600; color: #f8fafc;">
                      {{ s.user?.name || 'Guest / Unauthenticated' }}
                    </div>
                    <span
                      v-if="s.is_current"
                      class="badge"
                      style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-size: 0.65rem; border: 1px solid #10b981;"
                    >
                      THIS DEVICE
                    </span>
                  </div>
                  <div style="font-size: 0.75rem; color: #64748b; font-family: monospace;">
                    {{ s.user?.email || s.id.slice(0, 16) + '...' }}
                  </div>
                </td>

                <!-- Role -->
                <td style="padding: 1rem;">
                  <span
                    v-if="s.user?.is_staff"
                    class="badge"
                    style="background: rgba(236, 72, 153, 0.15); color: #f472b6; font-size: 0.7rem; text-transform: uppercase;"
                  >
                    {{ s.user?.staff_role?.replace('_', ' ') || 'Staff' }}
                  </span>
                  <span
                    v-else
                    class="badge"
                    style="background: rgba(148, 163, 184, 0.15); color: #cbd5e1; font-size: 0.7rem; text-transform: capitalize;"
                  >
                    {{ s.user?.role || 'Guest' }}
                  </span>
                </td>

                <!-- IP Address -->
                <td style="padding: 1rem; font-family: monospace; font-size: 0.8125rem; color: #38bdf8;">
                  {{ s.ip_address || '127.0.0.1' }}
                </td>

                <!-- Device / Browser -->
                <td style="padding: 1rem; font-size: 0.8125rem; color: #cbd5e1;">
                  {{ s.browser }}
                </td>

                <!-- Last Activity -->
                <td style="padding: 1rem; font-size: 0.8125rem;">
                  <div style="color: #f8fafc; font-weight: 500;">{{ s.last_activity_human }}</div>
                  <div style="font-size: 0.7rem; color: #64748b;">{{ formatDate(s.last_activity_at) }}</div>
                </td>

                <!-- Action -->
                <td style="padding: 1rem; text-align: right;">
                  <button
                    v-if="!s.is_current && isSuperAdmin"
                    @click="killSession(s)"
                    class="btn btn-danger"
                    style="padding: 0.25rem 0.6rem; font-size: 0.75rem;"
                  >
                    Kill Session
                  </button>
                  <span v-else-if="s.is_current" style="color: #64748b; font-size: 0.75rem;">
                    Active
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 2: TWO-FACTOR AUTHENTICATION -->
      <div v-else-if="activeTab === '2fa'" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; align-items: flex-start;">
        <!-- Left: Current User 2FA Manager -->
        <div class="card" style="padding: 1.5rem;">
          <h3 style="font-size: 1.15rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.5rem;">
            Two-Factor Authentication (TOTP)
          </h3>
          <p style="font-size: 0.8125rem; color: #94a3b8; margin-bottom: 1.25rem;">
            Protect your staff account using Google Authenticator, Microsoft Authenticator, or 1Password.
          </p>

          <!-- If 2FA already enabled -->
          <div v-if="user2fa.is_enabled" style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 1rem; display: flex; align-items: center; gap: 0.75rem;">
              <div style="padding: 0.35rem; background: #10b981; border-radius: 50%; color: #000;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
              </div>
              <div>
                <div style="font-weight: 700; color: #34d399; font-size: 0.9375rem;">Two-Factor Authentication is ACTIVE</div>
                <div style="font-size: 0.75rem; color: #94a3b8;">Confirmed on {{ formatDate(user2fa.confirmed_at) }}</div>
              </div>
            </div>

            <!-- Recovery Codes List -->
            <div v-if="user2fa.recovery_codes && user2fa.recovery_codes.length > 0" style="background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 1rem;">
              <div style="font-size: 0.8125rem; font-weight: 700; color: #fbbf24; margin-bottom: 0.5rem;">
                Emergency Backup Recovery Codes
              </div>
              <p style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.75rem;">
                Store these recovery codes in a secure location. Each code can be used once if you lose device access.
              </p>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; font-family: monospace; font-size: 0.8125rem; color: #f8fafc;">
                <div v-for="rc in user2fa.recovery_codes" :key="rc" style="background: rgba(255, 255, 255, 0.05); padding: 0.25rem 0.5rem; border-radius: 4px; text-align: center;">
                  {{ rc }}
                </div>
              </div>
            </div>

            <div style="margin-top: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 1rem;">
              <button
                @click="disable2fa"
                class="btn btn-danger"
                style="font-size: 0.8125rem;"
              >
                Disable Two-Factor Authentication
              </button>
            </div>
          </div>

          <!-- If 2FA not enabled -->
          <div v-else style="display: flex; flex-direction: column; gap: 1rem;">
            <div v-if="!user2fa.secret">
              <p style="font-size: 0.875rem; color: #cbd5e1; margin-bottom: 1rem;">
                When two-factor authentication is enabled, you will be prompted for a secure 6-digit pin during login.
              </p>
              <button
                @click="setup2fa"
                class="btn btn-primary"
                style="background: #10b981; border-color: #059669;"
                :disabled="isSubmitting"
              >
                Set Up Two-Factor Authentication
              </button>
            </div>

            <!-- Setup in progress (Secret generated) -->
            <div v-else style="display: flex; flex-direction: column; gap: 1rem;">
              <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid #3b82f6; border-radius: 8px; padding: 1rem;">
                <div style="font-weight: 700; color: #60a5fa; font-size: 0.875rem; margin-bottom: 0.35rem;">
                  Step 1: Enter Secret Key in Authenticator
                </div>
                <div style="font-size: 0.8125rem; color: #cbd5e1; margin-bottom: 0.75rem;">
                  Open your authenticator app (Google Authenticator, Microsoft Authenticator, 1Password) and add account manually with this secret:
                </div>
                <div style="background: #0f172a; padding: 0.6rem 0.85rem; border-radius: 6px; font-family: monospace; font-size: 1.1rem; color: #38bdf8; letter-spacing: 0.1em; text-align: center; border: 1px dashed #38bdf8;">
                  {{ user2fa.secret }}
                </div>
              </div>

              <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 1rem;">
                <div style="font-weight: 700; color: #f8fafc; font-size: 0.875rem; margin-bottom: 0.5rem;">
                  Step 2: Confirm 6-Digit Code
                </div>
                <form @submit.prevent="confirm2fa" style="display: flex; gap: 0.5rem;">
                  <input
                    type="text"
                    v-model="verificationCode"
                    maxlength="6"
                    required
                    placeholder="123456"
                    class="form-control"
                    style="flex: 1; font-family: monospace; font-size: 1.1rem; letter-spacing: 0.15em; text-align: center;"
                  />
                  <button
                    type="submit"
                    class="btn btn-primary"
                    :disabled="isSubmitting || verificationCode.length < 6"
                  >
                    Confirm & Activate
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Platform Policy Guidelines -->
        <div class="card" style="padding: 1.5rem;">
          <h3 style="font-size: 1.15rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.5rem;">
            Security & Compliance Best Practices
          </h3>
          <p style="font-size: 0.8125rem; color: #94a3b8; margin-bottom: 1.25rem;">
            Standard Operating Procedures (SOPs) for PK-POS Super Admin operations.
          </p>

          <div style="display: flex; flex-direction: column; gap: 1rem; font-size: 0.8125rem; color: #cbd5e1;">
            <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
              <span style="color: #10b981; font-weight: 700;">✓</span>
              <div>
                <strong style="color: #f8fafc;">Mandatory 2FA Policy:</strong> When enforced, all internal staff accounts are prevented from accessing fiscal endpoints and terminal killswitches without an active TOTP device.
              </div>
            </div>

            <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
              <span style="color: #38bdf8; font-weight: 700;">✓</span>
              <div>
                <strong style="color: #f8fafc;">Session Killswitch:</strong> Used during active incident response or credential leak. Revoking a session destroys the PostgreSQL token, invalidating all subsequent API queries.
              </div>
            </div>

            <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
              <span style="color: #a78bfa; font-weight: 700;">✓</span>
              <div>
                <strong style="color: #f8fafc;">Full Audit Logging:</strong> Every session kill, 2FA toggle, and emergency evacuation writes immutable security logs to the platform audit trail.
              </div>
            </div>
          </div>
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
  sessions: {
    type: Array,
    required: true,
  },
  metrics: {
    type: Object,
    required: true,
  },
  user2fa: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const staffRole = computed(() => page.props.auth?.user?.staff_role || 'super_admin');
const isSuperAdmin = computed(() => staffRole.value === 'super_admin');

const activeTab = ref('sessions');
const isSubmitting = ref(false);
const verificationCode = ref('');

function killSession(s) {
  if (confirm(`Terminate session for ${s.user?.name || s.ip_address}? The user will be logged out immediately.`)) {
    router.post(`/superadmin/security/sessions/${s.id}/kill`);
  }
}

function confirmKillAll() {
  if (confirm('CRITICAL ACTION: Execute emergency platform killswitch? This will immediately terminate all active staff and merchant sessions across Pakistan except your current device.')) {
    router.post('/superadmin/security/sessions/kill-all');
  }
}

function setup2fa() {
  isSubmitting.value = true;
  router.post('/superadmin/security/2fa/setup', {}, {
    onFinish: () => { isSubmitting.value = false; },
  });
}

function confirm2fa() {
  isSubmitting.value = true;
  router.post('/superadmin/security/2fa/confirm', {
    code: verificationCode.value,
  }, {
    onFinish: () => { isSubmitting.value = false; },
  });
}

function disable2fa() {
  if (confirm('Are you sure you want to disable Two-Factor Authentication on your account?')) {
    router.post('/superadmin/security/2fa/disable');
  }
}

function toggleEnforcePolicy() {
  if (confirm('Toggle platform-wide mandatory 2FA enforcement policy for all staff members?')) {
    router.post('/superadmin/security/enforce-2fa');
  }
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

