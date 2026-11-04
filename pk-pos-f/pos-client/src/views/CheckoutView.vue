<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import type { CachedProduct, CartItem, PaymentMethod, ProcessResult } from '../types/pos';
import { formatPKR } from '../types/pos';
import { getCachedProducts, getProductByBarcode, processSale, openCashDrawer } from '../services/posService';

interface HeldSale {
  id: string;
  heldAt: string;
  items: CartItem[];
  customer?: { name?: string; ntn?: string; cnic?: string };
  discountPercent?: number;
  totalMinor: number;
}

const searchQuery = ref('');
const products = ref<CachedProduct[]>([]);
const isSearching = ref(false);
const cart = ref<CartItem[]>([]);
const searchInputRef = ref<HTMLInputElement | null>(null);

// Customer Identification State (FBR B2B / High-Value Rs 100k+ Compliance)
const customerDetails = ref<{ name: string; ntn: string; cnic: string }>({
  name: '',
  ntn: '',
  cnic: '',
});
const showCustomerModal = ref(false);

// Discount State
const discountPercent = ref<number>(0);
const showDiscountModal = ref(false);
const tempDiscountInput = ref<number>(0);

// Held / Parked Sales State
const heldSales = ref<HeldSale[]>([]);
const showHeldSalesModal = ref(false);

// Shortcuts Help Modal State
const showHelpModal = ref(false);

// Payment State
const selectedPayment = ref<PaymentMethod>('cash');
const amountTendered = ref<number | null>(null);
const nonCashConfirmed = ref<boolean>(false);
const isProcessing = ref<boolean>(false);
const processError = ref<string | null>(null);

// Sale Confirmation Modal State
const showConfirmation = ref<boolean>(false);
const completedResult = ref<{
  saleNumber: string;
  result: ProcessResult;
  totalMinor: number;
  tenderedMinor: number;
  changeMinor: number;
  paymentMethod: string;
} | null>(null);

// Hardware Barcode Scanner Buffer
let barcodeBuffer = '';
let lastKeyTime = 0;

// Calculations
const subtotalMinor = computed(() => {
  return cart.value.reduce((acc, item) => acc + item.quantity * item.unit_price_minor, 0);
});

const discountMinor = computed(() => {
  if (discountPercent.value <= 0) return 0;
  return Math.round((subtotalMinor.value * discountPercent.value) / 100);
});

const discountedSubtotalMinor = computed(() => {
  return Math.max(0, subtotalMinor.value - discountMinor.value);
});

const taxMinor = computed(() => {
  // Apply tax proportionally after basket discount
  const discountFactor = subtotalMinor.value > 0 ? discountedSubtotalMinor.value / subtotalMinor.value : 1;
  return cart.value.reduce((acc, item) => {
    const lineSubtotal = item.quantity * item.unit_price_minor * discountFactor;
    const lineTax = Math.round((lineSubtotal * item.tax_rate_basis_points) / 10000);
    return acc + lineTax;
  }, 0);
});

const totalMinor = computed(() => {
  return discountedSubtotalMinor.value + taxMinor.value;
});

const changeDueMinor = computed(() => {
  if (selectedPayment.value !== 'cash' || amountTendered.value === null) {
    return 0;
  }
  const tenderedMinor = Math.round(amountTendered.value * 100);
  return Math.max(0, tenderedMinor - totalMinor.value);
});

// Smart Pakistani Rupee Cash Tender Suggestions
const smartCashSuggestions = computed(() => {
  const currentTotal = totalMinor.value;
  if (currentTotal <= 0) return [];

  const totalRupees = currentTotal / 100;
  const suggestions = new Set<number>();

  // 1. Exact amount
  suggestions.add(totalRupees);

  // 2. Next nearest Rs. 100
  const next100 = Math.ceil(totalRupees / 100) * 100;
  if (next100 > totalRupees) suggestions.add(next100);

  // 3. Next nearest Rs. 500
  const next500 = Math.ceil(totalRupees / 500) * 500;
  if (next500 > totalRupees) suggestions.add(next500);

  // 4. Next nearest Rs. 1,000
  const next1000 = Math.ceil(totalRupees / 1000) * 1000;
  if (next1000 > totalRupees) suggestions.add(next1000);

  // 5. Always offer Rs. 5,000 if total < 5000
  if (totalRupees < 5000) {
    suggestions.add(5000);
  }

  return Array.from(suggestions).sort((a, b) => a - b).slice(0, 5);
});

const canCompleteSale = computed(() => {
  if (cart.value.length === 0 || isProcessing.value) return false;
  if (selectedPayment.value === 'cash') {
    if (amountTendered.value === null) return false;
    const tenderedMinor = Math.round(amountTendered.value * 100);
    return tenderedMinor >= totalMinor.value;
  }
  return nonCashConfirmed.value;
});

// Load products
async function loadProducts(query?: string) {
  isSearching.value = true;
  try {
    products.value = await getCachedProducts(query);
  } catch (err) {
    console.error('Failed to load products', err);
  } finally {
    isSearching.value = false;
  }
}

function handleSearchInput() {
  const q = searchQuery.value.trim();
  // Check for inline multiplier (e.g. "5*8964000100011" or "3*rice")
  if (q.includes('*')) {
    const parts = q.split('*');
    if (parts.length === 2 && parts[1].trim().length > 0) {
      loadProducts(parts[1].trim());
      return;
    }
  }
  loadProducts(q);
}

// Add to cart with quantity support
function addToCart(product: CachedProduct, quantity: number = 1) {
  const existing = cart.value.find((item) => item.product.id === product.id);
  const rate = product.tax_rate_basis_points || 1800;
  if (existing) {
    existing.quantity += quantity;
    updateItemTotals(existing);
  } else {
    const newItem: CartItem = {
      product,
      quantity,
      unit_price_minor: product.price_minor,
      tax_rate_basis_points: rate,
      tax_minor: Math.round((product.price_minor * rate * quantity) / 10000),
      total_minor: product.price_minor * quantity + Math.round((product.price_minor * rate * quantity) / 10000),
    };
    cart.value.push(newItem);
  }
  searchQuery.value = '';
  focusSearch();
}

