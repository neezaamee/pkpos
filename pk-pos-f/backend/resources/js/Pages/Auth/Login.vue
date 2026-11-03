<template>
  <div class="login-wrapper">
    <div class="login-card">
      <div class="login-header">
        <div class="brand-logo" style="margin: 0 auto 1rem auto; width: 48px; height: 48px; font-size: 1.35rem;">
          PK
        </div>
        <h1>PK POS Admin</h1>
        <p>Sign in to manage sales, tenants & FBR credentials</p>
      </div>

      <form @submit.prevent="submit">
        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            class="form-input"
            :class="{ 'is-invalid': form.errors.email }"
            placeholder="owner@pkpos.test"
            required
            autofocus
            autocomplete="username"
          />
          <div v-if="form.errors.email" class="invalid-feedback">
            {{ form.errors.email }}
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            class="form-input"
            :class="{ 'is-invalid': form.errors.password }"
            placeholder="••••••••"
            required
            autocomplete="current-password"
          />
          <div v-if="form.errors.password" class="invalid-feedback">
            {{ form.errors.password }}
          </div>
        </div>

        <div class="form-group" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
          <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-secondary); cursor: pointer;">
            <input
              v-model="form.remember"
              type="checkbox"
              style="accent-color: var(--accent-primary); width: 16px; height: 16px; border-radius: 4px;"
            />
            Remember me
          </label>
        </div>

        <button
          type="submit"
          class="btn btn-primary"
          style="width: 100%; padding: 0.85rem 1rem;"
          :disabled="form.processing"
        >
          <svg v-if="form.processing" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
            <path d="M12 2a10 10 0 0 1 10 10"></path>
          </svg>
          <span>{{ form.processing ? 'Signing in...' : 'Sign In to Dashboard' }}</span>
        </button>
      </form>

      <!-- Quick demo hint -->
      <div style="margin-top: 1.75rem; padding: 0.85rem; background: rgba(255, 255, 255, 0.03); border: 1px dashed var(--border-color); border-radius: var(--radius-md); font-size: 0.775rem; color: var(--text-muted); text-align: center;">
        <span style="font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 0.2rem;">
          Seeded Demo Credentials:
        </span>
        <span style="font-family: var(--font-mono);">owner@pkpos.test</span> / <span style="font-family: var(--font-mono);">password123</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
  email: '',
  password: '',
  remember: false,
});

function submit() {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
}
</script>

<style scoped>
.login-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  background: radial-gradient(circle at 50% 30%, #172036 0%, #0a0f1d 70%);
}

.login-card {
  width: 100%;
  max-width: 440px;
  background: var(--bg-card);
  backdrop-filter: blur(12px);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  padding: 2.25rem;
  box-shadow: var(--shadow-lg);
  animation: scaleUp 0.2s ease;
}

.login-header {
  text-align: center;
  margin-bottom: 2rem;
}

.login-header h1 {
  font-size: 1.4rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  margin-bottom: 0.35rem;
}

.login-header p {
  font-size: 0.85rem;
  color: var(--text-muted);
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@keyframes scaleUp {
  from { transform: scale(0.96); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
</style>
