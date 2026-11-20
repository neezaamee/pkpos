<template>
  <AdminLayout :title="`Journal Entry - ${entry.entry_number}`">
    <div class="show-journal-page">
      <!-- Breadcrumb / Header -->
      <div class="page-header">
        <div>
          <div class="breadcrumb">
            <Link href="/journal-entries" class="breadcrumb-link">General Journal</Link>
            <span class="breadcrumb-sep">/</span>
            <span>Entry Details</span>
          </div>
          <h1 class="page-title">{{ entry.entry_number }}</h1>
          <p class="page-subtitle">
            Posted on <strong>{{ entry.entry_date }}</strong> &bull; Source: <span class="badge-source">{{ entry.source_type || 'manual' }}</span>
          </p>
        </div>
        <div class="header-actions">
          <Link href="/journal-entries" class="btn btn-secondary">&larr; Back to Journal</Link>
          <button @click="printEntry" class="btn btn-secondary">Print Voucher</button>
        </div>
      </div>

      <!-- Entry Header Details Card -->
      <div class="card details-card">
        <div class="details-grid">
          <div>
            <div class="detail-label">Entry Date</div>
            <div class="detail-val font-mono">{{ entry.entry_date }}</div>
          </div>
          <div>
            <div class="detail-label">Reference Number</div>
            <div class="detail-val font-mono">{{ entry.reference || 'None' }}</div>
          </div>
          <div>
            <div class="detail-label">Status</div>
            <div class="detail-val">
              <span class="badge-status" :class="'status-' + entry.status">{{ entry.status.toUpperCase() }}</span>
            </div>
          </div>
          <div>
            <div class="detail-label">Posted By</div>
            <div class="detail-val">{{ entry.created_by_user?.name || 'System Auto-Post' }}</div>
          </div>
        </div>
        <div class="narration-box">
          <div class="detail-label">Narration / Description:</div>
          <div class="narration-text">{{ entry.description }}</div>
        </div>
      </div>

      <!-- Lines Table -->
      <div class="table-container card">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width: 140px;">Account Code</th>
              <th>Account Name</th>
              <th style="width: 130px;">Classification</th>
              <th>Memo</th>
              <th style="width: 140px; text-align: right;">Debit (Rs.)</th>
              <th style="width: 140px; text-align: right;">Credit (Rs.)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in entry.lines" :key="line.id">
              <td class="font-mono font-bold">{{ line.account.code }}</td>
              <td>
                <Link :href="`/accounts/${line.account.id}`" class="link-acc">
                  {{ line.account.name }}
                </Link>
              </td>
              <td>
                <span class="badge" :class="'badge-' + line.account.type">
                  {{ line.account.type.toUpperCase() }}
                </span>
              </td>
              <td class="text-muted font-sm">{{ line.memo || '—' }}</td>
              <td class="text-right font-mono font-bold" :class="{ 'text-debit': line.debit_minor > 0 }">
                {{ line.debit_minor > 0 ? formatMoney(line.debit_minor) : '—' }}
              </td>
              <td class="text-right font-mono font-bold" :class="{ 'text-credit': line.credit_minor > 0 }">
                {{ line.credit_minor > 0 ? formatMoney(line.credit_minor) : '—' }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="row-totals">
              <td colspan="4" class="text-right font-bold">Total Voucher Balancing:</td>
              <td class="text-right font-mono font-bold text-debit">
                Rs. {{ formatMoney(entry.total_debit_minor) }}
              </td>
              <td class="text-right font-mono font-bold text-credit">
                Rs. {{ formatMoney(entry.total_credit_minor) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  entry: any;
}>();

function printEntry() {
  window.print();
}

function formatMoney(minor: number): string {
  const rs = (minor || 0) / 100;
  return rs.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<style scoped>
.show-journal-page {
  padding: 1.5rem;
  max-width: 1100px;
  margin: 0 auto;
}

.breadcrumb {
  display: flex;
  gap: 0.5rem;
  font-size: 0.8125rem;
  color: #94a3b8;
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
  color: #f8fafc;
  margin: 0;
}
.page-subtitle {
  color: #94a3b8;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  padding: 1.25rem;
  margin-bottom: 1.5rem;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.25rem;
  border-bottom: 1px solid #334155;
  padding-bottom: 1rem;
}

.detail-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
}

.detail-val {
  font-size: 1rem;
  font-weight: 700;
  color: #f8fafc;
  margin-top: 0.25rem;
}

.narration-box {
  margin-top: 1rem;
}
.narration-text {
  font-size: 0.9375rem;
  color: #e2e8f0;
  margin-top: 0.25rem;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th, .data-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #334155;
  font-size: 0.875rem;
}

.data-table th {
  background: rgba(15, 23, 42, 0.6);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  color: #94a3b8;
}

.row-totals {
  background: rgba(15, 23, 42, 0.7);
  font-weight: 700;
}

.link-acc {
  color: #38bdf8;
  text-decoration: none;
  font-weight: 600;
}
.link-acc:hover { text-decoration: underline; }

.badge-source {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 0.15rem 0.4rem;
  border-radius: 0.2rem;
  background: rgba(148, 163, 184, 0.15);
  color: #94a3b8;
}

.badge-status {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.15rem 0.4rem;
  border-radius: 0.2rem;
}
.status-posted { background: rgba(74, 222, 128, 0.15); color: #4ade80; }
.status-draft { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }
.status-voided { background: rgba(248, 113, 113, 0.15); color: #f87171; }

.badge {
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.2rem 0.45rem;
  border-radius: 0.25rem;
}
.badge-asset { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }
.badge-liability { background: rgba(248, 113, 113, 0.15); color: #f87171; }
.badge-equity { background: rgba(192, 132, 252, 0.15); color: #c084fc; }
.badge-revenue { background: rgba(74, 222, 128, 0.15); color: #4ade80; }
.badge-expense { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }

.text-debit { color: #38bdf8; }
.text-credit { color: #4ade80; }

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
.btn-secondary { background: #334155; color: #f8fafc; }

.font-mono { font-family: monospace; }
.font-bold { font-weight: 700; }
.font-sm { font-size: 0.75rem; }
.text-right { text-align: right; }
.text-muted { color: #94a3b8; }
</style>