function updateItemTotals(item: CartItem) {
  const rate = item.tax_rate_basis_points;
  const lineSubtotal = item.quantity * item.unit_price_minor;
  item.tax_minor = Math.round((lineSubtotal * rate) / 10000);
  item.total_minor = lineSubtotal + item.tax_minor;
}

function incrementQty(item: CartItem) {
  item.quantity += 1;
  updateItemTotals(item);
}

function decrementQty(item: CartItem) {
  if (item.quantity > 1) {
    item.quantity -= 1;
    updateItemTotals(item);
  } else {
    removeFromCart(item);
  }
}

function handleQtyChange(item: CartItem, val: string) {
  const parsed = parseInt(val, 10);
  if (!isNaN(parsed) && parsed > 0) {
    item.quantity = parsed;
    updateItemTotals(item);
  }
}

function removeFromCart(item: CartItem) {
  cart.value = cart.value.filter((i) => i.product.id !== item.product.id);
}

function clearCart() {
  cart.value = [];
  amountTendered.value = null;
  nonCashConfirmed.value = false;
  discountPercent.value = 0;
  customerDetails.value = { name: '', ntn: '', cnic: '' };
  focusSearch();
}

function setQuickTender(amount: number) {
  amountTendered.value = amount;
}

function focusSearch() {
  nextTick(() => {
    searchInputRef.value?.focus();
  });
}

// Park / Hold Current Sale (F8)
function parkCurrentSale() {
  if (cart.value.length === 0) return;

  const held: HeldSale = {
    id: crypto.randomUUID(),
    heldAt: new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }),
    items: [...cart.value],
    customer: { ...customerDetails.value },
    discountPercent: discountPercent.value,
    totalMinor: totalMinor.value,
  };

  heldSales.value.push(held);
  saveHeldSalesToStorage();
  clearCart();
}

// Recall Held Sale (F9)
function recallSale(held: HeldSale) {
  cart.value = [...held.items];
  customerDetails.value = held.customer ? {
    name: held.customer.name || '',
    ntn: held.customer.ntn || '',
    cnic: held.customer.cnic || '',
  } : { name: '', ntn: '', cnic: '' };
  discountPercent.value = held.discountPercent || 0;
  heldSales.value = heldSales.value.filter((s) => s.id !== held.id);
  saveHeldSalesToStorage();
  showHeldSalesModal.value = false;
  focusSearch();
}

function removeHeldSale(heldId: string) {
  heldSales.value = heldSales.value.filter((s) => s.id !== heldId);
  saveHeldSalesToStorage();
}

function loadHeldSalesFromStorage() {
  if (typeof window !== 'undefined' && window.localStorage) {
    try {
      const data = localStorage.getItem('pk_pos_held_sales');
      if (data) heldSales.value = JSON.parse(data);
    } catch (e) {
      console.error('Failed to load held sales', e);
    }
  }
}

function saveHeldSalesToStorage() {
  if (typeof window !== 'undefined' && window.localStorage) {
    try {
      localStorage.setItem('pk_pos_held_sales', JSON.stringify(heldSales.value));
    } catch (e) {
      console.error('Failed to save held sales', e);
    }
  }
}

// Discount Handler (F3)
function openDiscountModal() {
  tempDiscountInput.value = discountPercent.value;
  showDiscountModal.value = true;
}

function applyDiscount() {
  discountPercent.value = Math.min(100, Math.max(0, Number(tempDiscountInput.value) || 0));
  showDiscountModal.value = false;
  focusSearch();
}

// Exact Cash Settlement (F10)
async function handleExactCashCheckout() {
  if (cart.value.length === 0 || isProcessing.value) return;
  selectedPayment.value = 'cash';
  amountTendered.value = totalMinor.value / 100;
  await nextTick();
  await handleCompleteSale();
}

// Global Keydown Handler (Function Keys & Hardware Scanner)
async function handleGlobalKeydown(e: KeyboardEvent) {
  // If sale confirmation modal is open, Enter closes it and begins next sale
  if (showConfirmation.value) {
    if (e.key === 'Enter') {
      e.preventDefault();
      closeConfirmationModal();
    }
    return;
  }

  // Escape key dismisses open dialogs
  if (e.key === 'Escape') {
    if (showHelpModal.value) { showHelpModal.value = false; focusSearch(); return; }
    if (showCustomerModal.value) { showCustomerModal.value = false; focusSearch(); return; }
    if (showDiscountModal.value) { showDiscountModal.value = false; focusSearch(); return; }
    if (showHeldSalesModal.value) { showHeldSalesModal.value = false; focusSearch(); return; }
    searchQuery.value = '';
    focusSearch();
    return;
  }

  // Zero-Mouse Function Key Handlers
  if (e.key === 'F1') {
    e.preventDefault();
    showHelpModal.value = !showHelpModal.value;
    return;
  }
  if (e.key === 'F2') {
    e.preventDefault();
    showCustomerModal.value = !showCustomerModal.value;
    return;
  }
  if (e.key === 'F3') {
    e.preventDefault();
    openDiscountModal();
    return;
  }
  if (e.key === 'F4') {
    e.preventDefault();
    focusSearch();
    return;
  }
  if (e.key === 'F8') {
    e.preventDefault();
    parkCurrentSale();
    return;
  }
  if (e.key === 'F9') {
    e.preventDefault();
    if (heldSales.value.length > 0) {
      showHeldSalesModal.value = !showHeldSalesModal.value;
    }
    return;
  }
  if (e.key === 'F10') {
    e.preventDefault();
    handleExactCashCheckout();
    return;
  }
  if (e.key === 'F12') {
    e.preventDefault();
    const methods: PaymentMethod[] = ['cash', 'card', 'raast', 'wallet'];
    const curIdx = methods.indexOf(selectedPayment.value);
    selectedPayment.value = methods[(curIdx + 1) % methods.length];
    nonCashConfirmed.value = false;
    return;
  }

  // Scanner interval detection (< 65ms)
  const now = Date.now();
  const timeDiff = now - lastKeyTime;
  lastKeyTime = now;

  if (e.key === 'Enter') {
    // If user hit Enter inside search input with an inline multiplier (e.g. 5*8964000100011)
    if (searchQuery.value.trim().length > 0 && searchQuery.value.includes('*')) {
      e.preventDefault();
      const parts = searchQuery.value.split('*');
      const qty = parseInt(parts[0], 10) || 1;
      const targetQuery = parts[1].trim();
      const product = await getProductByBarcode(targetQuery);
      if (product) {
        addToCart(product, qty);
        return;
      }
      const list = await getCachedProducts(targetQuery);
      if (list && list.length > 0) {
        addToCart(list[0], qty);
        return;
      }
    }

    // Hardware Scanner instant stream detection
    if (barcodeBuffer.length >= 3 && timeDiff < 65) {
      e.preventDefault();
      const code = barcodeBuffer;
      barcodeBuffer = '';
      const product = await getProductByBarcode(code);
      if (product) {
        addToCart(product, 1);
      }
      return;
    }
    barcodeBuffer = '';
  } else if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
    if (timeDiff > 70) {
      barcodeBuffer = e.key;
    } else {
      barcodeBuffer += e.key;
    }
  }
}

