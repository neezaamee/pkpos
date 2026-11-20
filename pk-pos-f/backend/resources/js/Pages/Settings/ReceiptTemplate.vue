<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  template: {
    type: Object,
    required: true,
  },
  tenant: {
    type: Object,
    required: true,
  },
})

const form = useForm({
  business_name: props.template.business_name || props.tenant.name || '',
  branch_name: props.template.branch_name || 'Main Branch',
  address_line1: props.template.address_line1 || 'Commercial Area',
  address_line2: props.template.address_line2 || '',
  phone: props.template.phone || '021-111-222-333',
  email: props.template.email || '',
  website: props.template.website || '',
  ntn_label: props.template.ntn_label || 'NTN:',
  strn_label: props.template.strn_label || 'STRN:',
  pos_id_label: props.template.pos_id_label || 'FBR POS ID:',
  paper_width: props.template.paper_width || '80mm',
  show_tax_breakdown: props.template.show_tax_breakdown ?? true,
  show_customer_info: props.template.show_customer_info ?? true,
  show_fbr_qr: props.template.show_fbr_qr ?? true,
  header_note: props.template.header_note || 'Official Fiscal Receipt',
  footer_policy: props.template.footer_policy || 'Goods once sold can only be exchanged within 3 days with original receipt.',
  footer_greeting: props.template.footer_greeting || 'Thank you for shopping with us! JazakAllah Khair.',
  logo_url: props.template.logo_url || '',
})

const submit = () => {
  form.put('/settings/receipt-template', {
    preserveScroll: true,
  })
}

// Receipt preview width style based on 80mm vs 58mm
const previewWidth = computed(() => {
  return form.paper_width === '58mm' ? '250px' : '320px'
})
</script>

