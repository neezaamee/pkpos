<template>
  <SuperAdminLayout title="SaaS Invoicing & B2B Billing Gateway">
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
      <!-- Page Header -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
        <div>
          <h1 style="font-size: 1.5rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.25rem;">
            Pakistan SaaS Invoicing & 1Bill Billing Gateway
          </h1>
          <p style="color: #94a3b8; font-size: 0.875rem;">
            Manage B2B subscription billing, generate 1Link 1Bill & Kuickpay consumer numbers, and verify bank wire IBFT transfer slips.
          </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
          <button
            @click="openCreateInvoiceModal"
            class="btn btn-primary"
            style="display: inline-flex; align-items: center; gap: 0.5rem;"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Issue B2B Invoice
          </button>
        </div>
      </div>

      <!-- Financial Metrics Cards -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Total Revenue Collected
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #34d399; margin-top: 0.25rem;">
                PKR {{ formatMoney(metrics.total_revenue_rs) }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(52, 211, 153, 0.15); border-radius: 8px; color: #34d399;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
            Settled via 1Bill, Kuickpay & Bank Wire
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Pending Wire Reviews
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #fbbf24; margin-top: 0.25rem;">
                {{ metrics.pending_reviews_count }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(245, 158, 11, 0.15); border-radius: 8px; color: #fbbf24;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #fbbf24; margin-top: 0.5rem;">
            Awaiting manual staff deposit verification
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Unpaid Receivables
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #f87171; margin-top: 0.25rem;">
                PKR {{ formatMoney(metrics.unpaid_receivables_rs) }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(239, 68, 68, 0.15); border-radius: 8px; color: #f87171;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                <line x1="6" y1="12" x2="18" y2="12"></line>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
            Outstanding open merchant invoices
          </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em;">
                Invoices Settled
              </div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #60a5fa; margin-top: 0.25rem;">
                {{ metrics.paid_invoices_count }} / {{ metrics.total_invoices_count }}
              </div>
            </div>
            <div style="padding: 0.5rem; background: rgba(59, 130, 246, 0.15); border-radius: 8px; color: #60a5fa;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
              </svg>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
            Total issued billing cycle records
          </div>
        </div>
      </div>

      <!-- Tab Switcher -->
      <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding-bottom: 0.25rem;">
        <button
          @click="activeTab = 'invoices'"
          class="btn"
          :style="activeTab === 'invoices' ? 'background: rgba(59, 130, 246, 0.2); color: #60a5fa; border-color: rgba(59, 130, 246, 0.4);' : 'background: transparent; color: #94a3b8; border-color: transparent;'"
        >
          All B2B Invoices & 1Bill Vouchers
        </button>

        <button
          @click="activeTab = 'wires'"
          class="btn"
          :style="activeTab === 'wires' ? 'background: rgba(245, 158, 11, 0.2); color: #fbbf24; border-color: rgba(245, 158, 11, 0.4);' : 'background: transparent; color: #94a3b8; border-color: transparent;'"
          style="display: inline-flex; align-items: center; gap: 0.5rem;"
        >
          <span>Bank Wire Review Queue</span>
          <span
            v-if="pendingWires.length > 0"
            style="background: #f59e0b; color: #000; font-weight: 700; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px;"
          >
            {{ pendingWires.length }}
          </span>
        </button>
      </div>

      <!-- TAB 1: ALL INVOICES & 1BILL DIRECTORY -->
      <div v-if="activeTab === 'invoices'" style="display: flex; flex-direction: column; gap: 1rem;">
        <!-- Filter Toolbar -->
        <div class="card" style="padding: 1rem;">
          <form @submit.prevent="applyFilters" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 240px; position: relative;">
              <input
                type="text"
                v-model="filterForm.q"
                placeholder="Search invoice #, 1Bill #, or merchant name..."
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

            <select v-model="filterForm.status" class="form-control" style="width: 160px;">
              <option value="">All Statuses</option>
              <option value="unpaid">Unpaid</option>
              <option value="pending_review">Pending Review</option>
              <option value="paid">Paid</option>
              <option value="void">Void</option>
            </select>

            <button type="submit" class="btn btn-secondary">
              Filter
            </button>
            <button
              v-if="filterForm.q || filterForm.status"
              type="button"
              @click="resetFilters"
              class="btn btn-secondary"
              style="color: #94a3b8;"
            >
              Clear
            </button>
          </form>
        </div>

        <!-- Invoices Table -->
        <div class="card" style="overflow: hidden;">
          <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
              <thead>
                <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.02);">
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Invoice & Cycle</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Merchant & NTN</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Plan</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Subtotal + Tax</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Total (PKR)</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">1Bill / Kuickpay</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Status</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8; text-align: right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="inv in invoices.data"
                  :key="inv.id"
                  style="border-bottom: 1px solid rgba(255, 255, 255, 0.05); transition: background-color 0.15s;"
                >
                  <!-- Invoice # -->
                  <td style="padding: 1rem;">
                    <div style="font-weight: 700; color: #f8fafc; font-family: monospace;">
                      {{ inv.invoice_number }}
                    </div>
                    <div style="font-size: 0.75rem; color: #94a3b8; text-transform: capitalize;">
                      {{ inv.billing_cycle }} • Due {{ inv.due_date }}
                    </div>
                  </td>

                  <!-- Merchant -->
                  <td style="padding: 1rem;">
                    <div style="font-weight: 600; color: #e2e8f0; font-size: 0.875rem;">
                      {{ inv.tenant?.name || 'Unknown Merchant' }}
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b;">
                      NTN: {{ inv.tenant?.ntn || 'N/A' }} • {{ inv.tenant?.province || 'National' }}
                    </div>
                  </td>

                  <!-- Plan -->
                  <td style="padding: 1rem;">
                    <span class="badge" style="background: rgba(148, 163, 184, 0.15); color: #cbd5e1;">
                      {{ inv.plan?.name || 'Custom Plan' }}
                    </span>
                  </td>

                  <!-- Subtotal + Tax -->
                  <td style="padding: 1rem; font-size: 0.8125rem;">
                    <div>Rs. {{ formatMoney(inv.subtotal_rs) }}</div>
                    <div style="font-size: 0.72rem; color: #94a3b8;">
                      + {{ inv.tax_authority }} ({{ inv.tax_rate }}%): Rs. {{ formatMoney(inv.tax_rs) }}
                    </div>
                  </td>

                  <!-- Total -->
                  <td style="padding: 1rem;">
                    <div style="font-weight: 700; color: #34d399; font-size: 0.9375rem;">
                      PKR {{ formatMoney(inv.total_rs) }}
                    </div>
                  </td>

                  <!-- 1Bill / Kuickpay -->
                  <td style="padding: 1rem;">
                    <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                      <div style="font-family: monospace; font-size: 0.75rem; color: #60a5fa;" title="1Link 1Bill Consumer Number">
                        1Bill: {{ inv.onebill_consumer_number || 'N/A' }}
                      </div>
                      <div style="font-family: monospace; font-size: 0.72rem; color: #a78bfa;" title="Kuickpay Consumer ID">
                        KP: {{ inv.kuickpay_consumer_number || 'N/A' }}
                      </div>
                    </div>
                  </td>

                  <!-- Status -->
                  <td style="padding: 1rem;">
                    <span
                      class="badge"
                      :style="getInvoiceStatusStyle(inv.status)"
                      style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase;"
                    >
                      {{ inv.status.replace('_', ' ') }}
                    </span>
                  </td>

                  <!-- Actions -->
                  <td style="padding: 1rem; text-align: right;">
                    <div style="display: inline-flex; align-items: center; gap: 0.4rem;">
                      <button
                        v-if="inv.status === 'unpaid'"
                        @click="openLogWireModal(inv)"
                        class="btn btn-secondary"
                        style="padding: 0.25rem 0.6rem; font-size: 0.75rem;"
                        title="Log a bank transfer deposit"
                      >
                        Log Wire
                      </button>
                      <button
                        v-if="inv.status !== 'paid' && inv.status !== 'void'"
                        @click="voidInvoice(inv)"
                        class="btn btn-secondary"
                        style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: #f87171;"
                      >
                        Void
                      </button>
                    </div>
                  </td>
                </tr>

                <tr v-if="invoices.data.length === 0">
                  <td colspan="8" style="text-align: center; padding: 3rem 1rem; color: #64748b;">
                    No SaaS invoices found. Issue a B2B invoice to generate 1Bill consumer codes.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- TAB 2: PENDING BANK WIRE QUEUE -->
      <div v-else-if="activeTab === 'wires'" style="display: flex; flex-direction: column; gap: 1rem;">
        <div class="card" style="padding: 1.25rem;">
          <h3 style="font-size: 1rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.25rem;">
            Interbank Wire & IBFT Verification Queue
          </h3>
          <p style="color: #94a3b8; font-size: 0.8125rem;">
            When merchants settle subscriptions via direct bank transfer, Meezan Bank, HBL, or Raast, review the deposit slip and reference ID below to unlock their account.
          </p>
        </div>

        <div class="card" style="overflow: hidden;">
          <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
              <thead>
                <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.02);">
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Merchant & NTN</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Invoice #</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Amount (PKR)</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Bank / Gateway</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;">Reference / Slip</th>
                  <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #94a3b8; text-align: right;">Review Action</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="wire in pendingWires"
                  :key="wire.id"
                  style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);"
                >
                  <td style="padding: 1rem;">
                    <div style="font-weight: 600; color: #f8fafc;">
                      {{ wire.tenant?.name }}
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b;">
                      NTN: {{ wire.tenant?.ntn || 'N/A' }} • {{ wire.tenant?.province }}
                    </div>
                  </td>

                  <td style="padding: 1rem; font-family: monospace; font-size: 0.875rem; color: #60a5fa;">
                    {{ wire.invoice_number }}
                  </td>

                  <td style="padding: 1rem; font-weight: 700; color: #34d399; font-size: 1rem;">
                    PKR {{ formatMoney(wire.amount_rs) }}
                  </td>

                  <td style="padding: 1rem;">
                    <div style="font-weight: 600; color: #e2e8f0; font-size: 0.875rem;">
                      {{ wire.bank_name }}
                    </div>
                    <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">
                      {{ wire.gateway.replace('_', ' ') }}
                    </div>
                  </td>

                  <td style="padding: 1rem;">
                    <div style="font-family: monospace; font-size: 0.8125rem; color: #fbbf24;">
                      Ref: {{ wire.transaction_reference }}
                    </div>
                    <div v-if="wire.deposit_slip_url" style="margin-top: 0.2rem;">
                      <a :href="wire.deposit_slip_url" target="_blank" style="color: #38bdf8; font-size: 0.75rem; text-decoration: underline;">
                        View Deposit Slip ↗
                      </a>
                    </div>
                    <div v-if="wire.proof_notes" style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem;">
                      {{ wire.proof_notes }}
                    </div>
                  </td>

                  <td style="padding: 1rem; text-align: right;">
                    <div style="display: inline-flex; align-items: center; gap: 0.5rem;">
                      <button
                        @click="approvePayment(wire)"
                        class="btn btn-primary"
                        style="padding: 0.35rem 0.75rem; font-size: 0.8125rem; background: #10b981; border-color: #059669;"
                      >
                        Approve Payment
                      </button>
                      <button
                        @click="openRejectModal(wire)"
                        class="btn btn-danger"
                        style="padding: 0.35rem 0.75rem; font-size: 0.8125rem;"
                      >
                        Reject
                      </button>
                    </div>
                  </td>
                </tr>

                <tr v-if="pendingWires.length === 0">
                  <td colspan="6" style="text-align: center; padding: 3rem 1rem; color: #64748b;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 0.75rem; color: #34d399;">
                      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                      <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <div style="font-weight: 600; font-size: 0.9375rem; color: #94a3b8;">Review Queue is Empty</div>
                    <div style="font-size: 0.8125rem; margin-top: 0.25rem;">
                      No pending bank transfers awaiting accounting reconciliation.
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- MODAL: ISSUE B2B INVOICE -->
      <div v-if="showInvoiceModal" class="modal-backdrop" @click.self="showInvoiceModal = false">
        <div class="card" style="width: 100%; max-width: 580px; padding: 1.5rem; max-height: 90vh; overflow-y: auto;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h2 style="font-size: 1.25rem; font-weight: 700; color: #f8fafc;">
              Issue B2B SaaS Invoice
            </h2>
            <button @click="showInvoiceModal = false" class="btn btn-secondary" style="padding: 0.25rem 0.5rem;">✕</button>
          </div>

          <form @submit.prevent="submitInvoiceModal" style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                Merchant Tenant *
              </label>
              <select v-model="invoiceForm.tenant_id" @change="onTenantChange" class="form-control" style="width: 100%;" required>
                <option value="">-- Choose Merchant Organization --</option>
                <option v-for="t in tenants" :key="t.id" :value="t.id">
                  {{ t.name }} ({{ t.province || 'National' }}) - NTN: {{ t.ntn || 'N/A' }}
                </option>
              </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                  Plan Tier
                </label>
                <select v-model="invoiceForm.plan_id" @change="onPlanChange" class="form-control" style="width: 100%;">
                  <option value="">Custom Invoicing</option>
                  <option v-for="p in plans" :key="p.id" :value="p.id">
                    {{ p.name }} (PKR {{ p.price_minor / 100 }})
                  </option>
                </select>
              </div>

              <div>
                <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                  Billing Cycle *
                </label>
                <select v-model="invoiceForm.billing_cycle" class="form-control" style="width: 100%;" required>
                  <option value="monthly">Monthly</option>
                  <option value="quarterly">Quarterly</option>
                  <option value="annual">Annual</option>
                </select>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                  Subtotal (PKR) *
                </label>
                <input
                  type="number"
                  step="0.01"
                  v-model.number="invoiceForm.subtotal_rs"
                  required
                  placeholder="e.g. 5000"
                  class="form-control"
                  style="width: 100%;"
                />
              </div>

              <div>
                <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                  Tax Authority & Rate *
                </label>
                <select v-model="invoiceForm.tax_authority" @change="onTaxAuthorityChange" class="form-control" style="width: 100%;" required>
                  <option value="PRA">Punjab PRA (16%)</option>
                  <option value="SRB">Sindh SRB (13%)</option>
                  <option value="KPRA">Khyber Pakhtunkhwa KPRA (15%)</option>
                  <option value="BRA">Balochistan BRA (15%)</option>
                  <option value="FBR">Federal FBR (18%)</option>
                </select>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                  Tax Rate (%) *
                </label>
                <input
                  type="number"
                  step="0.01"
                  v-model.number="invoiceForm.tax_rate"
                  required
                  class="form-control"
                  style="width: 100%;"
                />
              </div>

              <div>
                <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                  Payment Due Date *
                </label>
                <input
                  type="date"
                  v-model="invoiceForm.due_date"
                  required
                  class="form-control"
                  style="width: 100%;"
                />
              </div>
            </div>

            <!-- Calculated Summary Box -->
            <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 1rem;">
              <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; color: #94a3b8; margin-bottom: 0.25rem;">
                <span>Subtotal:</span>
                <span>PKR {{ formatMoney(invoiceForm.subtotal_rs || 0) }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; color: #94a3b8; margin-bottom: 0.5rem;">
                <span>Sales Tax ({{ invoiceForm.tax_authority }} {{ invoiceForm.tax_rate }}%):</span>
                <span>PKR {{ formatMoney(calculatedTax) }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; font-weight: 700; color: #34d399; font-size: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 0.5rem;">
                <span>Total Invoice Due:</span>
                <span>PKR {{ formatMoney(calculatedTotal) }}</span>
              </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
              <button type="button" @click="showInvoiceModal = false" class="btn btn-secondary">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                {{ isSubmitting ? 'Issuing...' : 'Generate Invoice & 1Bill Code' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- MODAL: LOG BANK WIRE DEPOSIT -->
      <div v-if="showLogWireModal" class="modal-backdrop" @click.self="showLogWireModal = false">
        <div class="card" style="width: 100%; max-width: 500px; padding: 1.5rem;">
          <h2 style="font-size: 1.25rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.5rem;">
            Log Bank Wire Payment
          </h2>
          <p style="font-size: 0.8125rem; color: #94a3b8; margin-bottom: 1.25rem;">
            Record direct bank wire / IBFT transfer for invoice <strong>{{ selectedInvoice?.invoice_number }}</strong>.
          </p>

          <form @submit.prevent="submitLogWire" style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                Bank Name *
              </label>
              <input
                type="text"
                v-model="wireForm.bank_name"
                required
                placeholder="e.g. Meezan Bank, HBL, Bank Alfalah"
                class="form-control"
                style="width: 100%;"
              />
            </div>

            <div>
              <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                Bank Transaction Reference / RRN *
              </label>
              <input
                type="text"
                v-model="wireForm.transaction_reference"
                required
                placeholder="e.g. FT263080018829"
                class="form-control"
                style="width: 100%;"
              />
            </div>

            <div>
              <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                Deposit Slip URL / Image Link
              </label>
              <input
                type="text"
                v-model="wireForm.deposit_slip_url"
                placeholder="https://..."
                class="form-control"
                style="width: 100%;"
              />
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
              <button type="button" @click="showLogWireModal = false" class="btn btn-secondary">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                {{ isSubmitting ? 'Submitting...' : 'Submit Wire Transfer' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- MODAL: REJECT PAYMENT -->
      <div v-if="showRejectModal" class="modal-backdrop" @click.self="showRejectModal = false">
        <div class="card" style="width: 100%; max-width: 480px; padding: 1.5rem;">
          <h2 style="font-size: 1.25rem; font-weight: 700; color: #f87171; margin-bottom: 0.5rem;">
            Reject Bank Wire Payment
          </h2>
          <p style="font-size: 0.8125rem; color: #94a3b8; margin-bottom: 1.25rem;">
            Provide a clear rejection reason for transaction <strong>{{ selectedWire?.transaction_reference }}</strong>.
          </p>

          <form @submit.prevent="submitReject" style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <label class="form-label" style="display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.8125rem; color: #cbd5e1;">
                Rejection Reason *
              </label>
              <textarea
                v-model="rejectionReason"
                required
                rows="3"
                placeholder="e.g. Reference number not found in company bank account statement."
                class="form-control"
                style="width: 100%; resize: vertical;"
              ></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
              <button type="button" @click="showRejectModal = false" class="btn btn-secondary">
                Cancel
              </button>
              <button type="submit" class="btn btn-danger" :disabled="isSubmitting">
                Confirm Rejection
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
import { router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  invoices: {
    type: Object,
    required: true,
  },
  pendingWires: {
    type: Array,
    default: () => [],
  },
  metrics: {
    type: Object,
    required: true,
  },
  tenants: {
    type: Array,
    default: () => [],
  },
  plans: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const activeTab = ref(props.filters.tab === 'pending_wires' ? 'wires' : 'invoices');

const filterForm = ref({
  q: props.filters.q || '',
  status: props.filters.status || '',
});

function applyFilters() {
  router.get('/superadmin/billing', {
    ...filterForm.value,
    tab: activeTab.value === 'wires' ? 'pending_wires' : 'invoices',
  }, {
    preserveState: true,
    replace: true,
  });
}

function resetFilters() {
  filterForm.value = { q: '', status: '' };
  applyFilters();
}

// Create Invoice Modal State
const showInvoiceModal = ref(false);
const isSubmitting = ref(false);

const invoiceForm = ref({
  tenant_id: '',
  plan_id: '',
  billing_cycle: 'monthly',
  subtotal_rs: 5000,
  tax_authority: 'PRA',
  tax_rate: 16,
  due_date: new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10),
  notes: '',
});

function openCreateInvoiceModal() {
  showInvoiceModal.value = true;
}

function onTenantChange() {
  const t = props.tenants.find(ten => ten.id === invoiceForm.value.tenant_id);
  if (t && t.province) {
    const prov = t.province.toLowerCase();
    if (prov.includes('sindh')) {
      invoiceForm.value.tax_authority = 'SRB';
      invoiceForm.value.tax_rate = 13;
    } else if (prov.includes('kpk') || prov.includes('khyber')) {
      invoiceForm.value.tax_authority = 'KPRA';
      invoiceForm.value.tax_rate = 15;
    } else if (prov.includes('baloch')) {
      invoiceForm.value.tax_authority = 'BRA';
      invoiceForm.value.tax_rate = 15;
    } else {
      invoiceForm.value.tax_authority = 'PRA';
      invoiceForm.value.tax_rate = 16;
    }
  }
}

function onPlanChange() {
  const p = props.plans.find(plan => plan.id === invoiceForm.value.plan_id);
  if (p) {
    invoiceForm.value.subtotal_rs = p.price_minor / 100;
  }
}

function onTaxAuthorityChange() {
  switch (invoiceForm.value.tax_authority) {
    case 'SRB':
      invoiceForm.value.tax_rate = 13;
      break;
    case 'KPRA':
    case 'BRA':
      invoiceForm.value.tax_rate = 15;
      break;
    case 'FBR':
      invoiceForm.value.tax_rate = 18;
      break;
    default:
      invoiceForm.value.tax_rate = 16;
  }
}

const calculatedTax = computed(() => {
  const sub = Number(invoiceForm.value.subtotal_rs) || 0;
  const rate = Number(invoiceForm.value.tax_rate) || 0;
  return Math.round(sub * (rate / 100));
});

const calculatedTotal = computed(() => {
  const sub = Number(invoiceForm.value.subtotal_rs) || 0;
  return sub + calculatedTax.value;
});

function submitInvoiceModal() {
  isSubmitting.value = true;
  router.post('/superadmin/billing/invoices', invoiceForm.value, {
    onSuccess: () => {
      showInvoiceModal.value = false;
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
}

// Log Wire Modal State
const showLogWireModal = ref(false);
const selectedInvoice = ref(null);
const wireForm = ref({
  bank_name: 'Meezan Bank',
  transaction_reference: '',
  deposit_slip_url: '',
});

function openLogWireModal(inv) {
  selectedInvoice.value = inv;
  wireForm.value = {
    bank_name: 'Meezan Bank',
    transaction_reference: '',
    deposit_slip_url: '',
  };
  showLogWireModal.value = true;
}

function submitLogWire() {
  if (!selectedInvoice.value) return;
  isSubmitting.value = true;
  router.post(`/superadmin/billing/invoices/${selectedInvoice.value.id}/pay-wire`, wireForm.value, {
    onSuccess: () => {
      showLogWireModal.value = false;
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
}

// Reject Modal State
const showRejectModal = ref(false);
const selectedWire = ref(null);
const rejectionReason = ref('');

function openRejectModal(wire) {
  selectedWire.value = wire;
  rejectionReason.value = '';
  showRejectModal.value = true;
}

function approvePayment(wire) {
  if (confirm(`Approve bank transfer of PKR ${wire.amount_rs.toLocaleString()} from ${wire.tenant?.name}? This will mark the invoice as PAID and activate the subscription.`)) {
    router.post(`/superadmin/billing/transactions/${wire.id}/review`, {
      action: 'approve',
    });
  }
}

function submitReject() {
  if (!selectedWire.value) return;
  isSubmitting.value = true;
  router.post(`/superadmin/billing/transactions/${selectedWire.value.id}/review`, {
    action: 'reject',
    rejection_reason: rejectionReason.value,
  }, {
    onSuccess: () => {
      showRejectModal.value = false;
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
}

function voidInvoice(inv) {
  if (confirm(`Are you sure you want to void invoice ${inv.invoice_number}?`)) {
    router.post(`/superadmin/billing/invoices/${inv.id}/void`);
  }
}

function formatMoney(amount) {
  if (!amount) return '0.00';
  return Number(amount).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function getInvoiceStatusStyle(status) {
  switch (status) {
    case 'paid':
      return {
        background: 'rgba(52, 211, 153, 0.2)',
        color: '#34d399',
        border: '1px solid rgba(52, 211, 153, 0.4)',
      };
    case 'pending_review':
      return {
        background: 'rgba(245, 158, 11, 0.2)',
        color: '#fbbf24',
        border: '1px solid rgba(245, 158, 11, 0.4)',
      };
    case 'void':
      return {
        background: 'rgba(239, 68, 68, 0.2)',
        color: '#f87171',
        border: '1px solid rgba(239, 68, 68, 0.4)',
      };
    default:
      return {
        background: 'rgba(148, 163, 184, 0.2)',
        color: '#cbd5e1',
        border: '1px solid rgba(148, 163, 184, 0.4)',
      };
  }
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
</style>