async function handleCompleteSale() {
  if (!canCompleteSale.value || isProcessing.value) return;

  isProcessing.value = true;
  processError.value = null;

  try {
    const saleId = crypto.randomUUID();
    const dateNum = new Date().toISOString().replace(/\D/g, '').slice(0, 14);
    const saleNumber = `INV-${dateNum.slice(0, 8)}-${dateNum.slice(8, 14)}`;

    const itemsPayload = cart.value.map((item) => ({
      id: crypto.randomUUID(),
      product_id: item.product.id,
      product_name: item.product.name,
      hs_code: item.product.hs_code,
      quantity: item.quantity,
      unit_price_minor: item.unit_price_minor,
      tax_rate_basis_points: item.tax_rate_basis_points,
      tax_minor: item.tax_minor,
      tax_amount_minor: item.tax_minor,
      total_minor: item.total_minor,
      line_total_minor: item.total_minor,
    }));

    const salePayload = {
      id: saleId,
      sale_number: saleNumber,
      payment_method: selectedPayment.value,
      subtotal_minor: subtotalMinor.value,
      discount_minor: discountMinor.value,
      tax_minor: taxMinor.value,
      total_minor: totalMinor.value,
      buyer_name: customerDetails.value.name || undefined,
      buyer_ntn: customerDetails.value.ntn || undefined,
      buyer_cnic: customerDetails.value.cnic || undefined,
      items: itemsPayload,
    };

    const res = await processSale(JSON.stringify(salePayload));

    if (selectedPayment.value === 'cash') {
      try {
        await openCashDrawer();
      } catch (err) {
        // ignore drawer kick failure in dev
      }
    }

    const tenderedMinor =
      selectedPayment.value === 'cash'
        ? Math.round((amountTendered.value || 0) * 100)
        : totalMinor.value;

    completedResult.value = {
      saleNumber,
      result: res,
      totalMinor: totalMinor.value,
      tenderedMinor,
      changeMinor: changeDueMinor.value,
      paymentMethod: selectedPayment.value,
    };

    showConfirmation.value = true;
  } catch (err: any) {
    processError.value = err?.message || String(err);
  } finally {
    isProcessing.value = false;
  }
}

function closeConfirmationModal() {
  showConfirmation.value = false;
  completedResult.value = null;
  clearCart();
}

