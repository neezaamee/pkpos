<template>
  <SuperAdminLayout title="Platform Settings & Payment Gateways">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin: 0 0 0.35rem 0;">
          Platform Settings & Payment Gateways
        </h1>
        <p style="color: var(--text-muted); margin: 0; font-size: 0.9rem;">
          Configure live payment gateways, platform branding, receipt templates, and regional defaults.
        </p>
      </div>

      <div style="display: flex; gap: 0.75rem;">
        <button
          type="button"
          @click="submitAllSettings"
          class="btn btn-primary"
          :disabled="form.processing"
          style="display: flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.25rem;"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
            <polyline points="17 21 17 13 7 13 7 21"></polyline>
            <polyline points="7 3 7 8 15 8"></polyline>
          </svg>
          {{ form.processing ? 'Saving...' : 'Save Settings' }}
        </button>
      </div>
    </div>

    <!-- Group Navigation Tabs -->
    <div class="tab-nav-bar">
      <button :class="{ active: currentTab === 'gateways' }" @click="currentTab = 'gateways'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
          <line x1="1" y1="10" x2="23" y2="10"></line>
        </svg>
        Payment Gateways
      </button>

      <button :class="{ active: currentTab === 'defaults' }" @click="currentTab = 'defaults'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="2" y1="12" x2="22" y2="12"></line>
          <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
        </svg>
        Platform Branding & Localization
      </button>

      <button :class="{ active: currentTab === 'receipts' }" @click="currentTab = 'receipts'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
          <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
        Thermal Receipts & Policies
      </button>

      <button :class="{ active: currentTab === 'support' }" @click="currentTab = 'support'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
        </svg>
        Support Channels
      </button>

      <button :class="{ active: currentTab === 'audit' }" @click="currentTab = 'audit'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
        </svg>
        Audit History
      </button>
    </div>

    <!-- TAB 1: PAYMENT GATEWAYS -->
    <div v-show="currentTab === 'gateways'">
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        
        <!-- Stripe Card -->
        <div class="setting-card">
          <div class="setting-card-header" style="border-left: 4px solid #6366f1;">
            <div>
              <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0 0 0.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
                Stripe Payments
                <span class="badge" :class="getSettingValue('gateway_stripe_enabled') ? 'badge-active' : 'badge-inactive'">
                  {{ getSettingValue('gateway_stripe_enabled') ? 'Active' : 'Disabled' }}
                </span>
              </h3>
              <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">International Visa/Mastercard processing</p>
            </div>
            <button
              type="button"
              @click="testGateway('stripe')"
              class="btn btn-secondary"
              style="font-size: 0.8rem; padding: 0.35rem 0.65rem;"
              :disabled="testingGateway === 'stripe'"
            >
              {{ testingGateway === 'stripe' ? 'Testing...' : 'Test Connection' }}
            </button>
          </div>
          <div class="setting-card-body">
            <div class="form-check-group">
              <label class="switch-label">
                <input type="checkbox" v-model="settingModels['gateway_stripe_enabled']" />
                <span>Enable Stripe Checkout for Subscriptions</span>
              </label>
            </div>

            <div class="form-group">
              <label class="form-label">Publishable Key</label>
              <input
                type="text"
                v-model="settingModels['gateway_stripe_publishable_key']"
                class="form-input"
                placeholder="pk_test_... or pk_live_..."
              />
            </div>

            <div class="form-group">
              <label class="form-label">
                Secret Key
                <span v-if="hasSettingSecret('gateway_stripe_secret_key')" class="secret-indicator">Configured & Encrypted</span>
              </label>
              <input
                type="password"
                v-model="settingModels['gateway_stripe_secret_key']"
                class="form-input"
                :placeholder="getSettingMask('gateway_stripe_secret_key') || 'sk_test_... or sk_live_...'"
              />
              <span class="form-hint">Stored with AES-256-CBC encryption. Leave blank to retain existing key.</span>
            </div>

            <div class="form-group">
              <label class="form-label">
                Webhook Signing Secret
                <span v-if="hasSettingSecret('gateway_stripe_webhook_secret')" class="secret-indicator">Configured</span>
              </label>
              <input
                type="password"
                v-model="settingModels['gateway_stripe_webhook_secret']"
                class="form-input"
                :placeholder="getSettingMask('gateway_stripe_webhook_secret') || 'whsec_...'"
              />
            </div>
          </div>
        </div>

        <!-- JazzCash Card -->
        <div class="setting-card">
          <div class="setting-card-header" style="border-left: 4px solid #f59e0b;">
            <div>
              <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0 0 0.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
                JazzCash Merchant Portal
                <span class="badge" :class="getSettingValue('gateway_jazzcash_enabled') ? 'badge-active' : 'badge-inactive'">
                  {{ getSettingValue('gateway_jazzcash_enabled') ? 'Active' : 'Disabled' }}
                </span>
              </h3>
              <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">JazzCash Mobile Wallet & Voucher payments</p>
            </div>
            <button
              type="button"
              @click="testGateway('jazzcash')"
              class="btn btn-secondary"
              style="font-size: 0.8rem; padding: 0.35rem 0.65rem;"
              :disabled="testingGateway === 'jazzcash'"
            >
              {{ testingGateway === 'jazzcash' ? 'Testing...' : 'Verify Setup' }}
            </button>
          </div>
          <div class="setting-card-body">
            <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
              <label class="switch-label">
                <input type="checkbox" v-model="settingModels['gateway_jazzcash_enabled']" />
                <span>Enable JazzCash</span>
              </label>
              <label class="switch-label">
                <input type="checkbox" v-model="settingModels['gateway_jazzcash_sandbox']" />
                <span>Sandbox Mode</span>
              </label>
            </div>

            <div class="form-group">
              <label class="form-label">Merchant ID</label>
              <input
                type="text"
                v-model="settingModels['gateway_jazzcash_merchant_id']"
                class="form-input"
                placeholder="MCXXXXX"
              />
            </div>

            <div class="form-group">
              <label class="form-label">
                API Password
                <span v-if="hasSettingSecret('gateway_jazzcash_password')" class="secret-indicator">Configured</span>
              </label>
              <input
                type="password"
                v-model="settingModels['gateway_jazzcash_password']"
                class="form-input"
                :placeholder="getSettingMask('gateway_jazzcash_password') || 'Enter JazzCash API password'"
              />
            </div>

            <div class="form-group">
              <label class="form-label">
                Integrity Salt / Hash Key
                <span v-if="hasSettingSecret('gateway_jazzcash_hash_key')" class="secret-indicator">Configured</span>
              </label>
              <input
                type="password"
                v-model="settingModels['gateway_jazzcash_hash_key']"
                class="form-input"
                :placeholder="getSettingMask('gateway_jazzcash_hash_key') || 'HMAC SHA256 Salt'"
              />
            </div>
          </div>
        </div>

        <!-- EasyPaisa Card -->
        <div class="setting-card">
          <div class="setting-card-header" style="border-left: 4px solid #10b981;">
            <div>
              <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0 0 0.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
                EasyPaisa Gateway
                <span class="badge" :class="getSettingValue('gateway_easypaisa_enabled') ? 'badge-active' : 'badge-inactive'">
                  {{ getSettingValue('gateway_easypaisa_enabled') ? 'Active' : 'Disabled' }}
                </span>
              </h3>
              <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Telenor Bank EasyPaisa OTC & MA processing</p>
            </div>
            <button
              type="button"
              @click="testGateway('easypaisa')"
              class="btn btn-secondary"
              style="font-size: 0.8rem; padding: 0.35rem 0.65rem;"
              :disabled="testingGateway === 'easypaisa'"
            >
              {{ testingGateway === 'easypaisa' ? 'Testing...' : 'Verify Setup' }}
            </button>
          </div>
          <div class="setting-card-body">
            <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
              <label class="switch-label">
                <input type="checkbox" v-model="settingModels['gateway_easypaisa_enabled']" />
                <span>Enable EasyPaisa</span>
              </label>
              <label class="switch-label">
                <input type="checkbox" v-model="settingModels['gateway_easypaisa_sandbox']" />
                <span>Sandbox Mode</span>
              </label>
            </div>

            <div class="form-group">
              <label class="form-label">Store ID</label>
              <input
                type="text"
                v-model="settingModels['gateway_easypaisa_store_id']"
                class="form-input"
                placeholder="e.g. 12345"
              />
            </div>

            <div class="form-group">
              <label class="form-label">
                Hash Key / Secret
                <span v-if="hasSettingSecret('gateway_easypaisa_hash_key')" class="secret-indicator">Configured</span>
              </label>
              <input
                type="password"
                v-model="settingModels['gateway_easypaisa_hash_key']"
                class="form-input"
                :placeholder="getSettingMask('gateway_easypaisa_hash_key') || 'Enter EasyPaisa Hash Key'"
              />
            </div>
          </div>
        </div>

        <!-- Direct Bank Wire & 1Bill Card -->
        <div class="setting-card">
          <div class="setting-card-header" style="border-left: 4px solid #38bdf8;">
            <div>
              <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0 0 0.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
                Bank Wire & 1Bill / Kuickpay
                <span class="badge" :class="getSettingValue('gateway_bank_wire_enabled') ? 'badge-active' : 'badge-inactive'">
                  {{ getSettingValue('gateway_bank_wire_enabled') ? 'Active' : 'Disabled' }}
                </span>
              </h3>
              <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Direct IBFT and 1Link Consumer Bill payments</p>
            </div>
          </div>
          <div class="setting-card-body">
            <div class="form-check-group" style="margin-bottom: 1rem;">
              <label class="switch-label">
                <input type="checkbox" v-model="settingModels['gateway_bank_wire_enabled']" />
                <span>Enable Direct Wire Transfers & 1Bill Invoicing</span>
              </label>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
              <div class="form-group">
                <label class="form-label">Bank Name</label>
                <input
                  type="text"
                  v-model="settingModels['gateway_bank_wire_bank_name']"
                  class="form-input"
                  placeholder="e.g. Meezan Bank"
                />
              </div>

              <div class="form-group">
                <label class="form-label">Account Title</label>
                <input
                  type="text"
                  v-model="settingModels['gateway_bank_wire_account_title']"
                  class="form-input"
                  placeholder="Official Account Title"
                />
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0.75rem;">
              <div class="form-group">
                <label class="form-label">IBAN</label>
                <input
                  type="text"
                  v-model="settingModels['gateway_bank_wire_iban']"
                  class="form-input"
                  placeholder="PK00MEZN..."
                />
              </div>

              <div class="form-group">
                <label class="form-label">1Bill Prefix</label>
                <input
                  type="text"
                  v-model="settingModels['gateway_bank_wire_1bill_prefix']"
                  class="form-input"
                  placeholder="100123"
                />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Payment Instructions</label>
              <textarea
                v-model="settingModels['gateway_bank_wire_instructions']"
                class="form-input"
                rows="3"
                placeholder="Narrative instructions for bank wire transfers..."
              ></textarea>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- TAB 2: PLATFORM DEFAULTS & BRANDING -->
    <div v-show="currentTab === 'defaults'">
      <div class="setting-card" style="max-width: 800px; margin-bottom: 2rem;">
        <div class="setting-card-header">
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0 0 0.25rem 0;">Platform Branding & Environment</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Global operating environment parameters</p>
          </div>
        </div>
        <div class="setting-card-body">
          <div class="form-group">
            <label class="form-label">Platform Brand Name</label>
            <input
              type="text"
              v-model="settingModels['platform_name']"
              class="form-input"
              placeholder="01POS Cloud"
            />
            <span class="form-hint">Used in automated transactional emails, page headers, and payment summaries.</span>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label class="form-label">Base Currency</label>
              <select v-model="settingModels['platform_default_currency']" class="form-input">
                <option value="PKR">Pakistani Rupee (PKR)</option>
                <option value="USD">US Dollar (USD)</option>
                <option value="EUR">Euro (EUR)</option>
                <option value="AED">UAE Dirham (AED)</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Default Language</label>
              <select v-model="settingModels['platform_default_locale']" class="form-input">
                <option value="en">English (Default)</option>
                <option value="ur">Urdu (اردو)</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">System Timezone</label>
              <select v-model="settingModels['platform_default_timezone']" class="form-input">
                <option value="Asia/Karachi">Asia/Karachi (PKT +05:00)</option>
                <option value="Asia/Dubai">Asia/Dubai (GST +04:00)</option>
                <option value="UTC">UTC Universal</option>
              </select>
            </div>
          </div>

          <div style="border-top: 1px solid var(--border-color); padding-top: 1.25rem; margin-top: 1.25rem;">
            <div class="form-check-group" style="margin-bottom: 0.75rem;">
              <label class="switch-label">
                <input type="checkbox" v-model="settingModels['platform_maintenance_mode']" />
                <span style="font-weight: 600; color: #fb7185;">Broadcast Maintenance Alert Banner</span>
              </label>
            </div>
            <div class="form-group">
              <label class="form-label">Maintenance Notice Text</label>
              <input
                type="text"
                v-model="settingModels['platform_maintenance_message']"
                class="form-input"
                placeholder="Scheduled infrastructure maintenance notice..."
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 3: THERMAL RECEIPTS & POLICIES -->
    <div v-show="currentTab === 'receipts'">
      <div class="setting-card" style="max-width: 800px; margin-bottom: 2rem;">
        <div class="setting-card-header">
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0 0 0.25rem 0;">Receipt Template & Refund Policies</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Baseline templates inherited by new retail tenants</p>
          </div>
        </div>
        <div class="setting-card-body">
          <div class="form-group">
            <label class="form-label">Default Thermal Receipt Global Footer</label>
            <textarea
              v-model="settingModels['receipt_global_footer_note']"
              class="form-input"
              rows="4"
              placeholder="Footer text printed on 80mm/58mm thermal receipts..."
            ></textarea>
            <span class="form-hint">Tenants can customize their own store headers, but this disclaimer prints at the bottom.</span>
          </div>

          <div style="display: grid; grid-template-columns: 180px 1fr; gap: 1.25rem;">
            <div class="form-group">
              <label class="form-label">Return Window (Days)</label>
              <input
                type="number"
                v-model="settingModels['receipt_return_policy_days']"
                class="form-input"
                min="0"
                max="90"
              />
            </div>

            <div class="form-group">
              <label class="form-label">Return Policy Text</label>
              <textarea
                v-model="settingModels['receipt_return_policy_disclaimer']"
                class="form-input"
                rows="2"
              ></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 4: SUPPORT CHANNELS -->
    <div v-show="currentTab === 'support'">
      <div class="setting-card" style="max-width: 800px; margin-bottom: 2rem;">
        <div class="setting-card-header">
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0 0 0.25rem 0;">Merchant Support & Helpdesk</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Contact coordinates displayed on tenant dashboards and system emails</p>
          </div>
        </div>
        <div class="setting-card-body">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label class="form-label">Support Email Address</label>
              <input
                type="email"
                v-model="settingModels['support_email']"
                class="form-input"
                placeholder="support@01output.com"
              />
            </div>

            <div class="form-group">
              <label class="form-label">UAN Phone / Helpline</label>
              <input
                type="text"
                v-model="settingModels['support_phone']"
                class="form-input"
                placeholder="+92 42 111 010 101"
              />
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label class="form-label">WhatsApp Business Helpline</label>
              <input
                type="text"
                v-model="settingModels['support_whatsapp']"
                class="form-input"
                placeholder="+92 300 1234567"
              />
            </div>

            <div class="form-group">
              <label class="form-label">Documentation / Knowledge Base Portal</label>
              <input
                type="url"
                v-model="settingModels['support_knowledge_base_url']"
                class="form-input"
                placeholder="https://01pos.01output.com/docs"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 5: AUDIT LOG -->
    <div v-show="currentTab === 'audit'">
      <div class="setting-card" style="max-width: 900px;">
        <div class="setting-card-header">
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0 0 0.25rem 0;">Settings Audit Trail</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Permanent log of administrative configuration modifications</p>
          </div>
        </div>
        <div class="table-container" style="border: none; border-radius: 0;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Event</th>
                <th>Administrator</th>
                <th>IP Address</th>
                <th>Timestamp</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!audit_logs.length">
                <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                  No settings change events recorded yet.
                </td>
              </tr>
              <tr v-for="log in audit_logs" :key="log.id">
                <td>
                  <span class="badge badge-active" style="text-transform: capitalize;">
                    {{ log.event.replace(/_/g, ' ') }}
                  </span>
                </td>
                <td style="font-weight: 600;">{{ log.user_name }}</td>
                <td style="font-family: monospace; font-size: 0.85rem;">{{ log.ip_address || '127.0.0.1' }}</td>
                <td style="color: var(--text-muted); font-size: 0.85rem;">{{ log.created_at }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  grouped_settings: { type: Object, required: true },
  audit_logs: { type: Array, default: () => [] },
});

