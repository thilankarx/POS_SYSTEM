<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useCartStore } from '../stores/cart.js';
import { usePaymentMethodsStore } from '../stores/paymentMethods.js';
import { useBusinessStore } from '../stores/business.js';
import { apiFetch, ApiError } from '../api/client.js';
import { formatMoney } from '../lib/money.js';

const router = useRouter();
const cart = useCartStore();
const paymentMethods = usePaymentMethodsStore();
const business = useBusinessStore();

// The sale can also finish in the background (the offline queue draining
// once connectivity returns) while the cashier is still sitting on this
// screen — not just via the explicit "Complete sale" click below.
watch(
    () => cart.cart?.status,
    (status) => {
        if (status === 'completed') {
            router.push({ name: 'confirm' });
        }
    },
);
const selectedMethod = ref(null);
const tendered = ref('');
const reference = ref('');
const error = ref(null);
const completing = ref(false);
const giftcardBalance = ref(null);
const giftcardError = ref(null);
const giftcardChecking = ref(false);
const tenderedInput = ref(null);
const showOrderDetails = ref(false);
const paymentBusy = ref(false);

onMounted(async () => {
    if (!cart.cart) {
        router.replace({ name: 'register' });
        return;
    }
    // Best-effort: refresh() silently keeps whatever was cached from the
    // app's last successful fetch if this one fails (offline), which is
    // what lets tendering still work without a live connection.
    await paymentMethods.refresh();
    selectedMethod.value = paymentMethods.methods[0] ?? null;
    await nextTick();
    tenderedInput.value?.focus({ preventScroll: true });
    window.addEventListener('keydown', onPaymentKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onPaymentKeydown);
});

// Digit keys pick a payment method tile — unlike Register.vue's F-key
// shortcuts, digits collide with typing an amount, so this reuses
// BarcodeCapture.vue's input-target check to never hijack a text field.
function onPaymentKeydown(event) {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName)) {
        return;
    }
    const index = Number(event.key) - 1;
    if (Number.isInteger(index) && index >= 0 && paymentMethods.methods[index]) {
        event.preventDefault();
        selectPaymentMethod(paymentMethods.methods[index]);
    }
}

function selectPaymentMethod(method) {
    if (selectedMethod.value?.id !== method.id) {
        reference.value = '';
        giftcardBalance.value = null;
        giftcardError.value = null;
    }
    selectedMethod.value = method;
    error.value = null;
    nextTick(() => tenderedInput.value?.focus({ preventScroll: true }));
}

const due = computed(() => cart.dueRemaining);

// Gross line amount, before any line-level discount -- matches how the
// register's running total in Register.vue is derived (qty x unit price).
const lineExtended = (line) => Number(line.unit_price || 0) * Number(line.quantity || 0);

// "2.000" -> "2", "1.500" -> "1.5" for the qty x price caption.
const trimQty = (quantity) => String(Number(quantity));

const grandTotal = computed(
    () => Number(cart.cart?.totals.total || 0) + Number(cart.cart?.tip_amount || 0),
);

// The reference box doubles as the slot for the number the card terminal
// prints on its approval slip, so show it for a manually-run card method
// too -- not just methods explicitly flagged requires_reference. When
// shown it is mandatory, mirroring AddCartPaymentAction server-side.
// (Integrated card providers capture their own reference, so not those.)
const showReference = computed(() => {
    const method = selectedMethod.value;
    if (!method) {
        return false;
    }
    return method.requires_reference || (method.kind === 'card' && (method.provider ?? 'manual') === 'manual');
});

const referenceMissing = computed(() => showReference.value && !reference.value.trim());

// Cart payments only carry the method *code* ("card", "cash"); resolve it
// to the configured display name for the bill, falling back to the code.
const methodName = (code) => paymentMethods.methods.find((method) => method.code === code)?.name ?? code;

const referenceLabel = computed(() => {
    if (selectedMethod.value?.code === 'giftcard') {
        return 'Gift card number';
    }
    if (selectedMethod.value?.kind === 'card') {
        return 'Card machine approval / slip no.';
    }
    return 'Reference';
});