onMounted(() => {
  loadProducts();
  loadHeldSalesFromStorage();
  window.addEventListener('keydown', handleGlobalKeydown);
  focusSearch();
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<template>
  <div class="checkout-container">
    <!-- Top Shortcuts Command Bar for High-Speed Cashier Ops -->
    <div class="shortcuts-command-bar">
      <div class="shortcut-pills-row">
        <button class="s-pill" @click="showHelpModal = true" title="Shortcuts Sheet">
          <kbd>F1</kbd> Help
        </button>
        <button class="s-pill" :class="{ active: customerDetails.name || customerDetails.ntn }" @click="showCustomerModal = true">
          <kbd>F2</kbd> Customer <span v-if="customerDetails.name" class="pill-dot"></span>
        </button>
        <button class="s-pill" :class="{ active: discountPercent > 0 }" @click="openDiscountModal">
          <kbd>F3</kbd> Discount <span v-if="discountPercent > 0">({{ discountPercent }}%)</span>
        </button>
        <button class="s-pill" @click="focusSearch">
          <kbd>F4</kbd> Search
        </button>
        <button class="s-pill" :disabled="cart.length === 0" @click="parkCurrentSale">
          <kbd>F8</kbd> Park Sale
        </button>
        <button class="s-pill" :disabled="heldSales.length === 0" @click="showHeldSalesModal = true">
          <kbd>F9</kbd> Held Sales <span v-if="heldSales.length > 0" class="held-badge">{{ heldSales.length }}</span>
        </button>
        <button class="s-pill pill-cash" :disabled="cart.length === 0" @click="handleExactCashCheckout">
          <kbd>F10</kbd> Exact Cash
        </button>
        <button class="s-pill" @click="selectedPayment = selectedPayment === 'cash' ? 'card' : 'cash'">
          <kbd>F12</kbd> Tender ({{ selectedPayment }})
        </button>
      </div>

      <div class="scanner-status-indicator">
        <span class="scanner-dot"></span>
        <span>Scanner Ready (e.g. 5*barcode)</span>
      </div>
    </div>

    <!-- 3-COLUMN MAIN WORKSPACE -->
    <div class="checkout-main-grid">
      <!-- COLUMN 1: Product Catalog & Search (~34% width) -->
      <div class="col-catalog">
        <div class="search-box">
          <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input
            ref="searchInputRef"
            type="text"
            v-model="searchQuery"
            class="search-input"
            placeholder="Scan barcode or type 5*barcode, name... (F4)"
            @input="handleSearchInput"
          />
          <button v-if="searchQuery" class="clear-search-btn" @click="searchQuery = ''; handleSearchInput(); focusSearch();">
            &times;
          </button>
        </div>

        <div class="products-grid">
          <div
            v-for="product in products"
            :key="product.id"
            class="product-card"
            @click="addToCart(product)"
          >
            <div class="product-top">
              <span class="product-name">{{ product.name }}</span>
              <span class="tax-badge">{{ (product.tax_rate_basis_points / 100).toFixed(0) }}% Tax</span>
            </div>
            <div class="product-meta">
              <span class="pct-code">PCT: {{ product.hs_code }}</span>
              <span v-if="product.barcode" class="barcode-badge">{{ product.barcode }}</span>
            </div>
            <div class="product-bottom">
              <span class="price-tag">{{ formatPKR(product.price_minor) }}</span>
              <span class="unit-tag">/ {{ product.unit }}</span>
            </div>
          </div>

          <div v-if="products.length === 0 && !isSearching" class="empty-products" style="grid-column: 1 / -1; text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
            <div v-if="searchQuery">
              <p style="font-size: 1rem; font-weight: 600; color: var(--text-main);">No matching products</p>
              <p style="font-size: 0.85rem; margin-top: 0.25rem;">No products found matching "{{ searchQuery }}"</p>
            </div>
            <div v-else>
              <div style="font-size: 2.2rem; margin-bottom: 0.5rem;">📦</div>
              <p style="font-size: 1.05rem; font-weight: 700; color: var(--text-main);">Catalog is Empty</p>
              <p style="font-size: 0.85rem; max-width: 340px; margin: 0.35rem auto 1.25rem; line-height: 1.4;">
                This store has no active products in the Admin Panel yet. Add products via your Merchant Admin panel, then click Sync.
              </p>
              <button class="secondary-btn" @click="loadProducts()" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                🔄 Refresh Catalog
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- COLUMN 2: Full-Height Cart Items List (~40% width) -->
      <div class="col-cart">
        <div class="cart-header">
          <div class="cart-title-row">
            <h2 class="cart-title">Order Basket</h2>
            <span class="items-count-badge">{{ cart.length }} items</span>
            <span v-if="customerDetails.name" class="customer-tag">
              👤 {{ customerDetails.name }}
            </span>
          </div>
          <button v-if="cart.length > 0" class="clear-btn" @click="clearCart">Clear Cart</button>
        </div>

        <!-- Line Items List (100% of viewport height dedicated to cart) -->
        <div class="cart-items-list">
          <div v-if="cart.length === 0" class="empty-cart-state">
            <svg class="cart-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <p class="empty-hint">Cart is empty.</p>
            <span class="empty-sub">Scan barcode or enter 5*barcode to ring items.</span>
          </div>

          <div v-for="item in cart" :key="item.product.id" class="cart-item-row">
            <div class="item-info">
              <div class="item-name">{{ item.product.name }}</div>
              <div class="item-sub">
                <span class="item-unit-price">{{ formatPKR(item.unit_price_minor) }} / {{ item.product.unit }}</span>
                <span class="pct-code-small">PCT: {{ item.product.hs_code }}</span>
              </div>
            </div>

            <div class="item-qty-controls">
              <button class="qty-btn" @click="decrementQty(item)">-</button>
              <input
                type="number"
                class="qty-input"
                :value="item.quantity"
                min="1"
                @change="handleQtyChange(item, ($event.target as HTMLInputElement).value)"
              />
              <button class="qty-btn" @click="incrementQty(item)">+</button>
            </div>

            <div class="item-total">
              <div class="line-price">{{ formatPKR(item.total_minor) }}</div>
              <div class="line-tax">Tax: {{ formatPKR(item.tax_minor) }}</div>
            </div>

            <button class="remove-line-btn" @click="removeFromCart(item)" title="Remove item">
              &times;
            </button>
          </div>
        </div>
      </div>

      <!-- COLUMN 3: Totals, Settlement & Financial Calculations (~26% width) -->
      <div class="col-settlement">
        <div class="settlement-header">
          <h3 class="settlement-title">Summary & Payment</h3>
          <span class="fiscal-tag">FBR Tax Ready</span>
        </div>

        <!-- Financial Totals Card -->
        <div class="cart-totals">
          <div class="totals-row">
            <span class="total-label">Subtotal</span>
            <span class="total-val">{{ formatPKR(subtotalMinor) }}</span>
          </div>
          <div v-if="discountPercent > 0" class="totals-row discount-row">
            <span class="total-label">Discount ({{ discountPercent }}%)</span>
            <span class="total-val">-{{ formatPKR(discountMinor) }}</span>
          </div>
          <div class="totals-row">
            <span class="total-label">Sales Tax (FBR GST)</span>
            <span class="total-val">{{ formatPKR(taxMinor) }}</span>
          </div>
          <div class="totals-row grand-total-row">
            <span class="grand-label">Grand Total</span>
            <span class="grand-val">{{ formatPKR(totalMinor) }}</span>
          </div>
        </div>

        <!-- Payment Method Selector -->
        <div class="payment-method-selector">
          <button
            class="pay-method-btn"
            :class="{ active: selectedPayment === 'cash' }"
            @click="selectedPayment = 'cash'"
          >
            Cash
          </button>
          <button
            class="pay-method-btn"
            :class="{ active: selectedPayment === 'card' }"
            @click="selectedPayment = 'card'; nonCashConfirmed = false;"
          >
            Card
          </button>
          <button
            class="pay-method-btn"
            :class="{ active: selectedPayment === 'raast' }"
            @click="selectedPayment = 'raast'; nonCashConfirmed = false;"
          >
            Raast QR
          </button>
          <button
            class="pay-method-btn"
            :class="{ active: selectedPayment === 'wallet' }"
            @click="selectedPayment = 'wallet'; nonCashConfirmed = false;"
          >
            Wallet
          </button>
        </div>

        <!-- Cash Tender Workflow -->
        <div v-if="selectedPayment === 'cash'" class="cash-workflow">
          <div class="tender-input-group">
            <label class="tender-label">Amount Tendered (Rs.)</label>
            <input
              type="number"
              v-model.number="amountTendered"
              class="tender-input"
              placeholder="0.00"
              min="0"
            />
          </div>

          <!-- Smart Pakistani Rupee Cash Tender Suggestions -->
          <div class="quick-chips">
            <button
              v-for="amt in smartCashSuggestions"
              :key="amt"
              class="chip-btn"
              :class="{ active: amountTendered === amt }"
              @click="setQuickTender(amt)"
            >
              {{ amt === totalMinor / 100 ? 'Exact' : 'Rs ' + amt.toLocaleString('en-PK') }}
            </button>
          </div>

          <!-- High-Contrast Prominent Change Due Display -->
          <div class="change-display" :class="{ ready: changeDueMinor >= 0 && amountTendered !== null }">
            <span class="change-label">Change Due:</span>
            <span class="change-val">{{ formatPKR(changeDueMinor) }}</span>
          </div>
        </div>

        <!-- Non-Cash Workflow -->
        <div v-else class="non-cash-workflow">
          <p class="non-cash-notice">
            Swipe card on POS terminal or verify customer digital payment transfer.
          </p>
          <button
            class="confirm-pay-btn"
            :class="{ confirmed: nonCashConfirmed }"
            @click="nonCashConfirmed = !nonCashConfirmed"
          >
            {{ nonCashConfirmed ? 'Payment Confirmed' : 'Confirm Payment Received' }}
          </button>
        </div>

        <p v-if="processError" class="error-banner">{{ processError }}</p>

        <!-- Settlement Buttons -->
        <div class="settlement-actions">
          <button
            class="complete-sale-btn"
            :disabled="!canCompleteSale"
            @click="handleCompleteSale"
          >
            <span v-if="isProcessing" class="spinner"></span>
            {{ isProcessing ? 'Submitting to FBR & Printing...' : 'Complete Sale & Print (Enter)' }}
          </button>

          <button
            v-if="selectedPayment === 'cash'"
            class="exact-cash-fast-btn"
            :disabled="cart.length === 0 || isProcessing"
            @click="handleExactCashCheckout"
          >
            Exact Cash & Print (F10)
          </button>
        </div>
      </div>
    </div>

    <!-- 1. Shortcuts Help Modal (F1) -->
    <div v-if="showHelpModal" class="modal-overlay" @click.self="showHelpModal = false">
      <div class="modal-card">
        <h3 class="modal-title">POS Keyboard Shortcuts</h3>
        <p class="modal-sub">Zero-mouse fast checkout keyboard controls</p>
        <div class="shortcuts-table">
          <div class="s-row"><kbd>F1</kbd> <span>Shortcuts Reference Sheet</span></div>
          <div class="s-row"><kbd>F2</kbd> <span>Customer NTN / CNIC Entry (FBR B2B)</span></div>
          <div class="s-row"><kbd>F3</kbd> <span>Apply Percentage Basket Discount</span></div>
          <div class="s-row"><kbd>F4</kbd> <span>Focus Product Barcode / Name Search</span></div>
          <div class="s-row"><kbd>F8</kbd> <span>Park / Hold Current Basket</span></div>
          <div class="s-row"><kbd>F9</kbd> <span>Recall Parked / Held Basket</span></div>
          <div class="s-row"><kbd>F10</kbd> <span>Exact Cash Settlement & Print (1-touch)</span></div>
          <div class="s-row"><kbd>F12</kbd> <span>Cycle Payment Tender (Cash/Card/Raast)</span></div>
          <div class="s-row"><kbd>5*code</kbd> <span>Scanner Inline Multiplier (5 units)</span></div>
          <div class="s-row"><kbd>Esc</kbd> <span>Close Modal / Clear Search</span></div>
        </div>
        <button class="next-sale-btn" @click="showHelpModal = false">Close (Esc)</button>
      </div>
    </div>

    <!-- 2. Customer NTN / CNIC Modal (F2) -->
    <div v-if="showCustomerModal" class="modal-overlay" @click.self="showCustomerModal = false">
      <div class="modal-card">
        <h3 class="modal-title">Customer Identification</h3>
        <p class="modal-sub">Required for B2B or invoices $\ge$ Rs 100,000</p>
        <div class="form-group-pos">
          <label>Customer / Business Name</label>
          <input v-model="customerDetails.name" class="pos-input" placeholder="e.g. Al-Madina Traders" />
        </div>
        <div class="form-group-pos">
          <label>National Tax Number (NTN)</label>
          <input v-model="customerDetails.ntn" class="pos-input" placeholder="7-digit NTN (e.g. 1234567)" />
        </div>
        <div class="form-group-pos">
          <label>Buyer CNIC (13-digit)</label>
          <input v-model="customerDetails.cnic" class="pos-input" placeholder="e.g. 4210112345671" />
        </div>
        <button class="next-sale-btn" @click="showCustomerModal = false">Save & Continue (Enter)</button>
      </div>
    </div>

    <!-- 3. Basket Discount Modal (F3) -->
    <div v-if="showDiscountModal" class="modal-overlay" @click.self="showDiscountModal = false">
      <div class="modal-card">
        <h3 class="modal-title">Apply Basket Discount</h3>
        <p class="modal-sub">Percentage discount applied to subtotal</p>
        <div class="form-group-pos">
          <label>Discount Percentage (%)</label>
          <input
            v-model.number="tempDiscountInput"
            type="number"
            min="0"
            max="100"
            class="pos-input"
            placeholder="e.g. 10"
            @keydown.enter="applyDiscount"
          />
        </div>
        <div class="discount-quick-pills">
          <button class="chip-btn" @click="tempDiscountInput = 5">5%</button>
          <button class="chip-btn" @click="tempDiscountInput = 10">10%</button>
          <button class="chip-btn" @click="tempDiscountInput = 15">15%</button>
          <button class="chip-btn" @click="tempDiscountInput = 20">20%</button>
          <button class="chip-btn" @click="tempDiscountInput = 0">Clear</button>
        </div>
        <button class="next-sale-btn" style="margin-top: 1rem;" @click="applyDiscount">Apply Discount (Enter)</button>
      </div>
    </div>

    <!-- 4. Held / Parked Sales Modal (F9) -->
    <div v-if="showHeldSalesModal" class="modal-overlay" @click.self="showHeldSalesModal = false">
      <div class="modal-card" style="max-width: 520px;">
        <h3 class="modal-title">Parked / Held Baskets</h3>
        <p class="modal-sub">Select a held customer basket to resume checkout</p>
        <div class="held-sales-list">
          <div v-for="held in heldSales" :key="held.id" class="held-sale-row">
            <div class="held-info">
              <span class="held-time">Held at {{ held.heldAt }}</span>
              <span class="held-count">{{ held.items.length }} items • {{ formatPKR(held.totalMinor) }}</span>
              <span v-if="held.customer?.name" class="held-cust">{{ held.customer.name }}</span>
            </div>
            <div class="held-actions">
              <button class="restore-btn" @click="recallSale(held)">Recall</button>
              <button class="remove-held-btn" @click="removeHeldSale(held.id)">&times;</button>
            </div>
          </div>
        </div>
        <button class="next-sale-btn" style="margin-top: 1rem;" @click="showHeldSalesModal = false">Dismiss (Esc)</button>
      </div>
    </div>

    <!-- 5. Sale Complete Modal -->
    <div v-if="showConfirmation" class="modal-overlay">
      <div class="modal-card">
        <div class="modal-icon-success">
          <svg class="check-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
          </svg>
        </div>

        <h3 class="modal-title">Sale Complete & Receipt Printed</h3>
        <p class="modal-sale-num">Invoice: {{ completedResult?.saleNumber }}</p>

        <div class="fbr-status-box" :class="completedResult?.result.status === 'Submitted' ? 'submitted' : 'offline'">
          <template v-if="completedResult?.result.status === 'Submitted'">
            <div class="fbr-title">FBR Live Submitted (Active QR)</div>
            <div class="fbr-usin">USIN: {{ completedResult.result.data.usin }}</div>
          </template>
          <template v-else-if="completedResult?.result.status === 'Offline'">
            <div class="fbr-title">PROVISIONAL OFFLINE SALE</div>
            <div class="fbr-usin">Ref: {{ completedResult.result.data.provisional_ref }}</div>
            <p class="fbr-note">Queued in sync outbox for automatic FBR submission.</p>
          </template>
          <template v-else>
            <div class="fbr-title">FBR Rejection Noted</div>
            <p class="fbr-note">Stored locally. Check history for error details.</p>
          </template>
        </div>

        <div class="modal-financial-summary">
          <div class="m-row">
            <span>Total Paid:</span>
            <strong>{{ formatPKR(completedResult?.totalMinor || 0) }}</strong>
          </div>
          <div v-if="completedResult?.paymentMethod === 'cash'" class="m-row change-row">
            <span>Change Returned:</span>
            <strong class="highlight-change">{{ formatPKR(completedResult?.changeMinor || 0) }}</strong>
          </div>
          <div class="m-row">
            <span>Payment Method:</span>
            <span class="cap">{{ completedResult?.paymentMethod }}</span>
          </div>
        </div>

        <button class="next-sale-btn" @click="closeConfirmationModal">
          Next Sale <span class="shortcut">Enter</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.checkout-container {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 64px);
  overflow: hidden;
  background: #0a0f1d;
}

/* Shortcuts Command Bar */
.shortcuts-command-bar {
  height: 38px;
  background: #1e293b;
  border-bottom: 1px solid #334155;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1rem;
  font-size: 0.78rem;
  user-select: none;
  flex-shrink: 0;
}

.shortcut-pills-row {
  display: flex;
  align-items: center;
  gap: 0.45rem;
}

.s-pill {
  background: #0f172a;
  border: 1px solid #334155;
  color: #cbd5e1;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.35rem;
  cursor: pointer;
  transition: all 0.1s ease;
}

.s-pill:hover:not(:disabled) {
  border-color: #3b82f6;
  color: #ffffff;
}

.s-pill:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.s-pill.active {
  background: #1e3a8a;
  border-color: #3b82f6;
  color: #93c5fd;
}

.s-pill.pill-cash {
  background: rgba(16, 185, 129, 0.15);
  border-color: rgba(16, 185, 129, 0.4);
  color: #34d399;
}

.s-pill.pill-cash:hover:not(:disabled) {
  background: #10b981;
  color: #ffffff;
}

.pill-dot {
  width: 5px;
  height: 5px;
  background: #3b82f6;
  border-radius: 50%;
}

.held-badge {
  background: #f59e0b;
  color: #000000;
  font-size: 0.65rem;
  font-weight: 800;
  padding: 0.05rem 0.35rem;
  border-radius: 9999px;
}

.scanner-status-indicator {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  color: #94a3b8;
  font-size: 0.72rem;
}

.scanner-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 6px #10b981;
}

