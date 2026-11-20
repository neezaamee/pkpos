<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import type { CachedProduct, CartItem, CustomerProfile, PaymentMethod, ProcessResult, ProductUomConversion, ProductVariantItem, SplitPaymentItem } from '../types/pos';
import { formatPKR } from '../types/pos';
import { getCachedProducts, getProductByBarcode, processSale, openCashDrawer, searchCustomers, createCustomer, verifyInvoiceForReturn, processSaleReturn, fetchReceiptTemplate, type VerifyInvoiceResponse, type ReceiptTemplateData } from '../services/posService';

interface HeldSale {
  id: string;
  heldAt: string;
  items: CartItem[];
  customer?: { name?: string; ntn?: string; cnic?: string };
  selectedCustomer?: CustomerProfile | null;
  discountPercent?: number;
  totalMinor: number;
}

const searchQuery = ref('');
const products = ref<CachedProduct[]>([]);
const isSearching = ref(false);
const cart = ref<CartItem[]>([]);
const searchInputRef = ref<HTMLInputElement | null>(null);
const mobileSection = ref<'products' | 'cart' | 'pay'>('products');

const cartUnitsCount = computed(() => {
  const sum = cart.value.reduce((acc, item) => acc + item.quantity, 0);
  return Math.round(sum * 1000) / 1000;
});

function isTouchOrCompactViewport(): boolean {
  if (typeof window === 'undefined') return false;
  return window.innerWidth <= 960 || window.matchMedia('(pointer: coarse)').matches;
}

// Product Variant Selection State
const showVariantModal = ref(false);
const selectedProductForVariants = ref<CachedProduct | null>(null);

// Customer Identification & Khata (Udhaar) State
const customerDetails = ref<{ name: string; ntn: string; cnic: string }>({
  name: '',
  ntn: '',
  cnic: '',
});
const selectedCustomer = ref<CustomerProfile | null>(null);
const customerSearchResults = ref<CustomerProfile[]>([]);
const customerLookupQuery = ref('');
const isSearchingCustomer = ref(false);
const customerModalTab = ref<'search' | 'create'>('search');
const newCustomerForm = ref({
  name: '',
  phone: '',
  cnic: '',
  ntn: '',
  credit_limit_pkr: 0,
});
const customerErrorMessage = ref<string | null>(null);
const showCustomerModal = ref(false);

// Discount State
const discountPercent = ref<number>(0);
const showDiscountModal = ref(false);
const tempDiscountInput = ref<number>(0);

// Held / Parked Sales State
const heldSales = ref<HeldSale[]>([]);
const showHeldSalesModal = ref(false);

// Sales Returns & FBR Credit Notes State
const showReturnModal = ref(false);
const returnInvoiceQuery = ref('');
const isVerifyingReturnInvoice = ref(false);
const returnVerificationResult = ref<VerifyInvoiceResponse | null>(null);
const returnItemsState = ref<Array<{
  sale_item_id: string;
  product_name: string;
  unit_code: string;
  sold_quantity: number;
  returnable_quantity: number;
  quantity_returned: number;
  unit_price_minor: number;
  restock_condition: 'restock_shelf' | 'damaged_quarantine' | 'discard';
}>>([]);
const returnReason = ref('customer_change_of_mind');
const returnRefundMethod = ref('cash');
const returnNotes = ref('');
const isProcessingReturn = ref(false);
const returnErrorMessage = ref('');
const returnSuccessResult = ref<any>(null);

function openReturnModal() {
  showReturnModal.value = true;
  returnInvoiceQuery.value = '';
  returnVerificationResult.value = null;
  returnItemsState.value = [];
  returnErrorMessage.value = '';
  returnSuccessResult.value = null;
}

async function handleLookupReturnInvoice() {
  if (!returnInvoiceQuery.value.trim()) return;
  isVerifyingReturnInvoice.value = true;
  returnErrorMessage.value = '';
  returnVerificationResult.value = null;
  try {
    const res = await verifyInvoiceForReturn(returnInvoiceQuery.value.trim());
    returnVerificationResult.value = res;
    returnItemsState.value = res.returnable_items.map(item => ({
      sale_item_id: item.sale_item_id,
      product_name: item.product_name,
      unit_code: item.unit_code,
      sold_quantity: item.sold_quantity,
      returnable_quantity: item.returnable_quantity,
      quantity_returned: 0,
      unit_price_minor: item.unit_price_minor,
      restock_condition: 'restock_shelf',
    }));
    returnRefundMethod.value = res.sale.customer_id ? 'credit_note_khata' : 'cash';
  } catch (err: any) {
    returnErrorMessage.value = err.message || 'Invoice lookup failed.';
  } finally {
    isVerifyingReturnInvoice.value = false;
  }
}

const totalReturnRefundMinor = computed(() => {
  return returnItemsState.value.reduce((sum, item) => {
    const qty = Number(item.quantity_returned) || 0;
    return sum + Math.round(qty * item.unit_price_minor);
  }, 0);
});

async function handleProcessReturn() {
  if (!returnVerificationResult.value) return;
  const itemsToReturn = returnItemsState.value
    .filter(i => (Number(i.quantity_returned) || 0) > 0)
    .map(i => ({
      sale_item_id: i.sale_item_id,
      quantity_returned: Number(i.quantity_returned),
      restock_condition: i.restock_condition,
    }));

  if (itemsToReturn.length === 0) {
    returnErrorMessage.value = 'Please enter a return quantity for at least one item.';
    return;
  }

  isProcessingReturn.value = true;
  returnErrorMessage.value = '';
  try {
    const res = await processSaleReturn({
      sale_id: returnVerificationResult.value.sale.id,
      reason: returnReason.value,
      refund_method: returnRefundMethod.value,
      notes: returnNotes.value,
      items: itemsToReturn,
    });
    returnSuccessResult.value = res.sale_return;
  } catch (err: any) {
    returnErrorMessage.value = err.message || 'Failed to process return.';
  } finally {
    isProcessingReturn.value = false;
  }
}

// Shortcuts Help Modal State
const showHelpModal = ref(false);

// Payment State
const selectedPayment = ref<PaymentMethod>('cash');
const amountTendered = ref<number | null>(null);
const nonCashConfirmed = ref<boolean>(false);
const isProcessing = ref<boolean>(false);
const processError = ref<string | null>(null);

// Receipt Template State
const receiptTemplateData = ref<ReceiptTemplateData | null>(null);

// Split / Multi-Tender Payment State
const showSplitModal = ref<boolean>(false);
const splitTenders = ref<{ method: 'cash' | 'card' | 'raast' | 'credit'; amount: number | null; ref: string }[]>([
  { method: 'cash', amount: null, ref: '' },
  { method: 'card', amount: null, ref: '' },
  { method: 'raast', amount: null, ref: '' },
  { method: 'credit', amount: null, ref: '' },
]);
const splitPaymentsApplied = ref<SplitPaymentItem[]>([]);
const splitCreditError = ref<string>('');