// Cash owed back to the customer: change from payments already taken,
// plus the overpayment implied by whatever is currently typed in the
// tender box for a change-giving method. The typed part folds into
// `cart.changeDue` the moment "Add payment" runs, so it never
// double-counts.
const changeDue = computed(() => {
    let change = cart.changeDue;
    const typed = Number(tendered.value);
    if (selectedMethod.value?.allows_change && typed > 0 && typed > due.value) {
        change += typed - due.value;
    }
    return Math.round(change * 100) / 100;
});

// Quick cash-tender suggestions — "Exact" plus a few round-up-to-nearest
// amounts computed from the due total, so this works for any currency's
// typical denominations rather than hardcoding e.g. USD bills.
const quickAmounts = computed(() => {
    if (!due.value || due.value <= 0) {
        return [];
    }
    const roundUps = new Set();
    [5, 10, 20, 50, 100].forEach((step) => {
        const rounded = Math.ceil(due.value / step) * step;
        if (rounded > due.value) {
            roundUps.add(rounded);
        }
    });
    return [...roundUps].sort((a, b) => a - b).slice(0, 3);
});

watch(reference, () => {
    giftcardBalance.value = null;
    giftcardError.value = null;
});

async function addPayment() {
    if (paymentBusy.value) {
        return;
    }
    error.value = null;
    if (!selectedMethod.value) {
        return;
    }
    if (referenceMissing.value) {
        error.value = `${referenceLabel.value} is required for ${selectedMethod.value.name}.`;
        return;
    }
    // due.value can legitimately be exactly 0 once the cart is already
    // covered -- `due.value || …` treated that as "unknown" and fell back
    // to charging the full typed amount instead of clamping to nothing
    // owed. `??` only falls back on null/undefined, never on a real 0.
    const amount = tendered.value ? Math.min(Number(tendered.value), due.value ?? Number(tendered.value)) : due.value;
    if (!amount || amount <= 0) {
        error.value = 'Enter an amount greater than zero.';
        return;
    }
    paymentBusy.value = true;
    try {
        // Only change-giving methods (cash) record what was tendered --
        // it is the basis for the change owed. Typing "50" against a
        // card payment still just books the amount due, no tendered.
        const tenderedForRecord = selectedMethod.value.allows_change ? tendered.value || null : null;
        await cart.addPayment(selectedMethod.value, amount.toFixed(2), tenderedForRecord, reference.value || null);
        tendered.value = '';
        reference.value = '';
        // Mid-split: refill the next guest's share automatically so the
        // cashier can just keep hitting "Add payment" once per guest.
        if (splitInto.value > 1) {
            if (due.value > 0) {
                tendered.value = Math.min(due.value, splitShare.value).toFixed(2);
            } else {
                splitInto.value = 1;
            }
        }
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Unable to add payment.';
    } finally {
        paymentBusy.value = false;
    }
}

// -- Split bill, evenly N ways --------------------------------------------
// Purely a client-side tendering convenience: the share is total÷N, fixed
// at the moment N is chosen (not recomputed against a shrinking due as
// shares are paid), so N equal payments cover the bill with only the last
// one absorbing a cent or two of rounding via the due-vs-share min().
// There is still exactly one Sale/receipt at the end -- nothing server-side
// needs to know the bill was split.
const splitInto = ref(1);
// A plain ref, not a computed() off `due` -- it must stay fixed at the
// original due÷N once chosen. A computed here would silently recompute
// against the *shrinking* due as each share gets paid (share 1 = due÷N,
// but by share 2 the remaining due is smaller, so due÷N is smaller too),
// snowballing into every share after the first being short.
const splitShare = ref(null);

function chooseSplit(n) {
    splitInto.value = n;
    if (due.value > 0) {
        splitShare.value = Math.round((due.value / n) * 100) / 100;
        tendered.value = Math.min(due.value, splitShare.value).toFixed(2);
    }
}

function clearSplit() {
    splitInto.value = 1;
    splitShare.value = null;
    tendered.value = '';
}