/* 3-COLUMN MAIN WORKSPACE GRID */
.checkout-main-grid {
  display: grid;
  grid-template-columns: 34% 41% 25%;
  flex: 1;
  overflow: hidden;
  height: calc(100vh - 64px - 38px);
}

@media (max-width: 1440px) {
  .checkout-main-grid {
    grid-template-columns: 32% 41% 27%;
  }
}

@media (max-width: 1200px) {
  .checkout-main-grid {
    grid-template-columns: 30% 40% 30%;
  }
}

/* COLUMN 1: CATALOG & SEARCH */
.col-catalog {
  display: flex;
  flex-direction: column;
  border-right: 1px solid #1e293b;
  overflow: hidden;
  background: #0f172a;
}

.search-box {
  padding: 0.75rem 1rem;
  background: #1e293b;
  border-bottom: 1px solid #334155;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  position: relative;
  flex-shrink: 0;
}

.search-icon {
  width: 1.25rem;
  height: 1.25rem;
  color: #94a3b8;
}

.search-input {
  flex: 1;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  padding: 0.65rem 1rem;
  color: #f8fafc;
  font-size: 0.95rem;
  outline: none;
  transition: border-color 0.15s ease;
}

.search-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.clear-search-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  font-size: 1.25rem;
  cursor: pointer;
  padding: 0 0.5rem;
}