// Sale Confirmation Modal State
const showConfirmation = ref<boolean>(false);
const completedResult = ref<{
  saleNumber: string;
  result: ProcessResult;
  totalMinor: number;
  subtotalMinor?: number;
  taxMinor?: number;
  tenderedMinor: number;
  changeMinor: number;
  paymentMethod: string;
  payments?: SplitPaymentItem[];
  items?: CartItem[];
  customer?: CustomerProfile | null;
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

// Khata & Credit Computeds
const customerAvailableCreditMinor = computed(() => {
  if (!selectedCustomer.value || selectedCustomer.value.credit_limit_minor <= 0) return 0;
  return Math.max(0, selectedCustomer.value.credit_limit_minor - (selectedCustomer.value.current_balance_minor || 0));
});

const creditExceeded = computed(() => {
  if (selectedPayment.value !== 'credit') return false;
  if (!selectedCustomer.value) return true;
  return totalMinor.value > customerAvailableCreditMinor.value;
});

// Split Payment Computeds
const splitTotalAllocatedMinor = computed(() => {
  return splitTenders.value.reduce((acc, t) => acc + Math.round((t.amount || 0) * 100), 0);
});

const splitRemainingMinor = computed(() => {
  return totalMinor.value - splitTotalAllocatedMinor.value;
});

const isSplitValid = computed(() => {
  return totalMinor.value > 0 && splitTotalAllocatedMinor.value === totalMinor.value && !splitCreditError.value;
});

function openSplitPaymentModal() {
  if (cart.value.length === 0) return;
  splitCreditError.value = '';
  if (splitPaymentsApplied.value.length > 0) {
    splitTenders.value.forEach(t => {
      const match = splitPaymentsApplied.value.find(p => p.payment_method === t.method);
      t.amount = match ? match.amount_minor / 100 : null;
      t.ref = match?.reference || '';
    });
  } else {
    const cashAmt = amountTendered.value && amountTendered.value < totalMinor.value / 100
      ? amountTendered.value
      : null;
    splitTenders.value[0].amount = cashAmt;
    splitTenders.value[1].amount = null;
    splitTenders.value[2].amount = null;
    splitTenders.value[3].amount = null;
  }
  showSplitModal.value = true;
}

function allocateRemainingTo(index: number) {
  const currentItemAmount = Math.round((splitTenders.value[index].amount || 0) * 100);
  const otherTendersSum = splitTotalAllocatedMinor.value - currentItemAmount;
  const rem = Math.max(0, totalMinor.value - otherTendersSum);
  splitTenders.value[index].amount = Math.round(rem) / 100;
  validateSplitCredit();
}

function validateSplitCredit() {
  splitCreditError.value = '';
  const creditTender = splitTenders.value.find(t => t.method === 'credit');
  if (creditTender && creditTender.amount && creditTender.amount > 0) {
    if (!selectedCustomer.value) {
      splitCreditError.value = 'Attach a customer account first to use Khata credit.';
      return;
    }
    const creditMinor = Math.round(creditTender.amount * 100);
    if (creditMinor > customerAvailableCreditMinor.value) {
      splitCreditError.value = `Credit allocation (${formatPKR(creditMinor)}) exceeds customer limit (${formatPKR(customerAvailableCreditMinor.value)}).`;
    }
  }
}

function applySplitPayment() {
  validateSplitCredit();
  if (!isSplitValid.value) return;

  const active: SplitPaymentItem[] = [];
  splitTenders.value.forEach(t => {
    if (t.amount && t.amount > 0) {
      active.push({
        payment_method: t.method,
        amount_minor: Math.round(t.amount * 100),
        tendered_minor: Math.round(t.amount * 100),
        change_minor: 0,
        reference: t.ref.trim() || undefined,
      });
    }
  });

  splitPaymentsApplied.value = active;
  selectedPayment.value = 'split';
  showSplitModal.value = false;
}

function clearSplitPayment() {
  splitPaymentsApplied.value = [];
  splitTenders.value.forEach(t => { t.amount = null; t.ref = ''; });
  selectedPayment.value = 'cash';
  showSplitModal.value = false;
}

function printThermalReceipt() {
  window.print();
}

const canCompleteSale = computed(() => {
  if (cart.value.length === 0 || isProcessing.value) return false;
  if (selectedPayment.value === 'cash') {
    if (amountTendered.value === null) return false;
    const tenderedMinor = Math.round(amountTendered.value * 100);
    return tenderedMinor >= totalMinor.value;
  }
  if (selectedPayment.value === 'credit') {
    return selectedCustomer.value !== null && !creditExceeded.value;
  }
  if (selectedPayment.value === 'split') {
    return splitPaymentsApplied.value.length > 0 &&
      splitPaymentsApplied.value.reduce((acc, p) => acc + p.amount_minor, 0) === totalMinor.value;
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

// Product selection dispatcher: checks for variants first
function onProductClick(product: CachedProduct) {
  if (product.variants && product.variants.length > 0) {
    selectedProductForVariants.value = product;
    showVariantModal.value = true;
  } else {
    addToCart(product, 1);
  }
}

function addVariantAndClose(product: CachedProduct, variant: ProductVariantItem) {
  addToCart(product, 1, variant);
  showVariantModal.value = false;
  selectedProductForVariants.value = null;
}

// Add to cart with variant, UOM pack conversion, and fractional quantity support
function addToCart(
  product: CachedProduct,
  quantity: number = 1,
  variant?: ProductVariantItem,
  packUom?: ProductUomConversion
) {
  const existing = cart.value.find((item) => {
    const sameProd = item.product.id === product.id;
    const sameVar = variant ? item.variant?.id === variant.id : !item.variant;
    const sameUom = packUom ? item.selected_uom?.id === packUom.id : !item.selected_uom;
    return sameProd && sameVar && sameUom;
  });

  const rate = product.tax_rate_basis_points || 1800;
  let unitPrice = variant?.price_override_minor ?? product.price_minor;
  let unitCode = product.unit || 'pcs';
  let conversionFactor = 1.0;

  if (packUom) {
    unitPrice = packUom.price_minor;
    unitCode = packUom.unit_code;
    conversionFactor = packUom.conversion_factor;
  }

  if (existing) {
    existing.quantity = Math.round((existing.quantity + quantity) * 1000) / 1000;
    updateItemTotals(existing);
  } else {
    const lineSubtotal = quantity * unitPrice;
    const lineTax = Math.round((lineSubtotal * rate) / 10000);
    const newItem: CartItem = {
      product,
      variant,
      quantity,
      unit_price_minor: unitPrice,
      tax_rate_basis_points: rate,
      tax_minor: lineTax,
      total_minor: Math.round(lineSubtotal + lineTax),
      selected_uom: packUom,
      unit_code: unitCode,
      unit_conversion_factor: conversionFactor,
    };
    cart.value.push(newItem);
  }
  searchQuery.value = '';
  focusSearch();
}

function switchItemUom(item: CartItem, packUom?: ProductUomConversion) {
  if (packUom) {
    item.selected_uom = packUom;
    item.unit_code = packUom.unit_code;
    item.unit_conversion_factor = packUom.conversion_factor;
    item.unit_price_minor = packUom.price_minor;
  } else {
    item.selected_uom = undefined;
    item.unit_code = item.product.unit || 'pcs';
    item.unit_conversion_factor = 1.0;
    item.unit_price_minor = item.variant?.price_override_minor ?? item.product.price_minor;
  }
  updateItemTotals(item);
}

function updateItemTotals(item: CartItem) {
  const rate = item.tax_rate_basis_points;
  const lineSubtotal = item.quantity * item.unit_price_minor;
  item.tax_minor = Math.round((lineSubtotal * rate) / 10000);
  item.total_minor = Math.round(lineSubtotal + item.tax_minor);
}

function incrementQty(item: CartItem) {
  item.quantity = Math.round((item.quantity + 1) * 1000) / 1000;
  updateItemTotals(item);
}

function decrementQty(item: CartItem) {
  if (item.quantity > 1) {
    item.quantity = Math.round((item.quantity - 1) * 1000) / 1000;
    updateItemTotals(item);
  } else {
    removeFromCart(item);
  }
}

function handleQtyChange(item: CartItem, val: string) {
  const parsed = parseFloat(val);
  if (!isNaN(parsed) && parsed > 0) {
    item.quantity = Math.round(parsed * 1000) / 1000;
    updateItemTotals(item);
  }
}

function removeFromCart(item: CartItem) {
  cart.value = cart.value.filter((i) => {
    if (item.variant) {
      return !(i.product.id === item.product.id && i.variant?.id === item.variant.id);
    }
    if (item.selected_uom) {
      return !(i.product.id === item.product.id && i.selected_uom?.id === item.selected_uom.id);
    }
    return !(i.product.id === item.product.id && !i.variant && !i.selected_uom);
  });
}

function clearCart() {
  cart.value = [];
  amountTendered.value = null;
  nonCashConfirmed.value = false;
  discountPercent.value = 0;
  selectedCustomer.value = null;
  customerDetails.value = { name: '', ntn: '', cnic: '' };
  if (selectedPayment.value === 'credit') {
    selectedPayment.value = 'cash';
  }
  focusSearch();
}

// Customer CRM & Khata Action Handlers
async function onCustomerSearchInput() {
  const q = customerLookupQuery.value.trim();
  isSearchingCustomer.value = true;
  customerErrorMessage.value = null;
  try {
    customerSearchResults.value = await searchCustomers(q);
  } catch (err: any) {
    customerErrorMessage.value = err?.message || 'Failed to search customers';
  } finally {
    isSearchingCustomer.value = false;
  }
}

function selectCustomer(cust: CustomerProfile) {
  selectedCustomer.value = cust;
  customerDetails.value = {
    name: cust.name,
    ntn: cust.ntn || '',
    cnic: cust.cnic || '',
  };
  showCustomerModal.value = false;
  customerLookupQuery.value = '';
  customerSearchResults.value = [];
}

function detachCustomer() {
  selectedCustomer.value = null;
  customerDetails.value = { name: '', ntn: '', cnic: '' };
  if (selectedPayment.value === 'credit') {
    selectedPayment.value = 'cash';
  }
}

async function handleRegisterCustomer() {
  if (!newCustomerForm.value.name || !newCustomerForm.value.phone) {
    customerErrorMessage.value = 'Customer name and phone are required.';
    return;
  }
  isSearchingCustomer.value = true;
  customerErrorMessage.value = null;
  try {
    const created = await createCustomer({
      name: newCustomerForm.value.name,
      phone: newCustomerForm.value.phone,
      cnic: newCustomerForm.value.cnic || undefined,
      ntn: newCustomerForm.value.ntn || undefined,
      credit_limit_minor: Math.round((newCustomerForm.value.credit_limit_pkr || 0) * 100),
    });
    selectCustomer(created);
    customerModalTab.value = 'search';
    newCustomerForm.value = { name: '', phone: '', cnic: '', ntn: '', credit_limit_pkr: 0 };
  } catch (err: any) {
    customerErrorMessage.value = err?.message || 'Failed to register customer';
  } finally {
    isSearchingCustomer.value = false;
  }
}

function selectCreditPayment() {
  if (!selectedCustomer.value) {
    showCustomerModal.value = true;
    return;
  }
  selectedPayment.value = 'credit';
  nonCashConfirmed.value = false;
}

function setQuickTender(amount: number) {
  amountTendered.value = amount;
}

function focusSearch(desktopOnly = true) {
  if (desktopOnly && isTouchOrCompactViewport()) {
    return;
  }
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
    selectedCustomer: selectedCustomer.value ? { ...selectedCustomer.value } : null,
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
  selectedCustomer.value = held.selectedCustomer || null;
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
    if (showVariantModal.value) { showVariantModal.value = false; selectedProductForVariants.value = null; focusSearch(); return; }
    if (showHelpModal.value) { showHelpModal.value = false; focusSearch(); return; }
    if (showCustomerModal.value) { showCustomerModal.value = false; focusSearch(); return; }
    if (showDiscountModal.value) { showDiscountModal.value = false; focusSearch(); return; }
    if (showHeldSalesModal.value) { showHeldSalesModal.value = false; focusSearch(); return; }
    if (showSplitModal.value) { showSplitModal.value = false; focusSearch(); return; }
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
  if (e.key === 'F6') {
    e.preventDefault();
    openReturnModal();
    return;
  }
  if (e.key === 'F7') {
    e.preventDefault();
    openSplitPaymentModal();
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
    const methods: PaymentMethod[] = selectedCustomer.value
      ? ['cash', 'card', 'raast', 'wallet', 'credit']
      : ['cash', 'card', 'raast', 'wallet'];
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
      const lookup = await getProductByBarcode(targetQuery);
      if (lookup) {
        addToCart(lookup.product, qty, undefined, lookup.packUom);
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
      const lookup = await getProductByBarcode(code);
      if (lookup) {
        addToCart(lookup.product, 1, undefined, lookup.packUom);
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
      product_name: item.variant
        ? `${item.product.name} (${item.variant.name})`
        : (item.selected_uom ? `${item.product.name} [${item.selected_uom.unit_name}]` : item.product.name),
      hs_code: item.product.hs_code,
      unit_code: item.unit_code || item.product.unit || 'pcs',
      unit_conversion_factor: item.unit_conversion_factor ?? 1.0,
      quantity: item.quantity,
      unit_price_minor: item.unit_price_minor,
      tax_rate_basis_points: item.tax_rate_basis_points,
      tax_minor: item.tax_minor,
      tax_amount_minor: item.tax_minor,
      total_minor: item.total_minor,
      line_total_minor: item.total_minor,
      discount_type: 'none',
      discount_amount_minor: 0,
    }));

    const paymentsPayload = selectedPayment.value === 'split' && splitPaymentsApplied.value.length > 0
      ? splitPaymentsApplied.value
      : undefined;

    const salePayload = {
      id: saleId,
      sale_number: saleNumber,
      payment_method: selectedPayment.value,
      payments: paymentsPayload,
      subtotal_minor: subtotalMinor.value,
      discount_minor: discountMinor.value,
      tax_minor: taxMinor.value,
      total_minor: totalMinor.value,
      customer_id: selectedCustomer.value?.id || undefined,
      buyer_name: selectedCustomer.value?.name || customerDetails.value.name || undefined,
      buyer_phone: selectedCustomer.value?.phone || undefined,
      buyer_ntn: selectedCustomer.value?.ntn || customerDetails.value.ntn || undefined,
      buyer_cnic: selectedCustomer.value?.cnic || customerDetails.value.cnic || undefined,
      items: itemsPayload,
    };

    const res = await processSale(JSON.stringify(salePayload));

    if (selectedCustomer.value) {
      if (selectedPayment.value === 'credit') {
        selectedCustomer.value.current_balance_minor =
          (selectedCustomer.value.current_balance_minor || 0) + totalMinor.value;
      } else if (selectedPayment.value === 'split' && paymentsPayload) {
        const creditTender = paymentsPayload.find(p => p.payment_method === 'credit');
        if (creditTender) {
          selectedCustomer.value.current_balance_minor =
            (selectedCustomer.value.current_balance_minor || 0) + creditTender.amount_minor;
        }
      }
    }

    if (selectedPayment.value === 'cash' || (selectedPayment.value === 'split' && paymentsPayload?.some(p => p.payment_method === 'cash'))) {
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
      subtotalMinor: subtotalMinor.value,
      taxMinor: taxMinor.value,
      tenderedMinor,
      changeMinor: changeDueMinor.value,
      paymentMethod: selectedPayment.value === 'split' ? 'Split Payment' : selectedPayment.value,
      payments: paymentsPayload,
      items: [...cart.value],
      customer: selectedCustomer.value || null,
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
  splitPaymentsApplied.value = [];
  splitTenders.value.forEach(t => { t.amount = null; t.ref = ''; });
  selectedPayment.value = 'cash';
  mobileSection.value = 'products';
  clearCart();
}

onMounted(() => {
  loadProducts();
  loadHeldSalesFromStorage();
  fetchReceiptTemplate().then(data => {
    if (data) receiptTemplateData.value = data;
  });
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
        <button
          class="s-pill"
          :class="{ active: selectedCustomer || customerDetails.name || customerDetails.ntn }"
          @click="showCustomerModal = true"
          :title="selectedCustomer ? `${selectedCustomer.name} (Khata: ${formatPKR(selectedCustomer.current_balance_minor)})` : 'Attach Customer (F2)'"
        >
          <kbd>F2</kbd>
          <span v-if="selectedCustomer">
            {{ selectedCustomer.name }} ({{ formatPKR(selectedCustomer.current_balance_minor) }})
          </span>
          <span v-else-if="customerDetails.name">
            {{ customerDetails.name }}
          </span>
          <span v-else>Customer</span>
          <span v-if="selectedCustomer || customerDetails.name" class="pill-dot"></span>
        </button>
        <button
          v-if="selectedCustomer || customerDetails.name"
          class="s-pill"
          style="padding: 0.2rem 0.4rem; color: #ef4444;"
          title="Detach Customer"
          @click="detachCustomer"
        >
          &times;
        </button>
        <button class="s-pill" :class="{ active: discountPercent > 0 }" @click="openDiscountModal">
          <kbd>F3</kbd> Discount <span v-if="discountPercent > 0">({{ discountPercent }}%)</span>
        </button>
        <button class="s-pill" @click="mobileSection = 'products'; focusSearch(false);">
          <kbd>F4</kbd> Search
        </button>
        <button class="s-pill" @click="openReturnModal">
          <kbd>F6</kbd> Return
        </button>
        <button
          class="s-pill"
          :class="{ active: selectedPayment === 'split' }"
          :disabled="cart.length === 0"
          @click="openSplitPaymentModal"
          title="Split / Multi-Tender Payment (F7)"
        >
          <kbd>F7</kbd> Split
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
      <div class="col-catalog" :class="{ 'mobile-panel-active': mobileSection === 'products' }">
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
            :class="{ 'has-variants': product.variants && product.variants.length > 0 }"
            @click="onProductClick(product)"
          >
            <div class="product-top">
              <span class="product-name">{{ product.name }}</span>
              <span v-if="product.variants && product.variants.length > 0" class="variants-badge">
                {{ product.variants.length }} Variants
              </span>
              <span v-else class="tax-badge">{{ (product.tax_rate_basis_points / 100).toFixed(0) }}% Tax</span>
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
      <div class="col-cart" :class="{ 'mobile-panel-active': mobileSection === 'cart' }">
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
            <button class="mobile-browse-btn" @click="mobileSection = 'products'">
              Browse Products
            </button>
          </div>

          <div v-for="item in cart" :key="item.variant ? item.product.id + '_' + item.variant.id : (item.selected_uom ? item.product.id + '_' + item.selected_uom.id : item.product.id)" class="cart-item-row">
            <div class="item-info">
              <div class="item-name">
                {{ item.product.name }}
                <span v-if="item.variant" class="variant-tag">({{ item.variant.name }})</span>
                <span v-if="item.selected_uom" class="variant-tag" style="background: rgba(99, 102, 241, 0.2); color: #818cf8;">
                  [{{ item.selected_uom.unit_name }}]
                </span>
              </div>
              <div class="item-sub">
                <span class="item-unit-price">
                  {{ formatPKR(item.unit_price_minor) }} / {{ item.unit_code || item.product.unit || 'pcs' }}
                </span>
                <span class="pct-code-small">PCT: {{ item.product.hs_code }}</span>
              </div>
              <!-- Multi-UOM Pack Selector (if conversions available) -->
              <div v-if="item.product.uom_conversions && item.product.uom_conversions.length > 0" style="display: flex; gap: 0.35rem; margin-top: 0.35rem;">
                <button
                  type="button"
                  class="s-pill"
                  :class="{ active: !item.selected_uom }"
                  style="font-size: 0.68rem; padding: 0.15rem 0.45rem;"
                  title="Switch to Base Unit"
                  @click="switchItemUom(item, undefined)"
                >
                  {{ item.product.unit || 'pcs' }}
                </button>
                <button
                  v-for="u in item.product.uom_conversions"
                  :key="u.id"
                  type="button"
                  class="s-pill"
                  :class="{ active: item.selected_uom?.id === u.id }"
                  style="font-size: 0.68rem; padding: 0.15rem 0.45rem;"
                  :title="`1 ${u.unit_code} = ${Number(u.conversion_factor)} base units`"
                  @click="switchItemUom(item, u)"
                >
                  {{ u.unit_code }} ({{ Number(u.conversion_factor) }})
                </button>
              </div>
            </div>

            <div class="item-qty-controls">
              <button class="qty-btn" @click="decrementQty(item)">-</button>
              <input
                type="number"
                class="qty-input"
                :value="item.quantity"
                min="0.001"
                step="any"
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
      <div class="col-settlement" :class="{ 'mobile-panel-active': mobileSection === 'pay' }">
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
          <button
            class="pay-method-btn"
            :class="{ active: selectedPayment === 'credit' }"
            @click="selectCreditPayment"
          >
            Khata (Udhaar)
          </button>
          <button
            class="pay-method-btn"
            :class="{ active: selectedPayment === 'split' }"
            @click="openSplitPaymentModal"
          >
            Split (F7)
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

        <!-- Khata / Udhaar Credit Workflow -->
        <div v-else-if="selectedPayment === 'credit'" class="khata-workflow" style="margin-top: 1rem; padding: 0.85rem; background: rgba(99, 102, 241, 0.08); border-radius: 8px; border: 1px solid rgba(99, 102, 241, 0.25);">
          <div v-if="!selectedCustomer" style="text-align: center; padding: 0.5rem 0;">
            <div style="font-size: 0.85rem; color: #f59e0b; font-weight: 600; margin-bottom: 0.5rem;">
              No Customer Account Attached
            </div>
            <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.75rem;">
              Attach an authorized customer account to charge this purchase to their Khata ledger.
            </p>
            <button
              type="button"
              class="confirm-pay-btn"
              style="padding: 0.45rem 1rem; font-size: 0.825rem;"
              @click="showCustomerModal = true"
            >
              Attach Customer Account (F2)
            </button>
          </div>

          <div v-else>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <span style="font-size: 0.825rem; font-weight: 700; color: #818cf8;">
                {{ selectedCustomer.name }}
              </span>
              <span style="font-size: 0.75rem; color: #94a3b8; font-family: var(--font-mono);">
                {{ selectedCustomer.phone }}
              </span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.78rem; margin-bottom: 0.65rem;">
              <div style="background: rgba(0,0,0,0.25); padding: 0.4rem 0.6rem; border-radius: 6px;">
                <div style="color: #94a3b8; font-size: 0.7rem;">Current Due</div>
                <div style="font-weight: 700; color: #ef4444; font-family: var(--font-mono);">
                  {{ formatPKR(selectedCustomer.current_balance_minor) }}
                </div>
              </div>

              <div style="background: rgba(0,0,0,0.25); padding: 0.4rem 0.6rem; border-radius: 6px;">
                <div style="color: #94a3b8; font-size: 0.7rem;">Credit Headroom</div>
                <div style="font-weight: 700; color: #10b981; font-family: var(--font-mono);">
                  {{ formatPKR(customerAvailableCreditMinor) }}
                </div>
              </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.8rem;">
              <span style="color: #94a3b8;">Balance After Sale:</span>
              <span style="font-weight: 800; font-family: var(--font-mono); color: #f8fafc;">
                {{ formatPKR((selectedCustomer.current_balance_minor || 0) + totalMinor) }}
              </span>
            </div>

            <div v-if="creditExceeded" style="margin-top: 0.5rem; padding: 0.5rem; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; border-radius: 6px; color: #ef4444; font-size: 0.75rem;">
              <strong>Credit Limit Exceeded:</strong> Sale of {{ formatPKR(totalMinor) }} exceeds remaining limit of {{ formatPKR(customerAvailableCreditMinor) }}.
            </div>
            <div v-else style="margin-top: 0.5rem; padding: 0.4rem; background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 6px; color: #34d399; font-size: 0.75rem; text-align: center;">
              Authorized for Khata purchase
            </div>
          </div>
        </div>

        <!-- Split Tender Workflow -->
        <div v-else-if="selectedPayment === 'split'" class="split-workflow" style="margin-top: 1rem; padding: 0.85rem; background: rgba(56, 189, 248, 0.08); border-radius: 8px; border: 1px solid rgba(56, 189, 248, 0.25);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <span style="font-size: 0.825rem; font-weight: 700; color: #38bdf8;">Split Payment Tenders</span>
            <button
              type="button"
              @click="openSplitPaymentModal"
              style="font-size: 0.72rem; padding: 0.2rem 0.5rem; background: #0284c7; color: #fff; border: none; border-radius: 4px; cursor: pointer;"
            >
              Edit Split (F7)
            </button>
          </div>

          <div v-if="splitPaymentsApplied.length === 0" style="text-align: center; padding: 0.5rem 0;">
            <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.5rem;">
              Distribute total invoice amount across Cash, Card, Raast, or Khata.
            </p>
            <button
              type="button"
              class="confirm-pay-btn"
              style="padding: 0.45rem 1rem; font-size: 0.825rem;"
              @click="openSplitPaymentModal"
            >
              Allocate Split Tenders (F7)
            </button>
          </div>

          <div v-else style="display: flex; flex-direction: column; gap: 0.35rem;">
            <div
              v-for="(p, idx) in splitPaymentsApplied"
              :key="idx"
              style="display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.25); padding: 0.35rem 0.5rem; border-radius: 4px; font-size: 0.78rem;"
            >
              <span class="cap" style="font-weight: 600; color: #f8fafc;">
                {{ p.payment_method }} <span v-if="p.reference" style="font-size: 0.7rem; color: #94a3b8;">({{ p.reference }})</span>
              </span>
              <strong style="font-family: var(--font-mono); color: #38bdf8;">{{ formatPKR(p.amount_minor) }}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 0.35rem; margin-top: 0.25rem; font-size: 0.8rem;">
              <span style="color: #94a3b8;">Total Allocated:</span>
              <strong style="color: #10b981; font-family: var(--font-mono);">{{ formatPKR(splitTotalAllocatedMinor) }}</strong>
            </div>
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

    <!-- Mobile Sticky Cart Summary Bar (visible <= 960px when cart has items) -->
    <div
      v-if="cart.length > 0 && mobileSection !== 'pay'"
      class="mobile-sticky-bar"
    >
      <div class="sticky-summary" @click="mobileSection = 'cart'">
        <span class="sticky-count">{{ cartUnitsCount }} {{ cartUnitsCount === 1 ? 'item' : 'items' }}</span>
        <span class="sticky-total">{{ formatPKR(totalMinor) }}</span>
      </div>
      <button class="sticky-pay-btn" @click="mobileSection = 'pay'">
        Pay Now &rarr;
      </button>
    </div>

    <!-- Mobile Bottom 3-Tab Switcher (visible <= 960px) -->
    <div class="mobile-bottom-tabs">
      <button
        class="mobile-tab-btn"
        :class="{ active: mobileSection === 'products' }"
        @click="mobileSection = 'products'"
      >
        <svg class="m-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
        </svg>
        <span>Products</span>
        <span class="m-tab-pill" v-if="products.length > 0">{{ products.length }}</span>
      </button>

      <button
        class="mobile-tab-btn"
        :class="{ active: mobileSection === 'cart' }"
        @click="mobileSection = 'cart'"
      >
        <svg class="m-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        <span>Basket</span>
        <span class="m-tab-badge" v-if="cartUnitsCount > 0">{{ cartUnitsCount }}</span>
      </button>

      <button
        class="mobile-tab-btn"
        :class="{ active: mobileSection === 'pay' }"
        @click="mobileSection = 'pay'"
      >
        <svg class="m-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        <span>Pay</span>
      </button>
    </div>

    <!-- Product Variant Selection Modal -->
    <div v-if="showVariantModal && selectedProductForVariants" class="modal-overlay" @click.self="showVariantModal = false">
      <div class="modal-card variant-selector-modal" style="max-width: 540px; width: 92%;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding-bottom: 0.75rem;">
          <div>
            <h3 class="modal-title" style="margin-bottom: 0.25rem;">Select Product Variant</h3>
            <p class="modal-sub" style="margin-bottom: 0; color: #60a5fa; font-weight: 600;">{{ selectedProductForVariants.name }}</p>
          </div>
          <button class="clear-btn" @click="showVariantModal = false" style="font-size: 1.4rem; line-height: 1; padding: 0.2rem 0.5rem; background: none; border: none; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>

        <p style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.25rem;">
          Choose size, color, or option to add to basket. Multiple variants of the same product can be purchased simultaneously:
        </p>

        <div class="variants-selection-grid">
          <div
            v-for="v in selectedProductForVariants.variants"
            :key="v.id"
            class="variant-option-card"
            @click="addVariantAndClose(selectedProductForVariants, v)"
          >
            <div class="variant-opt-left">
              <span class="variant-opt-name">{{ v.name }}</span>
              <span v-if="v.sku" class="variant-opt-sku">SKU: {{ v.sku }}</span>
            </div>
            <div class="variant-opt-right">
              <span class="variant-opt-price">
                {{ formatPKR(v.price_override_minor ?? selectedProductForVariants.price_minor) }}
              </span>
              <span class="variant-opt-add">+ Add to Basket</span>
            </div>
          </div>
        </div>

        <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
          <button class="secondary-btn" @click="showVariantModal = false" style="padding: 0.45rem 1.2rem; font-size: 0.85rem;">Cancel (Esc)</button>
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

    <!-- 2. Customer CRM & Khata (Udhaar) Modal (F2) -->
    <div v-if="showCustomerModal" class="modal-overlay" @click.self="showCustomerModal = false">
      <div class="modal-card" style="max-width: 540px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
          <h3 class="modal-title" style="margin: 0;">Customer CRM &amp; Khata</h3>
          <button
            type="button"
            style="background: transparent; border: none; color: #94a3b8; font-size: 1.25rem; cursor: pointer;"
            @click="showCustomerModal = false"
          >
            &times;
          </button>
        </div>
        <p class="modal-sub">Attach account for credit purchases (Udhaar), loyalty tracking, or FBR high-value compliance</p>

        <!-- Tab Switcher -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 1px solid #334155; padding-bottom: 0.5rem;">
          <button
            type="button"
            class="s-pill"
            :class="{ active: customerModalTab === 'search' }"
            @click="customerModalTab = 'search'"
          >
            Search Customer
          </button>
          <button
            type="button"
            class="s-pill"
            :class="{ active: customerModalTab === 'create' }"
            @click="customerModalTab = 'create'"
          >
            + New Customer
          </button>
        </div>

        <!-- Current Attached Customer Banner (if selected) -->
        <div v-if="selectedCustomer" style="background: rgba(99, 102, 241, 0.12); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 8px; padding: 0.75rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div style="font-weight: 700; color: #818cf8; font-size: 0.9rem;">
              {{ selectedCustomer.name }}
            </div>
            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.15rem;">
              Phone: {{ selectedCustomer.phone }} &bull; Due: <strong style="color: #ef4444;">{{ formatPKR(selectedCustomer.current_balance_minor) }}</strong>
            </div>
          </div>
          <button
            type="button"
            class="chip-btn"
            style="color: #ef4444; border-color: rgba(239, 68, 68, 0.3);"
            @click="detachCustomer"
          >
            Detach
          </button>
        </div>

        <!-- TAB 1: Search Existing Customer -->
        <div v-if="customerModalTab === 'search'">
          <div class="form-group-pos" style="margin-bottom: 0.75rem;">
            <label>Search by Name, Phone, or CNIC</label>
            <input
              v-model="customerLookupQuery"
              class="pos-input"
              placeholder="e.g. 0300... or Tariq"
              @input="onCustomerSearchInput"
              @keydown.enter="onCustomerSearchInput"
            />
          </div>

          <!-- Search Results List -->
          <div style="max-height: 220px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1rem;">
            <div
              v-for="c in customerSearchResults"
              :key="c.id"
              style="padding: 0.6rem 0.75rem; background: #1e293b; border: 1px solid #334155; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: background 0.15s;"
              @click="selectCustomer(c)"
            >
              <div>
                <div style="font-weight: 700; font-size: 0.85rem; color: #f8fafc;">
                  {{ c.name }}
                </div>
                <div style="font-size: 0.72rem; color: #94a3b8; font-family: var(--font-mono);">
                  {{ c.phone }} <span v-if="c.cnic">&bull; CNIC: {{ c.cnic }}</span>
                </div>
              </div>
              <div style="text-align: right;">
                <div style="font-size: 0.7rem; color: #94a3b8;">Khata Due</div>
                <div style="font-size: 0.85rem; font-weight: 700; font-family: var(--font-mono);" :style="{ color: c.current_balance_minor > 0 ? '#ef4444' : '#10b981' }">
                  {{ formatPKR(c.current_balance_minor) }}
                </div>
              </div>
            </div>

            <div v-if="customerLookupQuery && customerSearchResults.length === 0 && !isSearchingCustomer" style="text-align: center; padding: 1.5rem; color: #94a3b8; font-size: 0.8rem;">
              No customers found. Switch to "+ New Customer" to register them.
            </div>
          </div>
        </div>

        <!-- TAB 2: Register New Customer -->
        <div v-else-if="customerModalTab === 'create'">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
            <div class="form-group-pos" style="margin: 0;">
              <label>Full Name *</label>
              <input v-model="newCustomerForm.name" class="pos-input" placeholder="e.g. Tariq Traders" required />
            </div>
            <div class="form-group-pos" style="margin: 0;">
              <label>Phone Number *</label>
              <input v-model="newCustomerForm.phone" class="pos-input" placeholder="03001234567" required />
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
            <div class="form-group-pos" style="margin: 0;">
              <label>CNIC (13 digits)</label>
              <input v-model="newCustomerForm.cnic" class="pos-input" placeholder="42101-1234567-1" />
            </div>
            <div class="form-group-pos" style="margin: 0;">
              <label>NTN</label>
              <input v-model="newCustomerForm.ntn" class="pos-input" placeholder="Optional" />
            </div>
          </div>

          <div class="form-group-pos" style="margin-bottom: 1rem;">
            <label>Credit Limit (PKR) - 0 for Cash-Only</label>
            <input v-model.number="newCustomerForm.credit_limit_pkr" type="number" min="0" step="1000" class="pos-input" placeholder="0" />
          </div>

          <button
            type="button"
            class="next-sale-btn"
            style="background: #10b981; border-color: #10b981; margin-bottom: 0.5rem;"
            :disabled="isSearchingCustomer || !newCustomerForm.name || !newCustomerForm.phone"
            @click="handleRegisterCustomer"
          >
            {{ isSearchingCustomer ? 'Registering...' : 'Register & Attach to Sale' }}
          </button>
        </div>

        <div v-if="customerErrorMessage" style="color: #ef4444; font-size: 0.75rem; margin-bottom: 0.75rem;">
          {{ customerErrorMessage }}
        </div>

        <!-- FBR Walk-in manual override (always visible at bottom) -->
        <div style="border-top: 1px solid #334155; padding-top: 0.75rem; margin-top: 0.5rem;">
          <div style="font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-bottom: 0.4rem;">
            One-Off FBR Compliance Details (Without Registering)
          </div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
            <input v-model="customerDetails.name" class="pos-input" style="font-size: 0.75rem; padding: 0.35rem 0.6rem;" placeholder="Buyer Name" />
            <input v-model="customerDetails.cnic" class="pos-input" style="font-size: 0.75rem; padding: 0.35rem 0.6rem;" placeholder="CNIC (for > 100k)" />
          </div>
        </div>

        <button class="next-sale-btn" style="margin-top: 1rem;" @click="showCustomerModal = false">Done (Esc)</button>
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
          <div v-if="completedResult?.payments && completedResult.payments.length > 1" style="margin: 0.5rem 0; padding: 0.5rem; background: rgba(0,0,0,0.25); border-radius: 6px; font-size: 0.78rem;">
            <div style="font-weight: 700; color: #38bdf8; margin-bottom: 0.25rem;">Split Tenders:</div>
            <div v-for="(p, idx) in completedResult.payments" :key="idx" style="display: flex; justify-content: space-between; margin-bottom: 2px;">
              <span class="cap">{{ p.payment_method }} {{ p.reference ? `(${p.reference})` : '' }}:</span>
              <strong>{{ formatPKR(p.amount_minor) }}</strong>
            </div>
          </div>
          <div class="m-row">
            <span>Payment Method:</span>
            <span class="cap">{{ completedResult?.paymentMethod }}</span>
          </div>
        </div>

        <div style="display: flex; gap: 0.75rem; margin-top: 1rem;">
          <button
            type="button"
            class="print-receipt-btn"
            @click="printThermalReceipt"
            style="flex: 1; padding: 0.75rem; background: #334155; color: #f8fafc; border: 1px solid #475569; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem;"
          >
            <span>🖨️</span> Print Thermal Receipt
          </button>
          <button
            class="next-sale-btn"
            style="flex: 1; margin: 0;"
            @click="closeConfirmationModal"
          >
            Next Sale <span class="shortcut">Enter</span>
          </button>
        </div>
      </div>
    </div>

    <!-- 6. Sales Return & FBR Credit Note Modal (F6) -->
    <div v-if="showReturnModal" class="modal-overlay" @click.self="showReturnModal = false">
      <div class="modal-card" style="max-width: 680px; width: 95%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding-bottom: 0.75rem;">
          <div>
            <h3 class="modal-title" style="margin-bottom: 0.25rem;">Sales Return &amp; FBR Credit Note</h3>
            <p class="modal-sub">Scan receipt barcode, verify items, and issue statutory Type 2 Credit Note</p>
          </div>
          <button class="modal-close-btn" @click="showReturnModal = false">&times;</button>
        </div>

        <!-- Success Result View -->
        <div v-if="returnSuccessResult" style="text-align: center; padding: 1.5rem 0;">
          <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(16, 185, 129, 0.2); color: #10b981; font-size: 1.75rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
            ✓
          </div>
          <h4 style="font-size: 1.25rem; font-weight: 800; color: #fff; margin-bottom: 0.25rem;">
            Return Processed Successfully
          </h4>
          <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 1.25rem;">
            Return #{{ returnSuccessResult.return_number }} &bull; FBR Credit Note #{{ returnSuccessResult.fbr_credit_note_number }}
          </p>

          <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 8px; padding: 1rem; max-width: 320px; margin: 0 auto 1.5rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #ef4444;">Refund Amount</div>
            <div style="font-size: 1.75rem; font-weight: 800; font-family: monospace; color: #ef4444; margin-top: 0.25rem;">
              -{{ formatPKR(returnSuccessResult.total_refund_minor) }}
            </div>
          </div>

          <button class="next-sale-btn" @click="showReturnModal = false">
            Done (Esc)
          </button>
        </div>

        <!-- Return Workflow Form -->
        <div v-else>
          <!-- Step 1: Scan or Type Invoice # -->
          <div style="margin-bottom: 1.25rem;">
            <label style="display: block; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 0.4rem;">
              Scan Receipt Barcode or Type Invoice Number
            </label>
            <div style="display: flex; gap: 0.5rem;">
              <input
                v-model="returnInvoiceQuery"
                type="text"
                class="pos-input"
                placeholder="e.g. INV-20261122-0001"
                style="flex: 1; font-family: monospace; font-weight: 700;"
                @keydown.enter="handleLookupReturnInvoice"
              />
              <button
                type="button"
                class="chip-btn"
                style="background: #3b82f6; color: white; padding: 0.5rem 1rem;"
                :disabled="isVerifyingReturnInvoice || !returnInvoiceQuery.trim()"
                @click="handleLookupReturnInvoice"
              >
                {{ isVerifyingReturnInvoice ? 'Verifying...' : 'Verify' }}
              </button>
            </div>
            <div v-if="returnErrorMessage" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.4rem;">
              {{ returnErrorMessage }}
            </div>
          </div>

          <!-- Step 2: Return Items Table if verified -->
          <div v-if="returnVerificationResult" style="border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 1rem; margin-top: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
              <span style="font-size: 0.85rem; font-weight: 700; color: #f8fafc;">
                Invoice #{{ returnVerificationResult.sale.sale_number }}
              </span>
              <span v-if="returnVerificationResult.sale.customer" style="font-size: 0.78rem; color: #94a3b8;">
                Customer: <strong style="color: #fff;">{{ returnVerificationResult.sale.customer.name }}</strong>
              </span>
            </div>

            <div style="max-height: 220px; overflow-y: auto; margin-bottom: 1rem;">
              <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
                <thead>
                  <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1); color: #94a3b8; text-align: left;">
                    <th style="padding: 0.4rem 0.25rem;">Item</th>
                    <th style="padding: 0.4rem 0.25rem; text-align: center;">Sold</th>
                    <th style="padding: 0.4rem 0.25rem; text-align: center;">Returnable</th>
                    <th style="padding: 0.4rem 0.25rem; width: 85px; text-align: center;">Return Qty</th>
                    <th style="padding: 0.4rem 0.25rem;">Restock Condition</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in returnItemsState" :key="item.sale_item_id" style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                    <td style="padding: 0.5rem 0.25rem;">
                      <div style="font-weight: 700; color: #fff;">{{ item.product_name }}</div>
                      <div style="font-size: 0.72rem; color: #94a3b8;">{{ formatPKR(item.unit_price_minor) }} / {{ item.unit_code }}</div>
                    </td>
                    <td style="padding: 0.5rem 0.25rem; text-align: center; font-family: monospace;">
                      {{ item.sold_quantity }}
                    </td>
                    <td style="padding: 0.5rem 0.25rem; text-align: center; font-family: monospace; color: #10b981; font-weight: 700;">
                      {{ item.returnable_quantity }}
                    </td>
                    <td style="padding: 0.5rem 0.25rem;">
                      <input
                        v-model.number="item.quantity_returned"
                        type="number"
                        step="any"
                        min="0"
                        :max="item.returnable_quantity"
                        class="pos-input"
                        style="padding: 0.25rem; font-family: monospace; text-align: center; font-size: 0.85rem;"
                        placeholder="0"
                        :disabled="item.returnable_quantity <= 0"
                      />
                    </td>
                    <td style="padding: 0.5rem 0.25rem;">
                      <select
                        v-model="item.restock_condition"
                        class="pos-input"
                        style="padding: 0.25rem; font-size: 0.75rem;"
                        :disabled="item.quantity_returned <= 0"
                      >
                        <option value="restock_shelf">Restock Shelf</option>
                        <option value="damaged_quarantine">Damaged (Quarantine)</option>
                        <option value="discard">Discard / Expired</option>
                      </select>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Refund Method & Settlement -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
              <div>
                <label style="display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 0.3rem;">
                  Refund Settlement Mode
                </label>
                <select v-model="returnRefundMethod" class="pos-input" style="font-size: 0.8rem;">
                  <option value="cash">Cash Refund (Drawer Cash)</option>
                  <option v-if="returnVerificationResult.sale.customer_id" value="credit_note_khata">
                    Customer Khata Credit (Udhaar)
                  </option>
                  <option value="original_payment">Original Payment Method</option>
                </select>
              </div>

              <div>
                <label style="display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 0.3rem;">
                  Return Reason
                </label>
                <select v-model="returnReason" class="pos-input" style="font-size: 0.8rem;">
                  <option value="customer_change_of_mind">Customer Change of Mind</option>
                  <option value="defective">Defective / Damaged</option>
                  <option value="wrong_item">Wrong Item</option>
                  <option value="expired">Expired</option>
                  <option value="other">Other</option>
                </select>
              </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem;">
              <div>
                <span style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Total Refund</span>
                <div style="font-size: 1.35rem; font-weight: 800; font-family: monospace; color: #ef4444;">
                  -{{ formatPKR(totalReturnRefundMinor) }}
                </div>
              </div>

              <button
                type="button"
                class="confirm-pay-btn"
                style="background: #ef4444; border-color: #ef4444; width: auto; padding: 0.6rem 1.25rem;"
                :disabled="isProcessingReturn || totalReturnRefundMinor <= 0"
                @click="handleProcessReturn"
              >
                {{ isProcessingReturn ? 'Processing...' : 'Issue FBR Credit Note' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 7. Split Payment / Multi-Tender Modal (F7) -->
    <div v-if="showSplitModal" class="modal-overlay" @click.self="showSplitModal = false">
      <div class="modal-card" style="max-width: 580px; width: 95%;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding-bottom: 0.75rem;">
          <div>
            <h3 class="modal-title" style="margin-bottom: 0.25rem;">Multi-Tender / Split Payment</h3>
            <p class="modal-sub">Allocate invoice balance across multiple payment methods</p>
          </div>
          <button class="modal-close-btn" @click="showSplitModal = false">&times;</button>
        </div>

        <!-- Balance Due & Allocation Tracker -->
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem;">
          <div style="background: rgba(0,0,0,0.3); padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08);">
            <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase;">Total Invoice Due</div>
            <div style="font-size: 1.1rem; font-weight: 800; color: #f8fafc; font-family: var(--font-mono);">
              {{ formatPKR(totalMinor) }}
            </div>
          </div>

          <div style="background: rgba(0,0,0,0.3); padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08);">
            <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase;">Allocated So Far</div>
            <div style="font-size: 1.1rem; font-weight: 800; font-family: var(--font-mono);" :style="{ color: splitTotalAllocatedMinor > totalMinor ? '#ef4444' : '#38bdf8' }">
              {{ formatPKR(splitTotalAllocatedMinor) }}
            </div>
          </div>

          <div style="background: rgba(0,0,0,0.3); padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08);">
            <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase;">Remaining</div>
            <div style="font-size: 1.1rem; font-weight: 800; font-family: var(--font-mono);" :style="{ color: splitRemainingMinor === 0 ? '#10b981' : (splitRemainingMinor < 0 ? '#ef4444' : '#f59e0b') }">
              {{ formatPKR(splitRemainingMinor) }}
            </div>
          </div>
        </div>

        <!-- Error Notification -->
        <div v-if="splitCreditError" style="padding: 0.5rem 0.75rem; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; border-radius: 6px; color: #ef4444; font-size: 0.75rem; margin-bottom: 1rem;">
          {{ splitCreditError }}
        </div>

        <!-- Tender Distribution Rows -->
        <div style="display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 1.25rem;">
          <div
            v-for="(tender, idx) in splitTenders"
            :key="tender.method"
            style="background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 0.75rem;"
          >
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="cap" style="font-weight: 700; font-size: 0.85rem; color: #f8fafc;">
                  {{ tender.method === 'credit' ? 'Customer Khata (Udhaar)' : tender.method }}
                </span>
                <span v-if="tender.method === 'credit' && selectedCustomer" style="font-size: 0.7rem; color: #10b981;">
                  (Max: {{ formatPKR(customerAvailableCreditMinor) }})
                </span>
                <span v-else-if="tender.method === 'credit' && !selectedCustomer" style="font-size: 0.7rem; color: #ef4444;">
                  (No customer attached)
                </span>
              </div>
              <button
                type="button"
                @click="allocateRemainingTo(idx)"
                style="font-size: 0.7rem; padding: 0.2rem 0.5rem; background: #334155; color: #38bdf8; border: 1px solid #475569; border-radius: 4px; cursor: pointer; font-weight: 600;"
              >
                + Fill Remaining
              </button>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
              <div>
                <input
                  type="number"
                  v-model.number="tender.amount"
                  min="0"
                  step="0.01"
                  placeholder="0.00"
                  class="pos-input"
                  style="font-family: var(--font-mono); font-size: 0.9rem; font-weight: 700;"
                  @input="validateSplitCredit"
                />
              </div>
              <div>
                <input
                  v-if="tender.method !== 'cash'"
                  type="text"
                  v-model="tender.ref"
                  :placeholder="tender.method === 'credit' ? 'Customer purchase note...' : (tender.method === 'card' ? 'Approval code / Card #' : 'Raast Tx ID / Ref #')"
                  class="pos-input"
                  style="font-size: 0.8rem;"
                />
                <div v-else style="display: flex; align-items: center; height: 100%; color: #94a3b8; font-size: 0.75rem; padding-left: 0.5rem;">
                  POS Cash in Drawer
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Actions -->
        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
          <button
            type="button"
            class="chip-btn"
            style="padding: 0.6rem 1rem;"
            @click="clearSplitPayment"
          >
            Clear / Cancel
          </button>
          <button
            type="button"
            class="next-sale-btn"
            style="margin: 0; width: auto; padding: 0.6rem 1.5rem;"
            :disabled="!isSplitValid"
            @click="applySplitPayment"
          >
            Apply Split Payment (Enter)
          </button>
        </div>
      </div>
    </div>

    <!-- Printable Thermal Receipt (Hidden on screen, styled with active ReceiptTemplate when printed) -->
    <div id="thermal-receipt" class="print-only">
      <div
        :style="{
          width: receiptTemplateData?.template.paper_width === '58mm' ? '58mm' : '80mm',
          fontFamily: `'Courier New', Courier, monospace`,
          fontSize: receiptTemplateData?.template.paper_width === '58mm' ? '10px' : '12px',
          color: '#000000',
          padding: '4px',
          lineHeight: '1.25'
        }"
      >
        <div style="text-align: center; margin-bottom: 6px;">
          <div style="font-size: 14px; font-weight: 900; text-transform: uppercase;">
            {{ receiptTemplateData?.template.business_name || receiptTemplateData?.tenant.name || 'POS STORE' }}
          </div>
          <div v-if="receiptTemplateData?.template.branch_name" style="font-weight: 700;">
            {{ receiptTemplateData.template.branch_name }}
          </div>
          <div v-if="receiptTemplateData?.template.address_line1">
            {{ receiptTemplateData.template.address_line1 }}
          </div>
          <div v-if="receiptTemplateData?.template.phone">
            Tel: {{ receiptTemplateData.template.phone }}
          </div>
          <div v-if="receiptTemplateData?.template.header_note" style="border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 2px 0; margin-top: 2px; font-weight: 700;">
            {{ receiptTemplateData.template.header_note }}
          </div>
        </div>

        <div style="border-bottom: 1px dashed #000; padding-bottom: 3px; margin-bottom: 4px; font-size: 10px;">
          <div style="display: flex; justify-content: space-between;">
            <span>{{ receiptTemplateData?.template.ntn_label || 'NTN:' }}</span>
            <span style="font-weight: 700;">{{ receiptTemplateData?.tenant.ntn || 'N/A' }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>{{ receiptTemplateData?.template.strn_label || 'STRN:' }}</span>
            <span style="font-weight: 700;">{{ receiptTemplateData?.tenant.strn || 'N/A' }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>{{ receiptTemplateData?.template.pos_id_label || 'POS ID:' }}</span>
            <span style="font-weight: 700;">{{ receiptTemplateData?.tenant.fbr_pos_id || 'POS-001' }}</span>
          </div>
        </div>

        <div style="font-size: 10px; margin-bottom: 4px;">
          <div style="display: flex; justify-content: space-between;">
            <span>Inv #: {{ completedResult?.saleNumber }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>Date: {{ new Date().toLocaleDateString('en-GB') }}</span>
          </div>
          <div v-if="receiptTemplateData?.template.show_customer_info && completedResult?.customer" style="display: flex; justify-content: space-between;">
            <span>Customer: {{ completedResult.customer.name }}</span>
          </div>
        </div>

        <div style="border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 2px 0; font-weight: 700; display: flex; justify-content: space-between; font-size: 10px;">
          <span>ITEM</span>
          <span>QTY x RATE</span>
          <span>TOTAL</span>
        </div>

        <div style="padding: 4px 0; border-bottom: 1px dashed #000; font-size: 10px;">
          <div v-for="item in completedResult?.items || []" :key="item.product.id" style="margin-bottom: 3px;">
            <div style="font-weight: 700;">{{ item.product.name }}</div>
            <div style="display: flex; justify-content: space-between;">
              <span>{{ item.quantity }} x {{ formatPKR(item.unit_price_minor) }}</span>
              <span>{{ formatPKR(item.total_minor) }}</span>
            </div>
          </div>
        </div>

        <div style="padding: 4px 0; font-size: 11px;">
          <div style="display: flex; justify-content: space-between;">
            <span>Subtotal:</span>
            <span>{{ formatPKR(completedResult?.subtotalMinor || 0) }}</span>
          </div>
          <div v-if="receiptTemplateData?.template.show_tax_breakdown" style="display: flex; justify-content: space-between;">
            <span>Sales Tax:</span>
            <span>{{ formatPKR(completedResult?.taxMinor || 0) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between; font-weight: 900; font-size: 13px; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 3px 0; margin-top: 2px;">
            <span>TOTAL:</span>
            <span>{{ formatPKR(completedResult?.totalMinor || 0) }}</span>
          </div>

          <div v-if="completedResult?.payments && completedResult.payments.length > 1" style="margin-top: 4px; font-size: 10px;">
            <div v-for="(p, i) in completedResult.payments" :key="i" style="display: flex; justify-content: space-between;">
              <span class="cap">{{ p.payment_method }}:</span>
              <span>{{ formatPKR(p.amount_minor) }}</span>
            </div>
          </div>
          <div v-else style="display: flex; justify-content: space-between; font-size: 10px; margin-top: 3px;">
            <span class="cap">Tender ({{ completedResult?.paymentMethod }}):</span>
            <span>{{ formatPKR(completedResult?.tenderedMinor || 0) }}</span>
          </div>
        </div>

        <div v-if="receiptTemplateData?.template.show_fbr_qr && completedResult?.result.status === 'Submitted'" style="text-align: center; margin-top: 6px;">
          <div style="font-size: 10px; font-weight: 700;">USIN: {{ completedResult.result.data.usin }}</div>
          <div style="font-size: 8px;">FBR Digital Fiscal Invoice</div>
        </div>

        <div style="text-align: center; margin-top: 6px; font-size: 9px; border-top: 1px dashed #000; padding-top: 4px;">
          <div v-if="receiptTemplateData?.template.footer_policy" style="font-style: italic; margin-bottom: 2px;">
            {{ receiptTemplateData.template.footer_policy }}
          </div>
          <div v-if="receiptTemplateData?.template.footer_greeting" style="font-weight: 700;">
            {{ receiptTemplateData.template.footer_greeting }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media screen {
  .print-only {
    display: none !important;
  }
}
@media print {
  body * {
    visibility: hidden;
  }
  #thermal-receipt, #thermal-receipt * {
    visibility: visible;
  }
  #thermal-receipt {
    position: absolute;
    left: 0;
    top: 0;
    display: block !important;
  }
}

.checkout-container {
  display: flex;
  flex-direction: column;
  flex: 1;
  height: 100%;
  min-height: 0;
  width: 100%;
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
  min-height: 0;
  width: 100%;
  overflow: hidden;
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
  min-height: 0;
  min-width: 0;
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
  flex-shrink: 0;
}

.search-input {
  flex: 1;
  min-width: 0;
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
  min-height: 0;
  min-width: 0;
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
  min-height: 0;
  min-width: 0;
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

/* Variant Badges & Selectors */
.variants-badge {
  font-size: 0.7rem;
  padding: 0.15rem 0.45rem;
  border-radius: 9999px;
  background: rgba(139, 92, 246, 0.15);
  color: #c084fc;
  border: 1px solid rgba(139, 92, 246, 0.35);
  font-weight: 600;
}

.product-card.has-variants {
  border-left: 3px solid #8b5cf6;
}

.variant-tag {
  display: inline-block;
  margin-left: 0.4rem;
  padding: 0.1rem 0.4rem;
  background: rgba(139, 92, 246, 0.15);
  color: #c084fc;
  border: 1px solid rgba(139, 92, 246, 0.3);
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}

.variants-selection-grid {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  max-height: 380px;
  overflow-y: auto;
}

.variant-option-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(30, 41, 59, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 0.85rem 1.1rem;
  cursor: pointer;
  transition: all 0.15s ease;
}

.variant-option-card:hover {
  background: rgba(51, 65, 85, 0.9);
  border-color: #8b5cf6;
  transform: translateX(3px);
}

.variant-opt-left {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.variant-opt-name {
  font-size: 0.95rem;
  font-weight: 700;
  color: #f8fafc;
}

.variant-opt-sku {
  font-size: 0.75rem;
  color: #94a3b8;
  font-family: monospace;
}

.variant-opt-right {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.variant-opt-price {
  font-size: 0.95rem;
  font-weight: 700;
  color: #34d399;
}

.variant-opt-add {
  background: #2563eb;
  color: #ffffff;
  padding: 0.35rem 0.75rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  transition: background 0.15s ease;
}

.variant-option-card:hover .variant-opt-add {
  background: #1d4ed8;
}

.mobile-browse-btn,
.mobile-sticky-bar,
.mobile-bottom-tabs {
  display: none;
}

/* Mobile & Portrait Tablet Checkout (<= 960px) */
@media (max-width: 960px) {
  .shortcuts-command-bar {
    height: auto;
    padding: 0.4rem 0.65rem;
    gap: 0.5rem;
  }

  .shortcut-pills-row {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding-bottom: 1px;
  }

  .shortcut-pills-row::-webkit-scrollbar {
    display: none;
  }

  .s-pill {
    flex-shrink: 0;
    white-space: nowrap;
    min-height: 30px;
    padding: 0.25rem 0.6rem;
    font-size: 0.75rem;
  }

  .s-pill kbd,
  .scanner-status-indicator,
  .next-sale-btn .shortcut {
    display: none;
  }

  .checkout-main-grid {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
  }

  .col-catalog,
  .col-cart,
  .col-settlement {
    display: none;
    flex: 1;
    width: 100%;
    border-right: none;
    min-height: 0;
  }

  .col-catalog.mobile-panel-active,
  .col-cart.mobile-panel-active,
  .col-settlement.mobile-panel-active {
    display: flex;
  }

  .products-grid {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    padding: 0.75rem;
    gap: 0.65rem;
  }

  .mobile-browse-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-top: 1rem;
    background: #2563eb;
    color: #ffffff;
    border: none;
    border-radius: 0.5rem;
    padding: 0.65rem 1.25rem;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
  }

  .mobile-sticky-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    background: #1e293b;
    border-top: 1px solid #334155;
    padding: 0.5rem 0.75rem;
    flex-shrink: 0;
  }

  .sticky-summary {
    display: flex;
    flex-direction: column;
    min-width: 0;
    cursor: pointer;
  }

  .sticky-count {
    font-size: 0.72rem;
    font-weight: 600;
    color: #94a3b8;
  }

  .sticky-total {
    font-size: 1.05rem;
    font-weight: 800;
    color: #10b981;
  }

  .sticky-pay-btn {
    background: #10b981;
    color: #ffffff;
    border: none;
    border-radius: 0.5rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    box-shadow: 0 2px 10px rgba(16, 185, 129, 0.35);
  }

  .mobile-bottom-tabs {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    background: #111827;
    border-top: 1px solid #1f2937;
    flex-shrink: 0;
  }

  .mobile-tab-btn {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    background: transparent;
    border: none;
    border-top: 2px solid transparent;
    color: #94a3b8;
    padding: 0.65rem 0.35rem;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    min-height: 48px;
  }

  .mobile-tab-btn.active {
    color: #ffffff;
    background: rgba(37, 99, 235, 0.14);
    border-top-color: #3b82f6;
  }

  .m-tab-icon {
    width: 1.1rem;
    height: 1.1rem;
    flex-shrink: 0;
  }

  .m-tab-pill {
    font-size: 0.68rem;
    background: #1e293b;
    color: #cbd5e1;
    padding: 0.05rem 0.4rem;
    border-radius: 9999px;
  }

  .m-tab-badge {
    font-size: 0.7rem;
    font-weight: 800;
    background: #2563eb;
    color: #ffffff;
    padding: 0.08rem 0.42rem;
    border-radius: 9999px;
  }

  .modal-overlay {
    padding: 0.75rem;
    overflow-y: auto;
  }

  .modal-card {
    max-height: 88dvh;
    overflow-y: auto;
    padding: 1.25rem;
  }
}

/* Smartphone Layout Refinements (<= 600px) */
@media (max-width: 600px) {
  .search-box {
    padding: 0.55rem 0.65rem;
    gap: 0.5rem;
  }

  .search-input {
    font-size: 16px;
    padding: 0.55rem 0.7rem;
  }

  .products-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem;
    padding: 0.6rem;
  }

  .product-card {
    padding: 0.65rem;
    min-height: 92px;
  }

  .product-name {
    font-size: 0.84rem;
    word-break: break-word;
  }

  .product-meta {
    flex-wrap: wrap;
    gap: 0.25rem;
  }

  .price-tag {
    font-size: 0.95rem;
  }

  .cart-header {
    padding: 0.65rem 0.85rem;
    flex-wrap: wrap;
    gap: 0.4rem;
  }

  .cart-title {
    font-size: 1rem;
  }

  .cart-items-list {
    padding: 0.6rem;
    gap: 0.5rem;
  }

  .cart-item-row {
    display: grid;
    grid-template-columns: 1fr auto;
    grid-template-areas:
      'info remove'
      'qty total';
    align-items: center;
    gap: 0.55rem 0.45rem;
    padding: 0.7rem 0.75rem;
  }

  .item-info {
    grid-area: info;
  }

  .item-name {
    white-space: normal;
    font-size: 0.9rem;
    line-height: 1.25;
  }

  .item-sub {
    flex-wrap: wrap;
    gap: 0.35rem;
  }

  .remove-line-btn {
    grid-area: remove;
    justify-self: end;
    align-self: start;
    font-size: 1.35rem;
    padding: 0.1rem 0.3rem;
  }

  .item-qty-controls {
    grid-area: qty;
    justify-self: start;
  }

  .qty-btn {
    width: 36px;
    height: 34px;
    font-size: 1.1rem;
  }

  .qty-input {
    width: 42px;
    height: 34px;
    font-size: 0.95rem;
  }

  .item-total {
    grid-area: total;
    min-width: 0;
    text-align: right;
  }

  .col-settlement {
    padding: 0.75rem;
    gap: 0.65rem;
  }

  .payment-method-selector {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.45rem;
  }

  .pay-method-btn {
    min-height: 42px;
    font-size: 0.85rem;
  }

  .quick-chips {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(84px, 1fr));
    gap: 0.4rem;
  }

  .chip-btn {
    min-height: 38px;
    min-width: 0;
  }

  .tender-input {
    font-size: 1.15rem;
  }

  .variant-option-card {
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.75rem;
  }

  .variant-opt-right {
    width: 100%;
    justify-content: space-between;
  }
}
</style>
