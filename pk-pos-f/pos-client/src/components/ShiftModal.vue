<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import {
  authenticateCashierPin,
  openRegisterShift,
  recordCashMovement,
  getShiftXReport,
  closeRegisterShift,
  getActiveCashier,
  getActiveShift,
} from '../services/posService';
import type { CashierUser, ShiftReportSummary } from '../types/pos';

const props = defineProps<{
  isOpen: boolean;
  initialMode?: 'pin' | 'open' | 'movement' | 'x_report' | 'z_report';
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'shift-updated'): void;
}>();

const mode = ref<'pin' | 'open' | 'movement' | 'x_report' | 'z_report'>('pin');

watch(() => props.initialMode, (newMode) => {
  if (newMode) mode.value = newMode;
}, { immediate: true });

// State
const pinInput = ref<string>('');
const pinError = ref<string | null>(null);
const isSubmitting = ref<boolean>(false);
const activeCashier = ref<CashierUser | null>(null);

// Shift State
const currentShiftSession = ref<any>(null);
const shiftSummary = ref<ShiftReportSummary | null>(null);

// Open Shift
const openingFloatRupees = ref<number>(5000);
const openNotes = ref<string>('');

// Cash Movement
const movementType = ref<'cash_in' | 'cash_drop' | 'payout_expense'>('cash_drop');
const movementAmountRupees = ref<number>(1000);
const movementReason = ref<string>('');
const movementSupervisorPin = ref<string>('');

// Close Shift (Z-Report)
const countedCashRupees = ref<number>(0);
const closeNotes = ref<string>('');
const zReportResult = ref<ShiftReportSummary | null>(null);

async function refreshShiftStatus() {
  try {
    const res = await getActiveShift();
    if (res && res.active) {
      currentShiftSession.value = res.session;
      shiftSummary.value = res.summary;
      if (res.summary?.cash_drawer_reconciliation?.expected_cash) {
        countedCashRupees.value = Math.round(res.summary.cash_drawer_reconciliation.expected_cash / 100);
      }
    } else {
      currentShiftSession.value = null;
      shiftSummary.value = null;
    }
  } catch (e) {
    console.error('Failed to load shift in modal', e);
  }
}

onMounted(() => {
  activeCashier.value = getActiveCashier();
  refreshShiftStatus();
});

watch(() => props.isOpen, (open) => {
  if (open) {
    pinInput.value = '';
    pinError.value = null;
    activeCashier.value = getActiveCashier();
    refreshShiftStatus();
  }
});

// Numpad input handler
function appendPin(digit: string) {
  if (pinInput.value.length < 8) {
    pinInput.value += digit;
  }
}

function backspacePin() {
  pinInput.value = pinInput.value.slice(0, -1);
}

function clearPin() {
  pinInput.value = '';
  pinError.value = null;
}

async function submitPin() {
  if (pinInput.value.length < 4) {
    pinError.value = 'PIN must be at least 4 digits.';
    return;
  }
  isSubmitting.value = true;
  pinError.value = null;
  try {
    const res = await authenticateCashierPin(pinInput.value);
    activeCashier.value = res.user;
    emit('shift-updated');
    emit('close');
  } catch (err: any) {
    pinError.value = err?.message || 'Invalid cashier PIN.';
  } finally {
    isSubmitting.value = false;
  }
}

async function handleOpenShift() {
  isSubmitting.value = true;
  try {
    const floatMinor = Math.round(openingFloatRupees.value * 100);
    // Use stored location id or fallback
    const locId = '9d6b2c28-9844-4822-a5ec-9a60e1d00001';
    await openRegisterShift(locId, floatMinor, openNotes.value);
    await refreshShiftStatus();
    emit('shift-updated');
    emit('close');
  } catch (err: any) {
    alert(err?.message || 'Failed to open shift.');
  } finally {
    isSubmitting.value = false;
  }
}