.products-grid {
  flex: 1;
  padding: 0.85rem;
  overflow-y: auto;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 0.75rem;
  align-content: start;
}

.product-card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 0.65rem;
  padding: 0.75rem;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 100px;
  transition: all 0.15s ease;
  user-select: none;
}

.product-card:hover {
  background: #253349;
  border-color: #3b82f6;
  transform: translateY(-2px);
  box-shadow: 0 6px 14px rgba(0, 0, 0, 0.35);
}

.product-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.4rem;
}

.product-name {
  font-size: 0.88rem;
  font-weight: 600;
  color: #f1f5f9;
  line-height: 1.25;
}

.tax-badge {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 0.1rem 0.35rem;
  border-radius: 0.25rem;
  white-space: nowrap;
}

.product-meta {
  display: flex;
  gap: 0.4rem;
  margin: 0.25rem 0;
  font-size: 0.72rem;
  color: #94a3b8;
}

.pct-code {
  font-family: monospace;
}

.barcode-badge {
  background: #0f172a;
  padding: 0.05rem 0.3rem;
  border-radius: 0.25rem;
  font-size: 0.68rem;
}

.product-bottom {
  display: flex;
  align-items: baseline;
  gap: 0.25rem;
  margin-top: 0.35rem;
}

.price-tag {
  font-size: 1.05rem;
  font-weight: 800;
  color: #38bdf8;
}