<template>
  <AdminLayout>
    <div class="page-container" style="padding: 1.5rem; max-width: 1400px; margin: 0 auto;">
      <!-- Page Header -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
          <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <span style="font-size: 0.8rem; font-weight: 700; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.05em;">Store Configuration</span>
            <span style="color: var(--text-muted);">&bull;</span>
            <span style="font-size: 0.8rem; color: var(--text-muted);">POS Hardware &amp; Printing</span>
          </div>
          <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0 0 0.25rem 0;">
            Receipt Template &amp; Thermal Branding
          </h1>
          <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;">
            Customize receipt header, store address, tax &amp; NTN identifiers, 80mm/58mm paper width, and return policies.
          </p>
        </div>

        <button
          type="button"
          @click="submit"
          :disabled="form.processing"
          class="btn-primary"
          style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; font-weight: 600; border-radius: 8px; background: #0284c7; color: #fff; border: none; cursor: pointer;"
        >
          <svg v-if="form.processing" class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="12" cy="12" r="10" stroke-width="4" stroke-opacity="0.25"></circle>
            <path d="M12 2a10 10 0 0 1 10 10" stroke-width="4"></path>
          </svg>
          <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
            <polyline points="17 21 17 13 7 13 7 21"></polyline>
            <polyline points="7 3 7 8 15 8"></polyline>
          </svg>
          {{ form.processing ? 'Saving Changes...' : 'Save Template' }}
        </button>
      </div>

      <!-- Flash Notification -->
      <div v-if="$page.props.flash?.success" style="padding: 0.875rem 1.25rem; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; color: #10b981; margin-bottom: 1.5rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
          <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        {{ $page.props.flash.success }}
      </div>

      <!-- Main Layout: Editor on Left, Live Thermal Preview on Right -->
      <div style="display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(320px, 1fr); gap: 2rem; align-items: start;">
        
        <!-- Left: Form Controls -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
          
          <!-- Section 1: Business Identity & Contact Details -->
          <div class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
              </svg>
              <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0;">Store Branding &amp; Contact</h3>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  Business / Store Name
                </label>
                <input
                  type="text"
                  v-model="form.business_name"
                  class="form-control"
                  placeholder="e.g. Al-Madina Supermarket"
                  style="width: 100%; padding: 0.55rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.875rem;"
                />
              </div>

              <div>
                <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  Branch / Outlet Name
                </label>
                <input
                  type="text"
                  v-model="form.branch_name"
                  class="form-control"
                  placeholder="e.g. DHA Phase 5 Branch"
                  style="width: 100%; padding: 0.55rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.875rem;"
                />
              </div>

              <div style="grid-column: span 2;">
                <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  Address Line 1
                </label>
                <input
                  type="text"
                  v-model="form.address_line1"
                  class="form-control"
                  placeholder="e.g. Shop #12, Commercial Broadway, Phase 5"
                  style="width: 100%; padding: 0.55rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.875rem;"
                />
              </div>

              <div>
                <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  Phone Number(s)
                </label>
                <input
                  type="text"
                  v-model="form.phone"
                  class="form-control"
                  placeholder="e.g. 042-35894120 / 0300-1234567"
                  style="width: 100%; padding: 0.55rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.875rem;"
                />
              </div>

              <div>
                <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  Email or Website (Optional)
                </label>
                <input
                  type="text"
                  v-model="form.email"
                  class="form-control"
                  placeholder="e.g. sales@madinastore.pk"
                  style="width: 100%; padding: 0.55rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.875rem;"
                />
              </div>
            </div>
          </div>

          <!-- Section 2: Printer Paper & Tax Identifiers -->
          <div class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
              </svg>
              <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0;">Thermal Width &amp; Tax Labels</h3>
            </div>

            <!-- Paper Width Selector -->
            <div style="margin-bottom: 1.25rem;">
              <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem;">
                Thermal Roll Paper Width
              </label>
              <div style="display: flex; gap: 1rem;">
                <button
                  type="button"
                  @click="form.paper_width = '80mm'"
                  :style="{
                    flex: '1',
                    padding: '0.65rem 1rem',
                    borderRadius: '8px',
                    border: form.paper_width === '80mm' ? '2px solid #0284c7' : '1px solid var(--border-color)',
                    background: form.paper_width === '80mm' ? 'rgba(2, 132, 199, 0.15)' : 'var(--bg-elevated)',
                    color: form.paper_width === '80mm' ? '#38bdf8' : 'var(--text-secondary)',
                    fontWeight: '700',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '0.5rem'
                  }"
                >
                  <span style="font-size: 1rem;">🖨️</span>
                  <span>80mm (Standard POS)</span>
                </button>

                <button
                  type="button"
                  @click="form.paper_width = '58mm'"
                  :style="{
                    flex: '1',
                    padding: '0.65rem 1rem',
                    borderRadius: '8px',
                    border: form.paper_width === '58mm' ? '2px solid #0284c7' : '1px solid var(--border-color)',
                    background: form.paper_width === '58mm' ? 'rgba(2, 132, 199, 0.15)' : 'var(--bg-elevated)',
                    color: form.paper_width === '58mm' ? '#38bdf8' : 'var(--text-secondary)',
                    fontWeight: '700',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '0.5rem'
                  }"
                >
                  <span style="font-size: 1rem;">🧾</span>
                  <span>58mm (Compact Roll)</span>
                </button>
              </div>
            </div>

            <!-- Tax Identifier Labels -->
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
              <div>
                <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  NTN Label
                </label>
                <input
                  type="text"
                  v-model="form.ntn_label"
                  class="form-control"
                  style="width: 100%; padding: 0.55rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.875rem;"
                />
              </div>

              <div>
                <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  STRN / Sales Tax Label
                </label>
                <input
                  type="text"
                  v-model="form.strn_label"
                  class="form-control"
                  style="width: 100%; padding: 0.55rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.875rem;"
                />
              </div>

              <div>
                <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  FBR POS ID Label
                </label>
                <input
                  type="text"
                  v-model="form.pos_id_label"
                  class="form-control"
                  style="width: 100%; padding: 0.55rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.875rem;"
                />
              </div>
            </div>

            <!-- Display Toggles -->
            <div style="display: flex; flex-direction: column; gap: 0.75rem; padding-top: 0.5rem; border-top: 1px solid var(--border-color);">
              <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                <input type="checkbox" v-model="form.show_tax_breakdown" style="width: 18px; height: 18px; accent-color: #0284c7;" />
                <span style="font-size: 0.875rem; color: var(--text-primary); font-weight: 500;">
                  Show Detailed Tax Breakdown (Base amount + GST / PRA item lines)
                </span>
              </label>

              <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                <input type="checkbox" v-model="form.show_customer_info" style="width: 18px; height: 18px; accent-color: #0284c7;" />
                <span style="font-size: 0.875rem; color: var(--text-primary); font-weight: 500;">
                  Show Customer Name &amp; Phone (when tagged to sale or credit khata)
                </span>
              </label>

              <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                <input type="checkbox" v-model="form.show_fbr_qr" style="width: 18px; height: 18px; accent-color: #0284c7;" />
                <span style="font-size: 0.875rem; color: var(--text-primary); font-weight: 500;">
                  Print FBR Verification QR Code &amp; USIN Fiscal Barcode
                </span>
              </label>
            </div>
          </div>

          <!-- Section 3: Custom Text, Policies & Greetings -->
          <div class="card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
              </svg>
              <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0;">Header Note &amp; Return Policy</h3>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
              <div>
                <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  Header Tagline / Subtitle
                </label>
                <input
                  type="text"
                  v-model="form.header_note"
                  class="form-control"
                  placeholder="e.g. Official Fiscal Tax Receipt"
                  style="width: 100%; padding: 0.55rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.875rem;"
                />
              </div>

              <div>
                <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  Return &amp; Exchange Policy
                </label>
                <textarea
                  v-model="form.footer_policy"
                  rows="2"
                  class="form-control"
                  placeholder="e.g. Goods once sold can only be exchanged within 3 days with original receipt."
                  style="width: 100%; padding: 0.55rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.875rem;"
                ></textarea>
              </div>

              <div>
                <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem;">
                  Footer Greeting
                </label>
                <input
                  type="text"
                  v-model="form.footer_greeting"
                  class="form-control"
                  placeholder="e.g. Thank you for shopping with us! JazakAllah Khair."
                  style="width: 100%; padding: 0.55rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 0.875rem;"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Live Thermal Receipt Preview -->
        <div style="position: sticky; top: 1.5rem;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.05em;">
                Live Thermal Preview
              </span>
              <span style="font-size: 0.75rem; background: rgba(56, 189, 248, 0.15); color: #38bdf8; padding: 0.15rem 0.5rem; border-radius: 4px; font-weight: 700;">
                {{ form.paper_width }}
              </span>
            </div>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Real-time WYSIWYG</span>
          </div>

          <!-- Realistic Paper Container -->
          <div style="display: flex; justify-content: center; background: #0f172a; padding: 2rem 1rem; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: inset 0 2px 8px rgba(0,0,0,0.4);">
            <div
              :style="{
                width: previewWidth,
                background: '#ffffff',
                color: '#111827',
                fontFamily: `'Courier New', Courier, monospace`,
                fontSize: form.paper_width === '58mm' ? '11px' : '12px',
                lineHeight: '1.35',
                padding: '16px 12px',
                boxShadow: '0 8px 24px rgba(0,0,0,0.5)',
                borderRadius: '2px',
                transition: 'all 0.2s ease',
              }"
            >
              <!-- Receipt Header -->
              <div style="text-align: center; margin-bottom: 8px;">
                <div style="font-size: 15px; font-weight: 900; letter-spacing: -0.5px; text-transform: uppercase;">
                  {{ form.business_name || 'STORE NAME' }}
                </div>
                <div v-if="form.branch_name" style="font-size: 11px; font-weight: 700;">
                  {{ form.branch_name }}
                </div>
                <div v-if="form.address_line1" style="font-size: 10px; color: #374151;">
                  {{ form.address_line1 }}
                </div>
                <div v-if="form.phone" style="font-size: 10px; color: #374151;">
                  Tel: {{ form.phone }}
                </div>
                <div v-if="form.email" style="font-size: 9px; color: #4b5563;">
                  {{ form.email }}
                </div>
                <div v-if="form.header_note" style="font-size: 10px; font-weight: 700; margin-top: 4px; border-top: 1px dashed #6b7280; border-bottom: 1px dashed #6b7280; padding: 2px 0;">
                  {{ form.header_note }}
                </div>
              </div>

              <!-- Fiscal & Tax Numbers -->
              <div style="font-size: 10px; border-bottom: 1px dashed #9ca3af; padding-bottom: 4px; margin-bottom: 6px;">
                <div style="display: flex; justify-content: space-between;">
                  <span>{{ form.ntn_label }}</span>
                  <span style="font-weight: 700;">{{ tenant.ntn || '9999999-9' }}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                  <span>{{ form.strn_label }}</span>
                  <span style="font-weight: 700;">{{ tenant.strn || '32-77-8761-123-45' }}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                  <span>{{ form.pos_id_label }}</span>
                  <span style="font-weight: 700;">{{ tenant.pos_id }}</span>
                </div>
              </div>

              <!-- Sale Metadata -->
              <div style="font-size: 10px; margin-bottom: 6px;">
                <div style="display: flex; justify-content: space-between;">
                  <span>Inv #: INV-20261120-7A89</span>
                  <span>17:30</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                  <span>Date: 20-Nov-2026</span>
                  <span>Cashier: Admin</span>
                </div>
                <div v-if="form.show_customer_info" style="display: flex; justify-content: space-between; border-top: 1px dotted #d1d5db; margin-top: 2px; padding-top: 2px;">
                  <span>Customer:</span>
                  <span style="font-weight: 700;">Ali Khan (0300-1234567)</span>
                </div>
              </div>

              <!-- Items Table Header -->
              <div style="border-top: 1px dashed #111827; border-bottom: 1px dashed #111827; padding: 3px 0; font-weight: 800; display: flex; justify-content: space-between; font-size: 10px;">
                <span>ITEM</span>
                <span>QTY x PRICE</span>
                <span>TOTAL</span>
              </div>

              <!-- Sample Items -->
              <div style="padding: 4px 0; border-bottom: 1px dashed #111827; font-size: 10px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                  <div>
                    <div style="font-weight: 700;">Basmati Rice Super</div>
                    <div style="font-size: 9px; color: #4b5563;">2.0 kg x 350.00</div>
                  </div>
                  <div style="font-weight: 700;">700.00</div>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                  <div>
                    <div style="font-weight: 700;">Cooking Oil 1L Pouch</div>
                    <div style="font-size: 9px; color: #4b5563;">1.0 pcs x 520.00</div>
                  </div>
                  <div style="font-weight: 700;">520.00</div>
                </div>
              </div>

              <!-- Financial Totals -->
              <div style="padding: 6px 0; font-size: 11px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                  <span>Subtotal:</span>
                  <span>Rs. 1,220.00</span>
                </div>
                <div v-if="form.show_tax_breakdown" style="display: flex; justify-content: space-between; font-size: 10px; color: #374151; margin-bottom: 2px;">
                  <span>FBR GST (18%):</span>
                  <span>Rs. 219.60</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-weight: 900; font-size: 13px; border-top: 1px solid #111827; border-bottom: 1px solid #111827; padding: 4px 0; margin-top: 3px;">
                  <span>NET TOTAL:</span>
                  <span>Rs. 1,439.60</span>
                </div>

                <!-- Tender Breakdown (Showing Split Tender Example) -->
                <div style="margin-top: 6px; font-size: 10px; border-bottom: 1px dotted #9ca3af; padding-bottom: 4px;">
                  <div style="display: flex; justify-content: space-between;">
                    <span>Tender [CASH]:</span>
                    <span>Rs. 1,000.00</span>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span>Tender [CARD]:</span>
                    <span>Rs. 439.60</span>
                  </div>
                </div>
              </div>

              <!-- FBR QR Code Area -->
              <div v-if="form.show_fbr_qr" style="text-align: center; margin: 8px 0; padding-top: 4px;">
                <div style="width: 72px; height: 72px; margin: 0 auto; background: #e5e7eb; border: 1px solid #9ca3af; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 700; color: #374151;">
                  [FBR QR]
                </div>
                <div style="font-size: 9px; font-weight: 700; margin-top: 3px;">
                  USIN: 78601-20261120-0012
                </div>
                <div style="font-size: 8px; color: #6b7280;">
                  Verified by FBR Digital Invoicing
                </div>
              </div>

              <!-- Footer Policies & Greetings -->
              <div style="text-align: center; margin-top: 8px; font-size: 9px; border-top: 1px dashed #9ca3af; padding-top: 6px;">
                <div v-if="form.footer_policy" style="margin-bottom: 4px; color: #374151; font-style: italic;">
                  {{ form.footer_policy }}
                </div>
                <div v-if="form.footer_greeting" style="font-weight: 700;">
                  {{ form.footer_greeting }}
                </div>
                <div style="margin-top: 6px; font-size: 8px; color: #9ca3af;">
                  Powered by Pakistan POS Cloud
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>