// -- Tip --------------------------------------------------------------------
// Percentage suggestions are computed off the subtotal, before tax -- the
// same basis already established for waiter commission, kept consistent
// rather than inventing a second convention. `totals.subtotal` is already
// net of every line discount (see CartPricer::price() server-side), so it
// must not be discounted a second time here -- doing so used to send this
// negative past a 50% line discount and zero out every tip suggestion.
const tipBasis = computed(() => Math.max(0, Number(cart.cart?.totals.subtotal || 0)));
const tipPercent = ref(null);
const customTip = ref('');
const tipBusy = ref(false);

async function chooseTipPercent(pct) {
    tipPercent.value = pct;
    customTip.value = '';
    tipBusy.value = true;
    try {
        await cart.setTip(((tipBasis.value * pct) / 100).toFixed(2));
    } finally {
        tipBusy.value = false;
    }
}

async function applyCustomTip() {
    tipPercent.value = null;
    tipBusy.value = true;
    try {
        await cart.setTip(Number(customTip.value || 0).toFixed(2));
    } finally {
        tipBusy.value = false;
    }
}

function onCustomTipBlur() {
    if (customTip.value !== '') {
        applyCustomTip();
    }
}

async function clearTip() {
    tipPercent.value = null;
    customTip.value = '';
    tipBusy.value = true;
    try {
        await cart.setTip('0');
    } finally {
        tipBusy.value = false;
    }
}

// Lets a cashier drive the whole tender-and-checkout loop with just
// Enter: add the typed amount, then complete immediately once it's
// covered the due total — or complete straight away if it already is.
async function handleTenderedEnter() {
    if (due.value <= 0) {
        await complete();
        return;
    }
    await addPayment();
    if (cart.dueRemaining <= 0) {
        await complete();
    }
}

async function checkGiftcardBalance() {
    giftcardError.value = null;
    giftcardBalance.value = null;
    if (!reference.value.trim()) {
        return;
    }
    giftcardChecking.value = true;
    try {
        const res = await apiFetch(`/giftcards/${encodeURIComponent(reference.value.trim())}`);
        giftcardBalance.value = res.data;
    } catch (e) {
        giftcardError.value = e instanceof ApiError ? e.message : 'Could not check that gift card.';
    } finally {
        giftcardChecking.value = false;
    }
}

async function discard() {
    await cart.finishAndReset();
    router.push({ name: 'register' });
}

const accountMethod = computed(() => paymentMethods.methods.find((method) => method.kind === 'account') ?? null);
const billingToAccount = ref(false);

async function billToAccount() {
    if (!accountMethod.value || due.value <= 0) {
        return;
    }
    billingToAccount.value = true;
    error.value = null;
    try {
        await cart.addPayment(accountMethod.value, due.value.toFixed(2), null, null);
        const done = await cart.completeSale();
        if (done) {
            router.push({ name: 'confirm' });
        } else if (cart.cart?.lastError) {
            error.value = cart.cart.lastError;
        }
    } finally {
        billingToAccount.value = false;
    }
}

async function complete() {
    error.value = null;
    completing.value = true;
    try {
        const done = await cart.completeSale();
        if (done) {
            router.push({ name: 'confirm' });
        } else if (cart.cart?.lastError) {
            error.value = cart.cart.lastError;
        } else {
            error.value = "Offline — the server is unreachable. This sale is saved on the register and will complete once it reconnects.";
        }
    } finally {
        completing.value = false;
    }
}
</script>

