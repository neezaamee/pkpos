<template>
  <AdminLayout title="Create Purchase Order">
    <div style="max-width: 1000px; margin: 0 auto;">
      <!-- Header -->
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
        <div>
          <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-primary); margin: 0;">
            Create Purchase Order
          </h2>
          <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
            Issue distributor procurement order with multi-unit packaging (cartons/packs) and unit costs
          </div>
        </div>
        <Link href="/purchases" class="btn btn-outline" style="padding: 0.4rem 0.85rem;">
          &larr; Back to Purchase Orders
        </Link>
      </div>

      <div class="card" style="padding: 2rem;">
        <form @submit.prevent="submit">
          <!-- 1. Header Information -->
          <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 1rem;">
            1. Procurement Order Details
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Select Supplier / Distributor <span style="color: #ef4444;">*</span>
              </label>
              <select v-model="form.supplier_id" class="form-control" required @change="onSupplierSelected">
                <option value="" disabled>-- Select Supplier --</option>
                <option v-for="s in suppliers" :key="s.id" :value="s.id">
                  {{ s.name }} ({{ s.phone }})
                </option>
              </select>
              <div v-if="selectedSupplierDetails" style="font-size: 0.75rem; color: #6366f1; margin-top: 0.25rem;">
                Terms: {{ selectedSupplierDetails.payment_terms_days === 0 ? 'Cash on Delivery' : `Net ${selectedSupplierDetails.payment_terms_days} Days` }}
              </div>
              <div v-if="form.errors.supplier_id" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
                {{ form.errors.supplier_id }}
              </div>
            </div>

            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Receiving Warehouse / Location <span style="color: #ef4444;">*</span>
              </label>
              <select v-model="form.location_id" class="form-control" required>
                <option value="" disabled>-- Select Location --</option>
                <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                  {{ loc.name }}
                </option>
              </select>
              <div v-if="form.errors.location_id" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;">
                {{ form.errors.location_id }}
              </div>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                Expected Delivery Date
              </label>
              <input
                v-model="form.expected_delivery_date"
                type="date"
                class="form-control"
              />
            </div>

            <div>
              <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; margin-bottom: 0.35rem;">
                PO Notes &amp; Dispatch Instructions
              </label>
              <input
                v-model="form.notes"
                type="text"
                class="form-control"
                placeholder="e.g. Deliver before 12 PM at rear gate, include batch certificates"
              />
            </div>
          </div>

          <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;" />

          <!-- 2. Purchase Order Items -->
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
            <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">
              2. Order Line Items ({{ form.items.length }})
            </div>
            <button
              type="button"
              class="btn btn-outline"
              style="padding: 0.35rem 0.75rem; font-size: 0.8rem; display: flex; align-items: center; gap: 0.35rem;"
              @click="addItemRow"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
              </svg>
              <span>Add Item Line</span>
            </button>
          </div>

          <div style="overflow-x: auto; margin-bottom: 1.5rem;">
            <table class="table" style="font-size: 0.85rem;">
              <thead>
                <tr>
                  <th style="min-width: 250px;">Product</th>
                  <th style="min-width: 140px;">Unit / Packaging</th>
                  <th style="width: 110px;">Qty</th>
                  <th style="width: 130px;">Unit Cost (Rs.)</th>
                  <th style="width: 120px; text-align: right;">Line Total</th>
                  <th style="width: 50px; text-align: center;"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="form.items.length === 0">
                  <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    Click "Add Item Line" to add products to this purchase order
                  </td>
                </tr>
                <tr v-for="(row, idx) in form.items" :key="idx">
                  <td>
                    <select
                      v-model="row.product_id"
                      class="form-control"
                      style="font-size: 0.825rem;"
                      required
                      @change="onProductChange(idx)"
                    >
                      <option value="" disabled>-- Select Product --</option>
                      <option v-for="p in products" :key="p.id" :value="p.id">
                        {{ p.name }} (SKU: {{ p.sku || 'N/A' }})
                      </option>
                    </select>
                  </td>

                  <td>
                    <select
                      v-model="row.unit_code"
                      class="form-control"
                      style="font-size: 0.825rem;"
                      @change="onUnitChange(idx)"
                    >
                      <option :value="getBaseUnit(row.product_id)">
                        {{ getBaseUnit(row.product_id) }} (Base 1x)
                      </option>
                      <option
                        v-for="conv in getProductConversions(row.product_id)"
                        :key="conv.id"
                        :value="conv.unit_code || conv.to_uom_code"
                      >
                        {{ conv.unit_name || conv.pack_name || conv.unit_code }} ({{ conv.conversion_factor }}x)
                      </option>
                    </select>
                    <div v-if="row.unit_conversion_factor > 1" style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem;">
                      = {{ row.ordered_quantity * row.unit_conversion_factor }} base units
                    </div>
                  </td>

                  <td>
                    <input
                      v-model.number="row.ordered_quantity"
                      type="number"
                      step="any"
                      min="0.001"
                      class="form-control"
                      style="font-family: var(--font-mono); font-size: 0.85rem;"
                      placeholder="1"
                      required
                    />
                  </td>

                  <td>
                    <input
                      v-model.number="row.unit_cost_pkr"
                      type="number"
                      step="any"
                      min="0"
                      class="form-control"
                      style="font-family: var(--font-mono); font-size: 0.85rem;"
                      placeholder="0.00"
                      required
                    />
                  </td>

                  <td style="text-align: right; font-family: var(--font-mono); font-weight: 700; color: var(--text-primary); vertical-align: middle;">
                    Rs. {{ computeLineTotal(row).toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                  </td>

                  <td style="text-align: center; vertical-align: middle;">
                    <button
                      type="button"
                      class="btn btn-secondary"
                      style="padding: 0.25rem 0.5rem; color: #ef4444;"
                      title="Remove Item"
                      @click="removeItemRow(idx)"
                    >
                      &times;
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Total Summary Box -->
          <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.75rem;">
            <div>
              <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
                Estimated Order Subtotal
              </div>
              <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary); font-family: var(--font-mono); margin-top: 0.25rem;">
                Rs. {{ grandTotalPkr.toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
              </div>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
              <Link href="/purchases" class="btn btn-secondary">
                Cancel
              </Link>
              <button
                type="submit"
                class="btn btn-primary"
                :disabled="form.processing || form.items.length === 0"
                style="min-width: 170px;"
              >
                <span v-if="form.processing">Creating PO...</span>
                <span v-else>Issue Purchase Order</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

interface SupplierOption {
  id: string
  name: string
  phone: string
  payment_terms_days: number
}

interface LocationOption {
  id: string
  name: string
}

interface UomConversion {
  id: string
  to_uom_code: string
  conversion_factor: number
  pack_name: string | null
}

interface ProductOption {
  id: string
  name: string
  sku: string | null
  barcode: string | null
  cost_minor: number
  price_minor: number
  unit_of_measure: string | null
  uom_conversions?: UomConversion[]
}

interface FormItemRow {
  product_id: string
  unit_code: string
  unit_conversion_factor: number
  ordered_quantity: number
  unit_cost_pkr: number
  cost_price_minor: number
  tax_rate_basis_points: number
}

const props = defineProps<{
  suppliers: SupplierOption[]
  locations: LocationOption[]
  products: ProductOption[]
}>()

const selectedSupplierDetails = ref<SupplierOption | null>(null)

const form = useForm({
  supplier_id: props.suppliers.length > 0 ? props.suppliers[0].id : '',
  location_id: props.locations.length > 0 ? props.locations[0].id : '',
  expected_delivery_date: '',
  notes: '',
  items: [
    {
      product_id: '',
      unit_code: 'pcs',
      unit_conversion_factor: 1,
      ordered_quantity: 1,
      unit_cost_pkr: 0,
      cost_price_minor: 0,
      tax_rate_basis_points: 0,
    },
  ] as FormItemRow[],
})

function onSupplierSelected() {
  selectedSupplierDetails.value = props.suppliers.find(s => s.id === form.supplier_id) || null
}

function getProduct(productId: string): ProductOption | undefined {
  return props.products.find(p => p.id === productId)
}

function getBaseUnit(productId: string): string {
  const p = getProduct(productId)
  return p?.unit_of_measure || 'pcs'
}

function getProductConversions(productId: string): UomConversion[] {
  const p = getProduct(productId)
  return p?.uom_conversions || []
}

function onProductChange(index: number) {
  const row = form.items[index]
  const prod = getProduct(row.product_id)
  if (prod) {
    row.unit_code = prod.unit_of_measure || 'pcs'
    row.unit_conversion_factor = 1
    row.unit_cost_pkr = (prod.cost_minor || 0) / 100
  }
}

function onUnitChange(index: number) {
  const row = form.items[index]
  const prod = getProduct(row.product_id)
  if (!prod) return

  if (row.unit_code === (prod.unit_of_measure || 'pcs')) {
    row.unit_conversion_factor = 1
  } else {
    const conv = prod.uom_conversions?.find(c => (c.unit_code || c.to_uom_code) === row.unit_code)
    if (conv) {
      row.unit_conversion_factor = conv.conversion_factor
      // Auto-scale estimated unit pack cost
      row.unit_cost_pkr = ((prod.cost_minor || 0) / 100) * conv.conversion_factor
    }
  }
}

function addItemRow() {
  form.items.push({
    product_id: '',
    unit_code: 'pcs',
    unit_conversion_factor: 1,
    ordered_quantity: 1,
    unit_cost_pkr: 0,
    cost_price_minor: 0,
    tax_rate_basis_points: 0,
  })
}

function removeItemRow(index: number) {
  form.items.splice(index, 1)
}

function computeLineTotal(row: FormItemRow): number {
  return (row.ordered_quantity || 0) * (row.unit_cost_pkr || 0)
}

const grandTotalPkr = computed(() => {
  return form.items.reduce((sum, item) => sum + computeLineTotal(item), 0)
})

function submit() {
  // Sync cost_price_minor before posting
  form.items.forEach(item => {
    item.cost_price_minor = Math.round((item.unit_cost_pkr || 0) * 100)
  })
  form.post('/purchases')
}
</script>