async function handleCashMovement() {
  if (!currentShiftSession.value) return;
  if (!movementReason.value.trim()) {
    alert('Please enter a description or reason.');
    return;
  }
  isSubmitting.value = true;
  try {
    const amountMinor = Math.round(movementAmountRupees.value * 100);
    await recordCashMovement(
      currentShiftSession.value.id,
      movementType.value,
      amountMinor,
      movementReason.value,
      movementSupervisorPin.value || undefined
    );
    movementReason.value = '';
    movementSupervisorPin.value = '';
    await refreshShiftStatus();
    emit('shift-updated');
    emit('close');
  } catch (err: any) {
    alert(err?.message || 'Failed to record cash movement.');
  } finally {
    isSubmitting.value = false;
  }
}

async function handleFetchXReport() {
  if (!currentShiftSession.value) return;
  isSubmitting.value = true;
  try {
    const res = await getShiftXReport(currentShiftSession.value.id);
    shiftSummary.value = res.report;
  } catch (err: any) {
    alert(err?.message || 'Failed to load X-Report.');
  } finally {
    isSubmitting.value = false;
  }
}

const calculatedVarianceMinor = computed(() => {
  if (!shiftSummary.value?.cash_drawer_reconciliation?.expected_cash) return 0;
  const expected = shiftSummary.value.cash_drawer_reconciliation.expected_cash;
  const counted = Math.round(countedCashRupees.value * 100);
  return counted - expected;
});

async function handleCloseShift() {
  if (!currentShiftSession.value) return;
  if (!confirm('Are you sure you want to end this shift and generate the official fiscal Z-Report?')) {
    return;
  }
  isSubmitting.value = true;
  try {
    const countedMinor = Math.round(countedCashRupees.value * 100);
    const res = await closeRegisterShift(currentShiftSession.value.id, countedMinor, closeNotes.value);
    zReportResult.value = res.z_report;
    await refreshShiftStatus();
    emit('shift-updated');
  } catch (err: any) {
    alert(err?.message || 'Failed to close shift.');
  } finally {
    isSubmitting.value = false;
  }
}

function printReport() {
  window.print();
}