.unit-tag {
  font-size: 0.72rem;
  color: #94a3b8;
}

.empty-products {
  grid-column: 1 / -1;
  text-align: center;
  color: #64748b;
  padding: 3rem 1rem;
}

/* COLUMN 2: FULL-HEIGHT CART ITEMS LIST */
.col-cart {
  display: flex;
  flex-direction: column;
  border-right: 1px solid #1e293b;
  overflow: hidden;
  background: #111827;
}

.cart-header {
  padding: 0.75rem 1.25rem;
  background: #1e293b;
  border-bottom: 1px solid #334155;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-shrink: 0;
}

.cart-title-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.cart-title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: #f8fafc;
}

.items-count-badge {
  background: #334155;
  color: #94a3b8;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.15rem 0.5rem;
  border-radius: 9999px;
}

.customer-tag {
  font-size: 0.75rem;
  background: rgba(59, 130, 246, 0.2);
  color: #60a5fa;
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
}

.clear-btn {
  background: transparent;
  border: 1px solid #475569;
  color: #cbd5e1;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.3rem 0.65rem;
  border-radius: 0.375rem;
  cursor: pointer;
}

.clear-btn:hover {
  background: #ef4444;
  border-color: #ef4444;
  color: #ffffff;
}

.cart-items-list {
  flex: 1;
  overflow-y: auto;
  padding: 0.85rem;
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
}

.empty-cart-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #64748b;
  text-align: center;
  padding: 2rem;
}

.cart-empty-icon {
  width: 3.5rem;
  height: 3.5rem;
  margin-bottom: 1rem;
  opacity: 0.35;
}

.empty-hint {
  font-size: 1.05rem;
  font-weight: 600;
  color: #94a3b8;
  margin: 0 0 0.35rem;
}

.empty-sub {
  font-size: 0.8rem;
  color: #64748b;
}

.cart-item-row {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  padding: 0.75rem 1rem;
  display: flex;
  align-items: center;
  gap: 0.85rem;
  transition: border-color 0.15s ease;
}

.cart-item-row:hover {
  border-color: #475569;
}

.item-info {
  flex: 1;
  min-width: 0;
}

.item-name {
  font-size: 0.95rem;
  font-weight: 600;
  color: #f1f5f9;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.item-sub {
  font-size: 0.75rem;
  color: #94a3b8;
  display: flex;
  align-items: center;
  gap: 0.65rem;
  margin-top: 0.15rem;
}

.item-unit-price {
  color: #cbd5e1;
}

.pct-code-small {
  font-family: monospace;
  background: #0f172a;
  padding: 0.05rem 0.35rem;
  border-radius: 3px;
  font-size: 0.7rem;
}

.item-qty-controls {
  display: flex;
  align-items: center;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 0.375rem;
}

.qty-btn {
  background: transparent;
  border: none;
  color: #cbd5e1;
  width: 28px;
  height: 30px;
  cursor: pointer;
  font-weight: 700;
  font-size: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.qty-btn:hover {
  background: #334155;
}

.qty-input {
  width: 40px;
  height: 30px;
  background: transparent;
  border: none;
  color: #ffffff;
  text-align: center;
  font-weight: 700;
  font-size: 0.9rem;
  outline: none;
  -moz-appearance: textfield;
}

.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.item-total {
  text-align: right;
  min-width: 90px;
}

.line-price {
  font-weight: 800;
  font-size: 1.05rem;
  color: #f8fafc;
}

.line-tax {
  font-size: 0.72rem;
  color: #94a3b8;
}

.remove-line-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  font-size: 1.35rem;
  cursor: pointer;
  padding: 0 0.25rem;
  line-height: 1;
}

.remove-line-btn:hover {
  color: #ef4444;
}

/* COLUMN 3: SETTLEMENT, TOTALS & PAYMENT */
.col-settlement {
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  background: #0a0f1d;
  padding: 0.85rem 1.15rem;
  gap: 0.75rem;
}

.settlement-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 0.35rem;
  border-bottom: 1px solid #1e293b;
}

.settlement-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #f8fafc;
}

.fiscal-tag {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.15rem 0.5rem;
  border-radius: 9999px;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

/* Financial Totals Card */
.cart-totals {
  background: #131b2e;
  border: 1px solid #1e293b;
  border-radius: 0.65rem;
  padding: 0.85rem 1rem;
}

.totals-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.88rem;
  color: #94a3b8;
  margin-bottom: 0.35rem;
}

.discount-row {
  color: #fbbf24;
}

.grand-total-row {
  margin-top: 0.45rem;
  padding-top: 0.45rem;
  border-top: 1px dashed #334155;
  color: #ffffff;
}

.grand-label {
  font-size: 1.15rem;
  font-weight: 700;
}

.grand-val {
  font-size: 1.6rem;
  font-weight: 900;
  color: #10b981;
  letter-spacing: -0.02em;
}

/* Payment Method Selector */
.payment-method-selector {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.4rem;
}

.pay-method-btn {
  background: #1e293b;
  border: 1px solid #334155;
  color: #cbd5e1;
  padding: 0.45rem 0;
  border-radius: 0.45rem;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.pay-method-btn.active {
  background: #2563eb;
  border-color: #3b82f6;
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.35);
}

