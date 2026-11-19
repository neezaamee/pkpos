<template>
  <AdminLayout :title="`Khata Ledger: ${customer.name}`">
    <!-- Top Action & Navigation Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <Link href="/customers" class="btn btn-outline" style="padding: 0.4rem 0.65rem;" title="Back to Customer Directory">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
        </Link>
        <div>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <h1 style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); margin: 0;">
              {{ customer.name }}
            </h1>
            <span
              v-if="customer.current_balance_minor > 0"
              class="badge"
              style="background: rgba(239, 68, 68, 0.15); color: #ef4444; font-weight: 700;"
            >
              <span class="badge-dot" style="background: #ef4444;"></span>
              Pending Udhaar
            </span>
            <span
              v-else
              class="badge"
              style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700;"
            >
              <span class="badge-dot" style="background: #10b981;"></span>
              Account Clear
            </span>
          </div>
          <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem; display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
            <span>Phone: <strong style="color: var(--text-secondary); font-family: var(--font-mono);">{{ customer.phone }}</strong></span>
            <span v-if="customer.city">&bull; City: <strong>{{ customer.city }}</strong></span>
            <span v-if="customer.cnic">&bull; CNIC: <strong style="font-family: var(--font-mono);">{{ customer.cnic }}</strong></span>
            <span v-if="customer.ntn">&bull; NTN: <strong style="font-family: var(--font-mono);">{{ customer.ntn }}</strong></span>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <button
          type="button"
          class="btn btn-outline"
          style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.9rem;"
          @click="printStatement"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 6 2 18 2 18 9"></polyline>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <rect x="6" y="14" width="12" height="8"></rect>
          </svg>
          <span>Print Statement</span>
        </button>

        <button
          type="button"
          class="btn btn-primary"
          style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 1.1rem; background: #10b981; border-color: #10b981;"
          @click="showPaymentModal = true"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          <span>Record Payment (Vasooli)</span>
        </button>
      </div>
    </div>

    <!-- Financial Metrics Strip -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="card" style="border-left: 4px solid #ef4444;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
          Current Receivable (Owed)
        </div>
        <div style="font-size: 1.75rem; font-weight: 800; color: #ef4444; margin-top: 0.25rem; font-family: var(--font-mono);">
          {{ formatMoney(customer.current_balance_minor) }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Total pending debit balance
        </div>
      </div>

      <div class="card" style="border-left: 4px solid #3b82f6;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
          Credit Ceiling (Limit)
        </div>
        <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; font-family: var(--font-mono);">
          {{ customer.credit_limit_minor > 0 ? formatMoney(customer.credit_limit_minor) : 'Rs. 0 (Cash Only)' }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Maximum allowed credit
        </div>
      </div>

      <div class="card" style="border-left: 4px solid #10b981;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
          Available Credit Room
        </div>
        <div style="font-size: 1.75rem; font-weight: 800; color: #10b981; margin-top: 0.25rem; font-family: var(--font-mono);">
          {{ formatMoney(availableCreditMinor) }}
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
          Remaining headroom for credit sales
        </div>
      </div>
    </div>

    <!-- Ledger Statement Table Card -->
    <div class="card-table">
      <div class="table-header">
        <div>
          <h2>Account Ledger Statement (Khata Vahi)</h2>
          <div style="font-size: 0.825rem; color: var(--text-muted);">
            Double-entry chronological history of purchases, debit charges, and repayments
          </div>
        </div>
      </div>

      <div style="overflow-x: auto;">
        <table class="data-table" id="statement-table">
          <thead>
            <tr>
              <th>Date &amp; Time</th>
              <th>Receipt / Ref #</th>
              <th>Transaction Details</th>
              <th style="text-align: right;">Debit (Naamay)</th>
              <th style="text-align: right;">Credit (Jama)</th>
              <th style="text-align: right;">Balance After</th>
              <th>Recorded By</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in transactions.data" :key="t.id">
              <td style="white-space: nowrap; font-size: 0.825rem; color: var(--text-secondary);">
                {{ formatDateTime(t.created_at) }}
              </td>
              <td style="font-family: var(--font-mono); font-size: 0.85rem; font-weight: 600;">
                <span v-if="t.receipt_number" style="color: #6366f1;">
                  {{ t.receipt_number }}
                </span>
                <span v-else-if="t.sale?.sale_number" style="color: var(--text-primary);">
                  {{ t.sale.sale_number }}
                </span>
                <span v-else style="color: var(--text-muted);">—</span>
              </td>
              <td>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                  <span
                    v-if="t.type === 'credit_sale'"
                    class="badge"
                    style="background: rgba(239, 68, 68, 0.12); color: #ef4444; font-size: 0.72rem;"
                  >
                    Credit Purchase
                  </span>
                  <span
                    v-else-if="t.type === 'payment'"
                    class="badge"
                    style="background: rgba(16, 185, 129, 0.12); color: #10b981; font-size: 0.72rem;"
                  >
                    Payment ({{ t.payment_method ? t.payment_method.toUpperCase() : 'CASH' }})
                  </span>
                  <span
                    v-else
                    class="badge"
                    style="background: rgba(148, 163, 184, 0.12); color: var(--text-muted); font-size: 0.72rem;"
                  >
                    {{ t.type }}
                  </span>

                  <span v-if="t.notes" style="font-size: 0.8rem; color: var(--text-muted);">
                    {{ t.notes }}
                  </span>
                </div>
              </td>
              <!-- Debit -->
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 700; color: #ef4444;">
                <span v-if="t.type === 'credit_sale' || (t.type === 'adjustment' && t.amount_minor > 0)">
                  +{{ formatMoney(t.amount_minor) }}
                </span>
                <span v-else style="color: var(--text-muted); font-weight: 400;">—</span>
              </td>
              <!-- Credit -->
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 700; color: #10b981;">
                <span v-if="t.type === 'payment'">
                  -{{ formatMoney(t.amount_minor) }}
                </span>
                <span v-else style="color: var(--text-muted); font-weight: 400;">—</span>
              </td>
              <!-- Balance After -->
              <td style="text-align: right; font-family: var(--font-mono); font-weight: 700; color: var(--text-primary);">
                {{ formatMoney(t.balance_after_minor) }}
              </td>
              <td style="font-size: 0.8rem; color: var(--text-secondary);">
                {{ t.recorded_by?.name || 'Staff' }}
              </td>
            </tr>

            <tr v-if="transactions.data.length === 0">
              <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                No transactions recorded in this Khata account yet.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="transactions.links && transactions.links.length > 3" style="padding: 1rem; display: flex; justify-content: flex-end; gap: 0.35rem;">
        <Link
          v-for="(link, i) in transactions.links"
          :key="i"
          :href="link.url || '#'"
          v-html="link.label"
          class="btn btn-outline"
          :class="{ 'active': link.active, 'disabled': !link.url }"
          style="padding: 0.25rem 0.65rem; font-size: 0.75rem;"
        />
      </div>
    </div>

    <!-- Record Payment Modal -->
    <div
      v-if="showPaymentModal"
      class="modal-backdrop"
      style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 1rem;"
      @click.self="showPaymentModal = false"
    >
      <div class="card" style="width: 100%; max-width: 480px; padding: 1.75rem; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
          <div>
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0;">
              Record Customer Payment (Vasooli)
            </h3>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
              Credit account of {{ customer.name }}
            </div>
          </div>
          <button
            type="button"
            class="modal-close"
            style="background: transparent; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;"
            @click="showPaymentModal = false"
          >
            &times;
          </button>
        </div>

        <form @submit.prevent="submitPayment">
          <div style="background: rgba(239, 68, 68, 0.08); border-radius: 8px; padding: 0.75rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.8rem; color: #ef4444; font-weight: 600;">Outstanding Due:</span>
            <span style="font-size: 1.15rem; font-weight: 800; color: #ef4444; font-family: var(--font-mono);">
              {{ formatMoney(customer.current_balance_minor) }}
            </span>
          </div>

          <!-- Payment Amount -->
          <div style="margin-bottom: 1.25rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Repayment Amount (PKR) <span style="color: #ef4444;">*</span>
            </label>
            <div style="position: relative; display: flex; align-items: center;">
              <span style="position: absolute; left: 0.75rem; font-weight: 700; color: var(--text-muted); font-size: 0.9rem;">Rs.</span>
              <input
                v-model.number="paymentPkr"
                type="number"
                min="1"
                step="1"
                class="form-control"
                style="padding-left: 2.75rem; font-family: var(--font-mono); font-size: 1.1rem; font-weight: 700;"
                placeholder="0"
                required
              />
            </div>
            <!-- Quick Fill Buttons -->
            <div style="display: flex; gap: 0.5rem; margin-top: 0.4rem;">
              <button
                type="button"
                class="btn btn-outline"
                style="padding: 0.2rem 0.5rem; font-size: 0.72rem;"
                @click="paymentPkr = customer.current_balance_minor / 100"
              >
                Pay Full Balance
              </button>
              <button
                v-if="customer.current_balance_minor > 200"
                type="button"
                class="btn btn-outline"
                style="padding: 0.2rem 0.5rem; font-size: 0.72rem;"
                @click="paymentPkr = Math.floor((customer.current_balance_minor / 100) / 2)"
              >
                Pay 50%
              </button>
            </div>
          </div>

          <!-- Payment Method -->
          <div style="margin-bottom: 1.25rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Payment Channel <span style="color: #ef4444;">*</span>
            </label>
            <select v-model="paymentForm.payment_method" class="form-control" required>
              <option value="cash">Cash in Hand</option>
              <option value="raast">Raast Instant P2P / Merchant</option>
              <option value="bank_transfer">Online Bank Transfer (IBFT)</option>
              <option value="card">Debit / Credit Card</option>
              <option value="cheque">Bank Cheque</option>
            </select>
          </div>

          <!-- Notes -->
          <div style="margin-bottom: 1.5rem;">
            <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
              Payment Reference / Cheque # / Notes
            </label>
            <input
              v-model="paymentForm.notes"
              type="text"
              class="form-control"
              placeholder="e.g. Raast Ref #1234567 or Cheque #8821"
            />
          </div>

          <!-- Actions -->
          <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button
              type="button"
              class="btn btn-outline"
              @click="showPaymentModal = false"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="paymentForm.processing || paymentPkr <= 0"
              style="padding: 0.5rem 1.25rem; background: #10b981; border-color: #10b981;"
            >
              <span v-if="paymentForm.processing">Recording...</span>
              <span v-else>Confirm Repayment</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  customer: {
    type: Object,
    required: true,
  },
  transactions: {
    type: Object,
    required: true,
  },
});

const showPaymentModal = ref(false);
const paymentPkr = ref(0);

const availableCreditMinor = computed(() => {
  if (!props.customer.credit_limit_minor || props.customer.credit_limit_minor <= 0) {
    return 0;
  }
  return Math.max(0, props.customer.credit_limit_minor - (props.customer.current_balance_minor || 0));
});

const paymentForm = useForm({
  amount_minor: 0,
  payment_method: 'cash',
  notes: '',
});

function submitPayment() {
  paymentForm.amount_minor = Math.round((paymentPkr.value || 0) * 100);
  paymentForm.post(`/customers/${props.customer.id}/payments`, {
    onSuccess: () => {
      showPaymentModal.value = false;
      paymentPkr.value = 0;
      paymentForm.reset('notes');
    },
  });
}

function printStatement() {
  window.print();
}

function formatMoney(minor) {
  if (minor === undefined || minor === null) return 'Rs. 0';
  const val = minor / 100;
  return 'Rs. ' + val.toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatDateTime(isoStr) {
  if (!isoStr) return '—';
  const d = new Date(isoStr);
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
    ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}
</script>