<template>
    <div v-if="cart.cart" class="flex h-full min-h-0 flex-col bg-slate-950">
        <header class="flex min-h-16 items-center justify-between gap-4 border-b border-slate-800 bg-slate-900 px-3 py-2.5 sm:px-4">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-md border border-slate-700 bg-slate-800 text-slate-300 transition hover:bg-slate-700 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="Back to register" title="Back to register" @click="router.push({ name: 'register' })">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" /></svg>
                </button>
                <div class="min-w-0 leading-tight">
                    <h1 class="text-base font-bold text-white sm:text-lg">Take payment</h1>
                    <p class="truncate text-xs text-slate-400">{{ cart.cart.lines.length }} {{ cart.cart.lines.length === 1 ? 'item' : 'items' }} in current sale</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-[11px] font-semibold uppercase text-slate-500">Remaining</p>
                <p class="text-lg font-bold tabular-nums" :class="due > 0 ? 'text-white' : 'text-emerald-400'">{{ formatMoney(due.toFixed(2), cart.cart.currency) }}</p>
            </div>
        </header>

        <div v-if="cart.cart.lastError" class="mx-3 mt-3 flex animate-slide-down items-center justify-between rounded-lg bg-amber-950 border border-amber-900/50 px-4 py-3 shadow-lg sm:mx-4">
            <p class="text-sm font-medium text-amber-300">{{ cart.cart.lastError }}</p>
            <button type="button" class="ml-4 shrink-0 rounded-lg bg-amber-800 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 transition-colors" @click="discard">
                Discard sale
            </button>
        </div>

        <div class="grid min-h-0 flex-1 gap-3 overflow-y-auto p-3 md:grid-cols-[minmax(320px,0.8fr)_minmax(0,1.2fr)] md:overflow-hidden sm:p-4">
            <!-- Order summary -->
            <section class="flex flex-col overflow-y-auto rounded-lg bg-slate-900/60 border border-slate-800/80 p-4 text-white shadow-xl md:p-5">
                <span class="text-sm font-medium text-slate-400">Amount due</span>
                <span class="mt-1 text-3xl font-bold tabular-nums sm:text-4xl" :class="due > 0 ? 'text-white' : 'text-emerald-400'">
                    {{ formatMoney(due.toFixed(2), cart.cart.currency) }}
                </span>

                <button type="button" class="mt-3 flex h-10 items-center justify-between rounded-md border border-slate-700 bg-slate-800 px-3 text-sm font-semibold text-slate-200 md:hidden" :aria-expanded="showOrderDetails" @click="showOrderDetails = !showOrderDetails">
                    {{ showOrderDetails ? 'Hide order details' : 'View order details' }}
                    <svg class="h-4 w-4 transition" :class="showOrderDetails ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" /></svg>
                </button>

                <div :class="showOrderDetails ? 'block' : 'hidden md:block'">

                <div v-if="cart.cart.customer || cart.cart.dinner_table" class="mt-4 space-y-0.5 text-sm">
                    <div v-if="cart.cart.customer" class="flex justify-between text-slate-400">
                        <span>Customer</span>
                        <span class="text-slate-200">{{ cart.cart.customer.company_name || cart.cart.customer.full_name }}</span>
                    </div>
                    <div v-if="cart.cart.dinner_table" class="flex justify-between text-slate-400">
                        <span>Table</span>
                        <span class="text-slate-200">{{ cart.cart.dinner_table.name }}</span>
                    </div>
                </div>

                <!-- Itemised bill -->
                <div class="mt-4 border-t border-slate-700/50 pt-3">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Items</span>
                    <div class="mt-2 space-y-1.5">
                        <div v-for="line in cart.cart.lines" :key="line._tempId" class="flex justify-between gap-3 text-sm">
                            <span class="min-w-0 flex-1 text-slate-200">
                                {{ line.item_name }}
                                <span class="text-slate-500">&nbsp;{{ trimQty(line.quantity) }} &times; {{ formatMoney(line.unit_price, cart.cart.currency) }}</span>
                                <span v-if="line.description" class="block text-xs text-slate-500">{{ line.description }}</span>
                            </span>
                            <span class="shrink-0 font-medium text-white">{{ formatMoney(lineExtended(line).toFixed(2), cart.cart.currency) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Money breakdown -->
                <div class="mt-4 space-y-1 border-t border-slate-700/50 pt-3 text-sm text-slate-400">
                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span>{{ formatMoney(cart.cart.totals.subtotal, cart.cart.currency) }}</span>
                    </div>
                    <div v-if="Number(cart.cart.totals.discount_total) > 0" class="flex justify-between text-emerald-400">
                        <span>Discount</span>
                        <span>-{{ formatMoney(cart.cart.totals.discount_total, cart.cart.currency) }}</span>
                    </div>
                    <div v-if="Number(cart.cart.totals.tax_total) > 0" class="flex justify-between">
                        <span>Tax</span>
                        <span>{{ formatMoney(cart.cart.totals.tax_total, cart.cart.currency) }}</span>
                    </div>
                    <div v-if="Number(cart.cart.totals.rounding_adjustment) !== 0" class="flex justify-between">
                        <span>Rounding</span>
                        <span>{{ formatMoney(cart.cart.totals.rounding_adjustment, cart.cart.currency) }}</span>
                    </div>
                    <div class="flex justify-between pt-1 text-base font-semibold text-white">
                        <span>Sale total</span>
                        <span>{{ formatMoney(cart.cart.totals.total, cart.cart.currency) }}</span>
                    </div>
                    <div v-if="Number(cart.cart.tip_amount) > 0" class="flex justify-between">
                        <span>Tip</span>
                        <span>{{ formatMoney(cart.cart.tip_amount, cart.cart.currency) }}</span>
                    </div>
                    <div v-if="Number(cart.cart.tip_amount) > 0" class="flex justify-between text-base font-semibold text-white">
                        <span>Grand total</span>
                        <span>{{ formatMoney(grandTotal.toFixed(2), cart.cart.currency) }}</span>
                    </div>
                </div>

                <div v-if="cart.cart.payments.length === 0" class="mt-4 text-sm text-slate-500 italic">
                    No payments added yet.
                </div>
                <div v-else class="mt-3 space-y-1.5 border-t border-slate-700/50 pt-3">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payment type</span>
                    <div v-for="payment in cart.cart.payments" :key="payment._tempId" class="flex items-start justify-between rounded-md bg-slate-800 px-3 py-2 text-sm">
                        <span class="min-w-0">
                            <span class="text-slate-200">{{ methodName(payment.method_code) }}</span>
                            <span v-if="payment.reference" class="block text-xs text-slate-500">Ref: {{ payment.reference }}</span>
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="font-medium text-white">{{ formatMoney(payment.amount, cart.cart.currency) }}</span>
                            <button type="button" class="text-red-400 hover:text-red-300" @click="cart.removePayment(payment)" aria-label="Remove payment">✕</button>
                        </span>
                    </div>
                </div>
                <div v-if="changeDue > 0" class="mt-4 flex items-center justify-between rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3">
                    <span class="text-sm font-semibold uppercase tracking-wide text-amber-300">Change due</span>
                    <span class="text-2xl font-bold text-amber-300">{{ formatMoney(changeDue.toFixed(2), cart.cart.currency) }}</span>
                </div>
                </div>
            </section>

            <!-- Tender workflow -->
            <section class="flex min-h-0 flex-col overflow-y-auto rounded-lg bg-slate-900/60 border border-slate-800/80 p-4 shadow-xl md:p-5">
                <!-- Tip and even-split are table-service conveniences: only the
                     restaurant register offers them. -->
                <template v-if="business.isRestaurantMode">
                    <span class="mb-2 text-sm font-semibold tracking-wide text-slate-300 uppercase">Tip</span>
                    <div class="mb-6 flex flex-wrap items-center gap-2">
                        <button
                            v-for="pct in [15, 18, 20]"
                            :key="pct"
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-semibold transition-colors"
                            :class="tipPercent === pct ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-200 hover:bg-slate-700 border border-slate-700'"
                            :disabled="tipBusy"
                            @click="chooseTipPercent(pct)"
                        >
                            {{ pct }}%
                        </button>
                        <input
                            v-model="customTip"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="Custom"
                            class="w-24 rounded-lg bg-slate-800 border border-slate-700 px-2 py-2 text-sm text-white outline-none focus:border-emerald-500"
                            @keyup.enter="applyCustomTip"
                            @blur="onCustomTipBlur"
                        >
                        <button
                            v-if="Number(cart.cart.tip_amount) > 0"
                            type="button"
                            class="rounded-lg px-3 py-2 text-sm text-slate-400 hover:text-white"
                            :disabled="tipBusy"
                            @click="clearTip"
                        >
                            No tip
                        </button>
                    </div>

                    <span class="mb-2 text-sm font-semibold tracking-wide text-slate-300 uppercase">Split bill</span>
                    <div class="mb-6 flex flex-wrap items-center gap-2">
                        <button
                            v-for="n in [2, 3, 4, 5, 6]"
                            :key="n"
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-semibold transition-colors"
                            :class="splitInto === n ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-200 hover:bg-slate-700 border border-slate-700'"
                            @click="chooseSplit(n)"
                        >
                            {{ n }} ways
                        </button>
                        <span v-if="splitInto > 1" class="text-sm text-slate-400">
                            {{ formatMoney(splitShare.toFixed(2), cart.cart.currency) }} each &mdash; one payment per guest below
                        </span>
                        <button v-if="splitInto > 1" type="button" class="rounded-lg px-3 py-2 text-sm text-slate-400 hover:text-white" @click="clearSplit">
                            Cancel split
                        </button>
                    </div>
                </template>

                <span class="mb-3 text-sm font-semibold tracking-wide text-slate-300 uppercase">Payment method</span>
                <div class="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <button
                        v-for="(method, index) in paymentMethods.methods"
                        :key="method.id"
                        type="button"
                        class="relative flex min-h-14 items-center gap-2 rounded-lg px-4 py-3 text-sm font-semibold shadow-sm transition hover:border-emerald-500/60"
                        :class="selectedMethod?.id === method.id ? 'bg-emerald-600 text-white ring-2 ring-emerald-500 shadow-emerald-900/50' : 'bg-slate-800 text-slate-200 hover:bg-slate-700 border border-slate-700'"
                        @click="selectPaymentMethod(method)"
                    >
                        <span class="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-sm bg-black/30 text-[9px] text-white/70">{{ index + 1 }}</span>
                        <svg v-if="method.code === 'cash'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="6" width="20" height="12" rx="2" />
                            <circle cx="12" cy="12" r="2" />
                        </svg>
                        <svg v-else-if="method.code === 'card'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="5" width="20" height="14" rx="2" />
                            <path stroke-linecap="round" d="M2 10h20" />
                        </svg>
                        <svg v-else-if="method.code === 'check'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7V3z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5" />
                        </svg>
                        <svg v-else-if="method.code === 'giftcard'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="8" width="20" height="12" rx="1" />
                            <path stroke-linecap="round" d="M2 8h20v3H2z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v12M12 8c-1.5-3-5-3-5 0s3.5 2.5 5 0zM12 8c1.5-3 5-3 5 0s-3.5 2.5-5 0z" />
                        </svg>
                        <svg v-else-if="method.code === 'points'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 2l2.9 6.1 6.6.7-4.9 4.5 1.3 6.5L12 16.8 5.9 19.8l1.3-6.5-4.9-4.5 6.6-.7L12 2z" />
                        </svg>
                        <svg v-else-if="method.kind === 'account'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="8" r="4" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 20c0-4 3.5-6 8-6s8 2 8 6" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="9" />
                            <path stroke-linecap="round" d="M12 7v10M9 9.5h4.5a1.5 1.5 0 010 3H9m0 0h4.5a1.5 1.5 0 010 3H9" />
                        </svg>
                        {{ method.name }}
                    </button>
                </div>

                <label class="mb-2 mt-2 text-sm font-semibold tracking-wide text-slate-300 uppercase">Amount tendered</label>
                <input
                    ref="tenderedInput"
                    v-model="tendered"
                    type="number"
                    step="0.01"
                    min="0"
                    :placeholder="`Due ${due.toFixed(2)}`"
                    class="h-14 w-full rounded-lg bg-slate-950 border border-slate-700 px-4 text-2xl font-bold text-white shadow-inner outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 transition placeholder-slate-600"
                    @keyup.enter="handleTenderedEnter"
                >
                <div v-if="quickAmounts.length" class="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="min-h-10 rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-200 hover:bg-slate-700"
                        @click="tendered = due.toFixed(2)"
                    >
                        Exact ({{ formatMoney(due.toFixed(2), cart.cart.currency) }})
                    </button>
                    <button
                        v-for="amount in quickAmounts"
                        :key="amount"
                        type="button"
                        class="min-h-10 rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-200 hover:bg-slate-700"
                        @click="tendered = amount.toFixed(2)"
                    >
                        {{ formatMoney(amount.toFixed(2), cart.cart.currency) }}
                    </button>
                </div>
                <p v-if="changeDue > 0" class="mt-3 text-lg font-bold text-amber-300">
                    Change due: {{ formatMoney(changeDue.toFixed(2), cart.cart.currency) }}
                </p>

                <div v-if="showReference" class="mt-3">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">
                        {{ referenceLabel }} <span class="text-red-400">*</span>
                    </label>
                    <div class="flex gap-2">
                    <input
                        v-model="reference"
                        type="text"
                        :placeholder="referenceLabel"
                        class="flex-1 rounded-lg bg-slate-800 px-3 py-2 text-white outline-none focus:ring-2 focus:ring-emerald-500"
                        :class="referenceMissing ? 'ring-1 ring-red-500/60' : ''"
                    >
                    <button
                        v-if="selectedMethod?.code === 'giftcard'"
                        type="button"
                        class="rounded-lg bg-slate-700 px-4 py-2 text-white disabled:opacity-50"
                        :disabled="giftcardChecking || !reference.trim()"
                        @click="checkGiftcardBalance"
                    >
                        {{ giftcardChecking ? 'Checking…' : 'Check balance' }}
                    </button>
                    </div>
                </div>
                <p v-if="giftcardError" class="mt-2 text-sm text-red-400">{{ giftcardError }}</p>
                <p v-if="giftcardBalance" class="mt-2 text-sm text-slate-300">
                    Balance: {{ formatMoney(giftcardBalance.balance, cart.cart.currency) }}
                    <span v-if="!giftcardBalance.is_active" class="text-red-400">(inactive)</span>
                    <span v-else-if="giftcardBalance.expires_at && new Date(giftcardBalance.expires_at) < new Date()" class="text-red-400">(expired)</span>
                </p>

                <button type="button" class="mt-5 flex min-h-13 w-full items-center justify-center gap-2 rounded-lg bg-emerald-500 px-4 font-bold text-slate-950 shadow transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-40" :disabled="paymentBusy || !selectedMethod || due <= 0 || referenceMissing" @click="addPayment">
                    <svg v-if="paymentBusy" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" /></svg>
                    {{ paymentBusy ? 'Adding payment...' : 'Add payment' }} <kbd v-if="!paymentBusy" class="rounded bg-slate-950/15 px-1.5 py-0.5 text-[11px]">Enter</kbd>
                </button>
            </section>
        </div>

        <p v-if="error" role="alert" class="mx-3 mb-3 rounded-md border border-red-500/30 bg-red-500/10 px-3 py-2.5 text-sm text-red-200 sm:mx-4">{{ error }}</p>

        <div v-if="due <= 0 || (business.isHardwareMode && accountMethod && due > 0)" class="flex flex-col gap-2 border-t border-slate-800 bg-slate-900 px-3 py-3 sm:flex-row sm:px-4">
            <button
                v-if="business.isHardwareMode && accountMethod && due > 0"
                type="button"
                class="min-h-13 w-full rounded-lg border border-slate-700 bg-slate-800 px-5 text-base font-bold text-slate-200 disabled:opacity-40 hover:bg-slate-700 transition sm:w-auto"
                :disabled="billingToAccount"
                @click="billToAccount"
            >
                {{ billingToAccount ? 'Billing…' : `Bill ${formatMoney(due.toFixed(2), cart.cart.currency)} to account` }}
            </button>

            <button
                type="button"
                class="min-h-13 w-full items-center justify-center gap-2 rounded-lg bg-emerald-500 px-6 text-lg font-bold text-slate-950 shadow hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-40 sm:ml-auto sm:flex sm:max-w-md"
                :class="due > 0 ? 'hidden' : 'flex'"
                :disabled="completing || due > 0"
                @click="complete"
            >
                {{ completing ? 'Completing…' : 'Complete sale' }}
            </button>
        </div>
    </div>
</template>