/* Cash Tender Workflow */
.cash-workflow {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.tender-input-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.tender-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #94a3b8;
}

.tender-input {
  background: #131b2e;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  color: #ffffff;
  font-size: 1.25rem;
  font-weight: 800;
  outline: none;
}

.tender-input:focus {
  border-color: #3b82f6;
}

.quick-chips {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
}

.chip-btn {
  flex: 1;
  min-width: 65px;
  background: #1e293b;
  border: 1px solid #334155;
  color: #93c5fd;
  padding: 0.35rem 0;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  text-align: center;
}

.chip-btn:hover {
  background: #253349;
  border-color: #3b82f6;
}

.chip-btn.active {
  background: #2563eb;
  color: #ffffff;
}

.change-display {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #131b2e;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  padding: 0.55rem 0.85rem;
}

.change-display.ready {
  border-color: #059669;
  background: rgba(16, 185, 129, 0.12);
}

.change-label {
  font-size: 0.85rem;
  color: #94a3b8;
  font-weight: 600;
}

.change-val {
  font-size: 1.4rem;
  font-weight: 900;
  color: #34d399;
  letter-spacing: -0.02em;
}

.non-cash-workflow {
  background: #131b2e;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  padding: 0.85rem;
  text-align: center;
}

.non-cash-notice {
  font-size: 0.75rem;
  color: #94a3b8;
  margin: 0 0 0.65rem;
}

.confirm-pay-btn {
  background: #1e293b;
  border: 1px solid #3b82f6;
  color: #60a5fa;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  width: 100%;
}

.confirm-pay-btn.confirmed {
  background: #059669;
  border-color: #10b981;
  color: #ffffff;
}

/* Settlement Buttons */
.settlement-actions {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: auto;
}

.complete-sale-btn {
  background: #10b981;
  color: #ffffff;
  border: none;
  padding: 0.85rem;
  border-radius: 0.625rem;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
  transition: all 0.15s ease;
}

.complete-sale-btn:hover:not(:disabled) {
  background: #059669;
  transform: translateY(-1px);
}

.complete-sale-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
}

.exact-cash-fast-btn {
  background: transparent;
  border: 1px solid #10b981;
  color: #34d399;
  padding: 0.55rem;
  border-radius: 0.5rem;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.exact-cash-fast-btn:hover:not(:disabled) {
  background: rgba(16, 185, 129, 0.15);
}

.exact-cash-fast-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.error-banner {
  background: rgba(239, 68, 68, 0.15);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.3);
  padding: 0.4rem 0.6rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  margin: 0;
}

/* Modal Overlay & Card */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

.modal-card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 1rem;
  padding: 1.75rem;
  width: 100%;
  max-width: 440px;
  text-align: center;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
}

.modal-title {
  margin: 0 0 0.35rem;
  font-size: 1.25rem;
  font-weight: 700;
  color: #f8fafc;
}

.modal-sub {
  margin: 0 0 1.25rem;
  font-size: 0.8rem;
  color: #94a3b8;
}

.modal-sale-num {
  margin: 0 0 1.25rem;
  font-size: 0.85rem;
  color: #94a3b8;
  font-family: monospace;
}

.shortcuts-table {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  text-align: left;
  margin-bottom: 1.5rem;
}

.s-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.85rem;
  color: #cbd5e1;
}

kbd {
  background: #0f172a;
  border: 1px solid #475569;
  border-radius: 4px;
  padding: 0.15rem 0.45rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: #38bdf8;
  min-width: 48px;
  text-align: center;
}

.form-group-pos {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  text-align: left;
  margin-bottom: 0.85rem;
}

.form-group-pos label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #94a3b8;
}

.pos-input {
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  padding: 0.6rem 0.75rem;
  color: #ffffff;
  font-size: 0.95rem;
  outline: none;
}

.pos-input:focus {
  border-color: #3b82f6;
}

.discount-quick-pills {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.held-sales-list {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  max-height: 250px;
  overflow-y: auto;
  text-align: left;
}

.held-sale-row {
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  padding: 0.65rem 0.85rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.held-info {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.held-time {
  font-size: 0.75rem;
  color: #94a3b8;
}

.held-count {
  font-size: 0.85rem;
  font-weight: 700;
  color: #f1f5f9;
}

.held-cust {
  font-size: 0.72rem;
  color: #38bdf8;
}

.held-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.restore-btn {
  background: #2563eb;
  border: none;
  color: #ffffff;
  padding: 0.35rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.remove-held-btn {
  background: transparent;
  border: none;
  color: #ef4444;
  font-size: 1.25rem;
  cursor: pointer;
}

.modal-icon-success {
  width: 56px;
  height: 56px;
  background: rgba(16, 185, 129, 0.15);
  border: 2px solid #10b981;
  color: #10b981;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
}

.check-svg {
  width: 28px;
  height: 28px;
}

.fbr-status-box {
  border-radius: 0.5rem;
  padding: 0.75rem;
  margin-bottom: 1.25rem;
}

.fbr-status-box.submitted {
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.3);
  color: #34d399;
}

.fbr-status-box.offline {
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.3);
  color: #fbbf24;
}

.fbr-title {
  font-size: 0.85rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.fbr-usin {
  font-size: 0.75rem;
  font-family: monospace;
}

.fbr-note {
  font-size: 0.7rem;
  margin: 0.25rem 0 0;
  opacity: 0.85;
}

.modal-financial-summary {
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  padding: 0.85rem;
  margin-bottom: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  text-align: left;
}

.m-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  color: #94a3b8;
}

.change-row {
  padding-top: 0.4rem;
  border-top: 1px dashed #334155;
}

.highlight-change {
  color: #10b981;
  font-size: 1.1rem;
}

.cap {
  text-transform: capitalize;
  font-weight: 600;
  color: #ffffff;
}

.next-sale-btn {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.next-sale-btn:hover {
  background: #1d4ed8;
}

.shortcut {
  font-size: 0.7rem;
  background: rgba(0, 0, 0, 0.3);
  padding: 0.1rem 0.35rem;
  border-radius: 0.25rem;
}

.spinner {
  width: 1rem;
  height: 1rem;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
