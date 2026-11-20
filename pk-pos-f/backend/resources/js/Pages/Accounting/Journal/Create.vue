<template>
  <AdminLayout title="New Manual Journal Entry">
    <div class="create-journal-page">
      <div class="page-header">
        <div>
          <div class="breadcrumb">
            <Link href="/journal-entries" class="breadcrumb-link">General Journal</Link>
            <span class="breadcrumb-sep">/</span>
            <span>New Manual Entry</span>
          </div>
          <h1 class="page-title">New Manual Journal Entry</h1>
          <p class="page-subtitle">Record adjusting entries, owner drawings, depreciation, or manual bank transfers</p>
        </div>
        <Link href="/journal-entries" class="btn btn-secondary">&larr; Back to Journal</Link>
      </div>

      <form @submit.prevent="submitEntry" class="card form-container">
        <!-- Header Info -->
        <div class="header-inputs">
          <div class="form-field">
            <label>Entry Date *</label>
            <input v-model="form.entry_date" type="date" required class="form-input" />
          </div>
          <div class="form-field">
            <label>Reference (Optional)</label>
            <input v-model="form.reference" type="text" placeholder="e.g. ADJ-001 or CHQ-998877" class="form-input font-mono" />
          </div>
          <div class="form-field field-grow">
            <label>Narration / Description *</label>
            <input v-model="form.description" type="text" placeholder="e.g. Monthly shop rent adjustment from bank" required class="form-input" />
          </div>
        </div>

        <!-- Lines Table -->
        <div class="lines-section">
          <div class="lines-header">
            <h3>Journal Lines (Debits &amp; Credits)</h3>
            <button type="button" @click="addLine" class="btn btn-sm btn-secondary">
              + Add Row
            </button>
          </div>

          <table class="lines-table">
            <thead>
              <tr>
                <th style="width: 35%;">Account *</th>
                <th>Memo / Description</th>
                <th style="width: 18%; text-align: right;">Debit (Rs.)</th>
                <th style="width: 18%; text-align: right;">Credit (Rs.)</th>
                <th style="width: 50px;"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(line, idx) in form.lines" :key="idx">
                <td>
                  <select v-model="line.account_id" required class="form-select w-full">
                    <option value="" disabled>Select an account...</option>
                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                      {{ acc.code }} — {{ acc.name }} ({{ acc.type }})
                    </option>
                  </select>
                </td>
                <td>
                  <input v-model="line.memo" type="text" placeholder="Line memo..." class="form-input w-full" />
                </td>
                <td>
                  <input
                    v-model.number="line.debit_rs"
                    @input="onDebitInput(idx)"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    class="form-input text-right font-mono w-full"
                  />
                </td>
                <td>
                  <input
                    v-model.number="line.credit_rs"
                    @input="onCreditInput(idx)"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    class="form-input text-right font-mono w-full"
                  />
                </td>
                <td class="text-center">
                  <button
                    type="button"
                    @click="removeLine(idx)"
                    :disabled="form.lines.length <= 2"
                    class="btn-trash"
                    title="Remove line"
                  >
                    &times;
                  </button>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="tfoot-row">
                <td colspan="2" class="text-right font-bold">Totals:</td>
                <td class="text-right font-mono font-bold text-debit">
                  Rs. {{ totalDebitRs.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </td>
                <td class="text-right font-mono font-bold text-credit">
                  Rs. {{ totalCreditRs.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Balance Indicator & Submission -->
        <div class="form-footer">
          <div class="balance-indicator">
            <div v-if="isBalanced" class="alert-balanced">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              <span>Entry is perfectly balanced (Debits = Credits). Ready to post.</span>
            </div>
            <div v-else class="alert-unbalanced">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
              </svg>
              <span>
                Unbalanced by <strong>Rs. {{ Math.abs(totalDebitRs - totalCreditRs).toLocaleString('en-PK', { minimumFractionDigits: 2 }) }}</strong>.
                Total Debits must equal Total Credits.
              </span>
            </div>
          </div>

          <div class="submit-actions">
            <Link href="/journal-entries" class="btn btn-secondary">Cancel</Link>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="!isBalanced || form.processing"
            >
              Post Journal Entry
            </button>
          </div>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  accounts: Array<any>;
}>();

const form = useForm({
  entry_date: new Date().toISOString().split('T')[0],
  reference: '',
  description: '',
  lines: [
    { account_id: '', memo: '', debit_rs: 0, credit_rs: 0, debit_minor: 0, credit_minor: 0 },
    { account_id: '', memo: '', debit_rs: 0, credit_rs: 0, debit_minor: 0, credit_minor: 0 },
  ],
});

function addLine() {
  form.lines.push({
    account_id: '',
    memo: '',
    debit_rs: 0,
    credit_rs: 0,
    debit_minor: 0,
    credit_minor: 0,
  });
}

function removeLine(idx: number) {
  if (form.lines.length > 2) {
    form.lines.splice(idx, 1);
  }
}

function onDebitInput(idx: number) {
  if (form.lines[idx].debit_rs > 0) {
    form.lines[idx].credit_rs = 0;
  }
}

function onCreditInput(idx: number) {
  if (form.lines[idx].credit_rs > 0) {
    form.lines[idx].debit_rs = 0;
  }
}

const totalDebitRs = computed(() => {
  return form.lines.reduce((sum, line) => sum + (Number(line.debit_rs) || 0), 0);
});

const totalCreditRs = computed(() => {
  return form.lines.reduce((sum, line) => sum + (Number(line.credit_rs) || 0), 0);
});

const isBalanced = computed(() => {
  const debits = Math.round(totalDebitRs.value * 100);
  const credits = Math.round(totalCreditRs.value * 100);
  return debits > 0 && debits === credits;
});

function submitEntry() {
  if (!isBalanced.value) return;

  // Convert Rs to paisas/minor
  form.lines.forEach((line) => {
    line.debit_minor = Math.round((Number(line.debit_rs) || 0) * 100);
    line.credit_minor = Math.round((Number(line.credit_rs) || 0) * 100);
  });

  form.post('/journal-entries');
}
</script>

<style scoped>
.create-journal-page {
  padding: 1.5rem;
  max-width: 1200px;
  margin: 0 auto;
}

.breadcrumb {
  display: flex;
  gap: 0.5rem;
  font-size: 0.8125rem;
  color: var(--text-muted, #94a3b8);
  margin-bottom: 0.25rem;
}

.breadcrumb-link {
  color: #38bdf8;
  text-decoration: none;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-main, #f8fafc);
  margin: 0;
}

.page-subtitle {
  color: var(--text-muted, #94a3b8);
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.card {
  background: var(--bg-surface, #1e293b);
  border: 1px solid var(--border-color, #334155);
  border-radius: 0.5rem;
  padding: 1.5rem;
}

.header-inputs {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.field-grow {
  flex: 1;
  min-width: 300px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-field label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #cbd5e1;
}

.lines-section {
  border-top: 1px solid #334155;
  padding-top: 1.25rem;
  margin-bottom: 1.5rem;
}

.lines-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.lines-header h3 {
  font-size: 1.125rem;
  font-weight: 700;
  color: #f8fafc;
  margin: 0;
}

.lines-table {
  width: 100%;
  border-collapse: collapse;
}

.lines-table th, .lines-table td {
  padding: 0.65rem 0.5rem;
  border-bottom: 1px solid #334155;
}

.lines-table th {
  background: rgba(15, 23, 42, 0.4);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  color: #94a3b8;
}

.tfoot-row {
  background: rgba(15, 23, 42, 0.6);
}

.btn-trash {
  background: transparent;
  border: none;
  color: #f87171;
  font-size: 1.25rem;
  cursor: pointer;
}
.btn-trash:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.form-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid #334155;
  padding-top: 1.25rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.alert-balanced {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #4ade80;
  font-size: 0.875rem;
  font-weight: 600;
}

.alert-unbalanced {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #f87171;
  font-size: 0.875rem;
}

.submit-actions {
  display: flex;
  gap: 0.75rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 600;
  border-radius: 0.375rem;
  cursor: pointer;
  border: 1px solid transparent;
  text-decoration: none;
}

.btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8125rem; }
.btn-primary { background: #0284c7; color: white; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { background: #334155; color: #f8fafc; }

.form-input, .form-select {
  padding: 0.5rem 0.75rem;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 0.375rem;
  color: #f8fafc;
  font-size: 0.875rem;
}

.w-full { width: 100%; }
.font-mono { font-family: monospace; }
.font-bold { font-weight: 700; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.text-debit { color: #38bdf8; }
.text-credit { color: #4ade80; }
</style>