const currentTab = ref('gateways');
const testingGateway = ref(null);

// Flatten grouped settings into reactive state for form editing
const settingModels = reactive({});
const settingMeta = {};

Object.values(props.grouped_settings).flat().forEach(item => {
  settingModels[item.key] = item.value;
  settingMeta[item.key] = item;
});

const form = useForm({
  settings: [],
});

function getSettingValue(key) {
  return settingModels[key];
}

function hasSettingSecret(key) {
  return settingMeta[key]?.has_value ?? false;
}

function getSettingMask(key) {
  return settingMeta[key]?.masked_value ?? '';
}

function submitAllSettings() {
  const payload = Object.keys(settingModels).map(key => ({
    key,
    value: settingModels[key],
  }));

  form.settings = payload;
  form.post('/superadmin/settings', {
    preserveScroll: true,
  });
}

async function testGateway(gateway) {
  testingGateway.value = gateway;
  try {
    const res = await axios.post('/superadmin/settings/test-gateway', { gateway });
    alert(res.data.message);
  } catch (err) {
    const msg = err.response?.data?.message || 'Connection failed or credentials rejected.';
    alert('Error: ' + msg);
  } finally {
    testingGateway.value = null;
  }
}
</script>

<style scoped>
.tab-nav-bar {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 0.25rem;
  overflow-x: auto;
}

.tab-nav-bar button {
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  color: var(--text-muted);
  font-size: 0.9rem;
  font-weight: 600;
  padding: 0.65rem 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease-in-out;
}

.tab-nav-bar button:hover {
  color: var(--text-primary);
}

.tab-nav-bar button.active {
  color: #818cf8;
  border-bottom-color: #6366f1;
}

.setting-card {
  background: var(--bg-surface);
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.setting-card-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(15, 23, 42, 0.25);
}

.setting-card-body {
  padding: 1.5rem;
}

.secret-indicator {
  margin-left: 0.5rem;
  font-size: 0.7rem;
  padding: 0.15rem 0.45rem;
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  border-radius: 999px;
  font-weight: 600;
}

.switch-label {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary);
  cursor: pointer;
}

.form-hint {
  display: block;
  font-size: 0.75rem;
  color: var(--text-muted);
  margin-top: 0.35rem;
}

.badge-active {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.badge-inactive {
  background: rgba(100, 116, 139, 0.2);
  color: #94a3b8;
  border: 1px solid rgba(100, 116, 139, 0.3);
}
</style>