function formatPKRMinor(minor: number) {
  const r = (minor || 0) / 100;
  return 'Rs. ' + r.toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
</script>

<template>
  <div v-if="isOpen" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-window">
      <!-- Modal Navigation Bar -->
      <div class="modal-header">
        <div class="modal-tabs">
          <button
            class="tab-pill"
            :class="{ active: mode === 'pin' }"
            @click="mode = 'pin'"
          >
            Switch Cashier
          </button>
          <button
            v-if="!currentShiftSession"
            class="tab-pill"
            :class="{ active: mode === 'open' }"
            @click="mode = 'open'"
          >
            Open Shift
          </button>
          <button
            v-if="currentShiftSession"
            class="tab-pill"
            :class="{ active: mode === 'movement' }"
            @click="mode = 'movement'"
          >
            Cash In / Out
          </button>
          <button
            v-if="currentShiftSession"
            class="tab-pill"
            :class="{ active: mode === 'x_report' }"
            @click="mode = 'x_report'; handleFetchXReport();"
          >
            X-Report
          </button>
          <button
            v-if="currentShiftSession"
            class="tab-pill danger"
            :class="{ active: mode === 'z_report' }"
            @click="mode = 'z_report'"
          >
            Close Shift (Z-Report)
          </button>
        </div>
        <button class="close-btn" @click="emit('close')">&times;</button>
      </div>

      <!-- Mode 1: Cashier Quick-Switch (PIN Pad) -->
      <div v-if="mode === 'pin'" class="modal-body">
        <div class="pin-view">
          <div class="cashier-status-bar" v-if="activeCashier">
            <span>Logged in: <strong>{{ activeCashier.name }}</strong> ({{ activeCashier.role }})</span>
          </div>

          <h3 class="pin-heading">Enter Cashier / Staff PIN</h3>
          <p class="pin-sub">Enter your 4 to 6-digit numeric PIN to switch cashier context</p>

          <div class="pin-display">
            <span v-for="i in 6" :key="i" class="pin-dot" :class="{ filled: pinInput.length >= i }"></span>
          </div>

          <div v-if="pinError" class="pin-error-banner">
            {{ pinError }}
          </div>

          <!-- Numpad Grid -->
          <div class="numpad-grid">
            <button v-for="d in ['1','2','3','4','5','6','7','8','9']" :key="d" class="numpad-key" @click="appendPin(d)">
              {{ d }}
            </button>
            <button class="numpad-key clear-key" @click="clearPin">C</button>
            <button class="numpad-key" @click="appendPin('0')">0</button>
            <button class="numpad-key back-key" @click="backspacePin">&larr;</button>
          </div>

          <button class="submit-pin-btn" :disabled="isSubmitting || pinInput.length < 4" @click="submitPin">
            {{ isSubmitting ? 'Verifying...' : 'Unlock Terminal & Switch' }}
          </button>
        </div>
      </div>

      <!-- Mode 2: Open Register Shift -->
      <div v-if="mode === 'open'" class="modal-body">
        <h3 class="section-title">Start Daily Register Shift</h3>
        <p class="section-sub">Count and enter the initial starting cash float inside the cash drawer.</p>

        <div class="form-group">
          <label>Opening Starting Float (PKR)</label>
          <div class="input-money-wrap">
            <span class="currency-tag">Rs.</span>
            <input type="number" v-model.number="openingFloatRupees" min="0" step="100" class="form-input large-number" />
          </div>
        </div>

        <div class="form-group">
          <label>Shift Opening Notes (Optional)</label>
          <input type="text" v-model="openNotes" placeholder="e.g. Morning Shift - Terminal 01" class="form-input" />
        </div>

        <div class="btn-group">
          <button class="btn btn-secondary" @click="emit('close')">Cancel</button>
          <button class="btn btn-primary" :disabled="isSubmitting" @click="handleOpenShift">
            {{ isSubmitting ? 'Opening Register...' : 'Open Register & Begin Shift' }}
          </button>
        </div>
      </div>

      <!-- Mode 3: Cash Movement (Drop / Payout / Cash In) -->
      <div v-if="mode === 'movement'" class="modal-body">
        <h3 class="section-title">Cash Movement (Drawer In / Out)</h3>
        <p class="section-sub">Log cash deposits to the safe, petty cash expenses, or add change to drawer.</p>

        <div class="movement-type-toggle">
          <button
            class="movement-btn drop"
            :class="{ active: movementType === 'cash_drop' }"
            @click="movementType = 'cash_drop'"
          >
            Safe Drop (-)
          </button>
          <button
            class="movement-btn payout"
            :class="{ active: movementType === 'payout_expense' }"
            @click="movementType = 'payout_expense'"
          >
            Petty Expense (-)
          </button>
          <button
            class="movement-btn in"
            :class="{ active: movementType === 'cash_in' }"
            @click="movementType = 'cash_in'"
          >
            Cash In (+)
          </button>
        </div>

        <div class="form-group">
          <label>Amount (PKR)</label>
          <div class="input-money-wrap">
            <span class="currency-tag">Rs.</span>
            <input type="number" v-model.number="movementAmountRupees" min="1" class="form-input large-number" />
          </div>
        </div>

        <div class="form-group">
          <label>Reason / Description</label>
          <input type="text" v-model="movementReason" placeholder="e.g. Excess cash transferred to safe, Tea expense" class="form-input" />
        </div>

        <div class="form-group" v-if="movementType === 'cash_drop' || movementAmountRupees >= 5000">
          <label>Supervisor Approval PIN (Required for high-value drops)</label>
          <input type="password" v-model="movementSupervisorPin" placeholder="Enter supervisor PIN" maxlength="8" class="form-input" />
        </div>

        <div class="btn-group">
          <button class="btn btn-secondary" @click="emit('close')">Cancel</button>
          <button class="btn btn-primary" :disabled="isSubmitting" @click="handleCashMovement">
            {{ isSubmitting ? 'Recording...' : 'Record Cash Movement' }}
          </button>
        </div>
      </div>

      <!-- Mode 4: Mid-Shift X-Report -->
      <div v-if="mode === 'x_report'" class="modal-body">
        <div v-if="shiftSummary" class="report-box">
          <div class="report-header">
            <h4>{{ shiftSummary.report_title }}</h4>
            <div class="report-id">{{ shiftSummary.z_report_number }}</div>
          </div>

          <div class="report-rows">
            <div class="report-row">
              <span>Opening Float:</span>
              <span>{{ formatPKRMinor(shiftSummary.opening_float_minor) }}</span>
            </div>
            <div class="report-row">
              <span>Gross Sales:</span>
              <span class="highlight">{{ formatPKRMinor(shiftSummary.gross_sales_minor) }}</span>
            </div>
            <div class="report-row">
              <span>Total Transactions:</span>
              <span>{{ shiftSummary.sales_count }} sales</span>
            </div>
            <div class="report-row">
              <span>Sales Tax (FBR):</span>
              <span>{{ formatPKRMinor(shiftSummary.tax_collected_minor) }}</span>
            </div>
            <div class="report-row divider">
              <span>Expected Cash in Till:</span>
              <span class="highlight-green">{{ formatPKRMinor(shiftSummary.cash_drawer_reconciliation.expected_cash) }}</span>
            </div>
          </div>

          <div class="btn-group" style="margin-top: 1.5rem;">
            <button class="btn btn-secondary" @click="emit('close')">Dismiss</button>
            <button class="btn btn-primary" @click="printReport">Print X-Report</button>
          </div>
        </div>
      </div>

      <!-- Mode 5: Close Shift & Z-Report -->
      <div v-if="mode === 'z_report'" class="modal-body">
        <div v-if="!zReportResult">
          <h3 class="section-title">End Shift &amp; Fiscal Z-Report</h3>
          <p class="section-sub">Count all physical cash currently in the drawer and reconcile before closing.</p>

          <div class="drawer-summary-card">
            <div class="drawer-summary-item">
              <span class="summary-label">Expected in Drawer</span>
              <span class="summary-val">{{ formatPKRMinor(shiftSummary?.cash_drawer_reconciliation?.expected_cash || 0) }}</span>
            </div>
            <div class="drawer-summary-item">
              <span class="summary-label">Counted Cash</span>
              <span class="summary-val">Rs. {{ countedCashRupees.toLocaleString() }}</span>
            </div>
            <div class="drawer-summary-item" :class="calculatedVarianceMinor === 0 ? 'exact' : (calculatedVarianceMinor > 0 ? 'over' : 'short')">
              <span class="summary-label">Variance</span>
              <span class="summary-val">
                {{ calculatedVarianceMinor === 0 ? 'Rs. 0 (Balanced)' : (calculatedVarianceMinor > 0 ? `+Rs. ${(calculatedVarianceMinor/100).toLocaleString()} (Over)` : `-Rs. ${(Math.abs(calculatedVarianceMinor)/100).toLocaleString()} (Short)`) }}
              </span>
            </div>
          </div>

          <div class="form-group" style="margin-top: 1.25rem;">
            <label>Physical Counted Cash in Drawer (PKR)</label>
            <div class="input-money-wrap">
              <span class="currency-tag">Rs.</span>
              <input type="number" v-model.number="countedCashRupees" min="0" class="form-input large-number" />
            </div>
          </div>

          <div class="form-group">
            <label>Shift Closure Notes / Discrepancy Reason</label>
            <input type="text" v-model="closeNotes" placeholder="e.g. Evening shift closed, cash deposited in locker" class="form-input" />
          </div>

          <div class="btn-group">
            <button class="btn btn-secondary" @click="emit('close')">Cancel</button>
            <button class="btn btn-danger" :disabled="isSubmitting" @click="handleCloseShift">
              {{ isSubmitting ? 'Closing...' : 'Confirm & Generate Z-Report' }}
            </button>
          </div>
        </div>

        <!-- Z-Report Result View -->
        <div v-else class="z-report-success">
          <div class="success-icon">✓</div>
          <h3>Shift Closed Successfully</h3>
          <p>Official Fiscal Z-Report <strong>{{ zReportResult.z_report_number }}</strong> generated.</p>

          <div class="btn-group" style="margin-top: 1.5rem;">
            <button class="btn btn-secondary" @click="emit('close')">Close</button>
            <button class="btn btn-primary" @click="printReport">Print Z-Report</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-window {
  background: var(--bg-surface, #1e293b);
  border: 1px solid var(--border-color, #334155);
  border-radius: 14px;
  width: 100%;
  max-width: 480px;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
  color: var(--text-primary, #f8fafc);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid var(--border-color, #334155);
  background: rgba(0, 0, 0, 0.2);
}

.modal-tabs {
  display: flex;
  gap: 0.4rem;
  overflow-x: auto;
}

.tab-pill {
  background: transparent;
  border: 1px solid transparent;
  color: var(--text-muted, #94a3b8);
  padding: 0.35rem 0.65rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.tab-pill.active {
  background: var(--accent-primary, #6366f1);
  color: #ffffff;
}

.tab-pill.danger.active {
  background: #ef4444;
  color: #ffffff;
}

.close-btn {
  background: transparent;
  border: none;
  color: var(--text-muted, #94a3b8);
  font-size: 1.4rem;
  cursor: pointer;
  line-height: 1;
}

.modal-body {
  padding: 1.5rem;
}

.cashier-status-bar {
  background: rgba(99, 102, 241, 0.15);
  border: 1px solid rgba(99, 102, 241, 0.3);
  color: #a5b4fc;
  font-size: 0.8rem;
  padding: 0.45rem 0.75rem;
  border-radius: 6px;
  margin-bottom: 1rem;
  text-align: center;
}

.pin-heading {
  font-size: 1.15rem;
  font-weight: 700;
  text-align: center;
  margin: 0;
}

.pin-sub {
  font-size: 0.78rem;
  color: var(--text-muted, #94a3b8);
  text-align: center;
  margin: 0.25rem 0 1.25rem;
}

.pin-display {
  display: flex;
  justify-content: center;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}

.pin-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  border: 2px solid var(--border-color, #475569);
  transition: all 0.15s ease;
}

.pin-dot.filled {
  background: var(--accent-primary, #6366f1);
  border-color: var(--accent-primary, #6366f1);
  box-shadow: 0 0 10px rgba(99, 102, 241, 0.5);
  transform: scale(1.1);
}

.pin-error-banner {
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #f87171;
  font-size: 0.8rem;
  padding: 0.4rem;
  border-radius: 6px;
  margin-bottom: 1rem;
  text-align: center;
}

.numpad-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.6rem;
  max-width: 280px;
  margin: 0 auto 1.25rem;
}

.numpad-key {
  height: 52px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid var(--border-color, #334155);
  border-radius: 10px;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-primary, #f8fafc);
  cursor: pointer;
  transition: all 0.1s ease;
}

.numpad-key:active {
  transform: scale(0.95);
  background: rgba(255, 255, 255, 0.15);
}

.clear-key {
  color: #ef4444;
}

.back-key {
  color: #38bdf8;
}

.submit-pin-btn {
  width: 100%;
  height: 44px;
  background: var(--accent-primary, #6366f1);
  color: #ffffff;
  border: none;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.15s ease;
}

.submit-pin-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.section-title {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0;
}

.section-sub {
  font-size: 0.78rem;
  color: var(--text-muted, #94a3b8);
  margin: 0.25rem 0 1.25rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  margin-bottom: 0.35rem;
  color: var(--text-muted, #94a3b8);
}

.form-input {
  width: 100%;
  height: 40px;
  background: rgba(0, 0, 0, 0.25);
  border: 1px solid var(--border-color, #334155);
  border-radius: 8px;
  padding: 0 0.85rem;
  color: var(--text-primary, #f8fafc);
  font-size: 0.9rem;
  box-sizing: border-box;
}

.input-money-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.currency-tag {
  position: absolute;
  left: 0.85rem;
  font-weight: 700;
  color: var(--text-muted, #94a3b8);
}

.large-number {
  padding-left: 2.5rem;
  font-size: 1.2rem;
  font-weight: 700;
  font-family: monospace;
}

.btn-group {
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
  margin-top: 1.5rem;
}

.btn {
  padding: 0.6rem 1.1rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  border: none;
}

.btn-primary {
  background: var(--accent-primary, #6366f1);
  color: #fff;
}

.btn-secondary {
  background: rgba(255, 255, 255, 0.08);
  color: var(--text-primary, #f8fafc);
  border: 1px solid var(--border-color, #334155);
}

.btn-danger {
  background: #ef4444;
  color: #fff;
}

.movement-type-toggle {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.5rem;
  margin-bottom: 1.25rem;
}

.movement-btn {
  padding: 0.5rem;
  border-radius: 6px;
  border: 1px solid var(--border-color, #334155);
  background: transparent;
  color: var(--text-muted, #94a3b8);
  font-weight: 600;
  font-size: 0.78rem;
  cursor: pointer;
}

.movement-btn.drop.active {
  background: rgba(245, 158, 11, 0.2);
  border-color: #f59e0b;
  color: #fbbf24;
}

.movement-btn.payout.active {
  background: rgba(239, 68, 68, 0.2);
  border-color: #ef4444;
  color: #f87171;
}

.movement-btn.in.active {
  background: rgba(16, 185, 129, 0.2);
  border-color: #10b981;
  color: #34d399;
}

.drawer-summary-card {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.5rem;
  background: rgba(0, 0, 0, 0.3);
  padding: 0.85rem;
  border-radius: 8px;
  border: 1px solid var(--border-color, #334155);
}

.drawer-summary-item {
  display: flex;
  flex-direction: column;
  text-align: center;
}

.summary-label {
  font-size: 0.68rem;
  color: var(--text-muted, #94a3b8);
  text-transform: uppercase;
  font-weight: 600;
}

.summary-val {
  font-size: 0.95rem;
  font-weight: 700;
  margin-top: 0.2rem;
  font-family: monospace;
}

.drawer-summary-item.exact .summary-val { color: #10b981; }
.drawer-summary-item.over .summary-val { color: #f59e0b; }
.drawer-summary-item.short .summary-val { color: #ef4444; }

.report-box {
  background: #ffffff;
  color: #000;
  padding: 1.25rem;
  border-radius: 8px;
  font-family: monospace;
  font-size: 0.8rem;
}

.report-header {
  text-align: center;
  border-bottom: 1px dashed #000;
  padding-bottom: 0.5rem;
  margin-bottom: 0.75rem;
}

.report-header h4 {
  margin: 0;
  font-size: 0.95rem;
}

.report-rows .report-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.35rem;
}

.report-row.divider {
  border-top: 1px dashed #000;
  padding-top: 0.5rem;
  margin-top: 0.5rem;
  font-weight: 700;
}

.highlight {
  font-weight: 700;
  color: #6366f1;
}

.highlight-green {
  font-weight: 700;
  color: #059669;
}

.z-report-success {
  text-align: center;
  padding: 1rem 0;
}

.success-icon {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: rgba(16, 185, 129, 0.2);
  color: #10b981;
  font-size: 1.8rem;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
}
</style>
