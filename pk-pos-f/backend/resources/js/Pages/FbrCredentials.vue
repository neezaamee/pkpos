<template>
  <AdminLayout title="FBR IMS Integration Credentials">
    <div style="max-width: 700px;">
      <!-- Active Token Status Banner -->
      <div class="metric-card" style="margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
        <div>
          <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em; display: block; margin-bottom: 0.25rem;">
            Token Expiry & Status
          </span>
          <div style="font-size: 1.1rem; font-weight: 700;">
            {{ credentials?.token_expires_at ? formatDate(credentials.token_expires_at) : 'No token saved yet' }}
          </div>
        </div>

        <div>
          <span v-if="tokenStatus === 'active'" class="badge badge-submitted">
            <span class="badge-dot"></span>
            Active
          </span>
          <span v-else-if="tokenStatus === 'expired'" class="badge badge-failed">
            <span class="badge-dot"></span>
            Expired
          </span>
          <span v-else class="badge badge-pending">
            <span class="badge-dot"></span>
            Unconfigured
          </span>
        </div>
      </div>

      <!-- Credentials Form -->
      <div class="form-card">
        <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem;">
          Configure POS Machine & Credentials
        </h3>

        <form @submit.prevent="submit">
          <!-- POS ID -->
          <div class="form-group">
            <label class="form-label" for="pos_id">POS Machine ID</label>
            <input
              id="pos_id"
              v-model="form.pos_id"
              type="text"
              class="form-input"
              :class="{ 'is-invalid': form.errors.pos_id }"
              placeholder="e.g. 102938"
              required
            />
            <span class="form-hint">7-digit POS identifier registered in the FBR e-portal.</span>
            <div v-if="form.errors.pos_id" class="invalid-feedback">
              {{ form.errors.pos_id }}
            </div>
          </div>

          <!-- NTN -->
          <div class="form-group">
            <label class="form-label" for="ntn">National Tax Number (NTN)</label>
            <input
              id="ntn"
              v-model="form.ntn"
              type="text"
              class="form-input"
              :class="{ 'is-invalid': form.errors.ntn }"
              placeholder="e.g. 1234567-8"
              required
            />
            <div v-if="form.errors.ntn" class="invalid-feedback">
              {{ form.errors.ntn }}
            </div>
          </div>

          <!-- STRN -->
          <div class="form-group">
            <label class="form-label" for="strn">Sales Tax Registration Number (STRN)</label>
            <input
              id="strn"
              v-model="form.strn"
              type="text"
              class="form-input"
              :class="{ 'is-invalid': form.errors.strn }"
              placeholder="e.g. 0123456789012"
            />
            <div v-if="form.errors.strn" class="invalid-feedback">
              {{ form.errors.strn }}
            </div>
          </div>

          <!-- Bearer Token -->
          <div class="form-group">
            <label class="form-label" for="bearer_token">FBR API Bearer Token</label>
            <input
              id="bearer_token"
              v-model="form.bearer_token"
              type="password"
              class="form-input"
              :class="{ 'is-invalid': form.errors.bearer_token }"
              :placeholder="hasExistingToken ? '•••••••••••••••• (Leave blank to keep existing)' : 'Enter FBR bearer token'"
              autocomplete="new-password"
            />
            <span class="form-hint">
              Secured with AES-256-CBC encryption at rest. Never displayed after saving.
            </span>
            <div v-if="form.errors.bearer_token" class="invalid-feedback">
              {{ form.errors.bearer_token }}
            </div>
          </div>

          <!-- Token Expiration Date -->
          <div class="form-group">
            <label class="form-label" for="token_expires_at">Token Expiry Date</label>
            <input
              id="token_expires_at"
              v-model="form.token_expires_at"
              type="date"
              class="form-input"
              :class="{ 'is-invalid': form.errors.token_expires_at }"
            />
            <span class="form-hint">FBR credentials generally expire every 180 or 365 days.</span>
            <div v-if="form.errors.token_expires_at" class="invalid-feedback">
              {{ form.errors.token_expires_at }}
            </div>
          </div>

          <div style="margin-top: 2rem; display: flex; align-items: center; justify-content: flex-end; gap: 1rem;">
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="form.processing"
            >
              <svg v-if="form.processing" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                <path d="M12 2a10 10 0 0 1 10 10"></path>
              </svg>
              <span>{{ form.processing ? 'Saving...' : 'Save FBR Credentials' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  credentials: {
    type: Object,
    default: null,
  },
  hasExistingToken: {
    type: Boolean,
    default: false,
  },
});

const form = useForm({
  pos_id: props.credentials?.pos_id || '',
  ntn: props.credentials?.ntn || '',
  strn: props.credentials?.strn || '',
  bearer_token: '',
  token_expires_at: props.credentials?.token_expires_at ? props.credentials.token_expires_at.substring(0, 10) : '',
});

function submit() {
  form.post('/fbr-credentials', {
    preserveScroll: true,
    onSuccess: () => {
      form.bearer_token = '';
    },
  });
}

const tokenStatus = computed(() => {
  if (!props.hasExistingToken && !props.credentials?.token_expires_at) return 'unconfigured';
  if (!props.credentials?.token_expires_at) return 'active';
  const expiry = new Date(props.credentials.token_expires_at);
  return expiry > new Date() ? 'active' : 'expired';
});

function formatDate(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}
</script>

<style scoped>
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
