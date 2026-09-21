<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useCartStore } from '../stores/cart.js';
import { useTerminalStore } from '../stores/terminal.js';
import { formatMoney } from '../lib/money.js';
import { downloadReceipt } from '../lib/downloadReceipt.js';
import { apiFetch, ApiError } from '../api/client.js';

const router = useRouter();
const cart = useCartStore();
const terminalStore = useTerminalStore();
const sale = cart.cart?.completedSale ?? null;
const isQuote = sale?.sale_type === 'quote';
const grandTotal = sale ? (Number(sale.total) + Number(sale.tip_amount || 0)).toFixed(2) : null;
const downloadError = ref('');
const downloading = ref(false);
const downloadSuccess = ref(false);
const printError = ref('');
const printing = ref(false);
const printSuccess = ref(false);

const itemCount = sale?.lines?.reduce((sum, line) => sum + Number(line.quantity || 0), 0) ?? 0;

function paymentMethodName(code) {
    return ({ cash: 'Cash', card: 'Card', check: 'Check', giftcard: 'Gift card', points: 'Loyalty points', account: 'On account' })[code] ?? code ?? 'Payment';
}

function completedAt(value) {
    if (!value) return null;
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function onKeydown(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        newSale();
    } else if (event.key.toLowerCase() === 'p' && terminalStore.terminal?.has_printer && !printing.value) {
        event.preventDefault();
        onPrintReceipt();
    } else if (event.key.toLowerCase() === 'd' && !downloading.value) {
        event.preventDefault();
        onDownloadReceipt();
    }
}

onMounted(() => {
    if (!sale) {
        router.replace({ name: 'register' });
        return;
    }
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
});

async function newSale() {
    await cart.finishAndReset();
    router.push({ name: 'register' });
}

async function onDownloadReceipt() {
    downloadError.value = '';
    downloadSuccess.value = false;
    downloading.value = true;
    try {
        await downloadReceipt(sale.id);
        downloadSuccess.value = true;
    } catch {
        downloadError.value = 'Could not download the receipt. Check your connection and try again.';
    } finally {
        downloading.value = false;
    }
}

async function onPrintReceipt() {
    printError.value = '';
    printSuccess.value = false;
    printing.value = true;
    try {
        await apiFetch(`/sales/${sale.id}/print-receipt`, { method: 'POST' });
        printSuccess.value = true;
    } catch (error) {
        printError.value = error instanceof ApiError
            ? error.message
            : 'Printer unavailable — receipt can still be downloaded.';
    } finally {
        printing.value = false;
    }
}
</script>

<template>
    <div v-if="sale" class="h-full overflow-y-auto bg-slate-950 p-3 sm:p-5 lg:p-6">
        <main class="animate-slide-down mx-auto grid min-h-full w-full max-w-5xl gap-4 lg:grid-cols-[minmax(0,0.9fr)_minmax(360px,1.1fr)] lg:items-stretch">
            <section class="flex flex-col justify-center rounded-lg border border-slate-800 bg-slate-900 p-5 text-center shadow-xl sm:p-7">
                <div class="mx-auto grid h-16 w-16 place-items-center rounded-lg bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/30">
                    <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </div>
                <p class="mt-5 text-xs font-bold uppercase text-emerald-400">{{ isQuote ? 'Quote ready' : 'Payment accepted' }}</p>
                <h1 class="mt-1 text-2xl font-bold text-white sm:text-3xl">{{ isQuote ? 'Quote saved' : 'Sale complete' }}</h1>
                <p class="mt-4 text-4xl font-extrabold tabular-nums text-white sm:text-5xl">{{ formatMoney(grandTotal, sale.currency) }}</p>
                <p v-if="Number(sale.tip_amount) > 0" class="mt-1 text-sm text-slate-400">Includes {{ formatMoney(sale.tip_amount, sale.currency) }} tip</p>

                <div v-if="!isQuote && Number(sale.change_given) > 0" class="mt-5 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-4">
                    <p class="text-xs font-bold uppercase text-amber-300">Change to customer</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-amber-200">{{ formatMoney(sale.change_given, sale.currency) }}</p>
                </div>
                <div v-else-if="!isQuote" class="mt-5 flex items-center justify-center gap-2 text-sm font-medium text-slate-400">
                    <svg class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    No change due
                </div>

                <button type="button" class="group relative mt-7 flex min-h-13 w-full items-center justify-center gap-2 rounded-lg bg-emerald-500 px-5 text-lg font-bold text-slate-950 shadow transition hover:bg-emerald-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400" @click="newSale">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    New sale
                    <kbd class="absolute right-4 rounded bg-slate-950/15 px-1.5 py-0.5 text-[11px]">Enter</kbd>
                </button>
            </section>

            <section class="flex min-h-0 flex-col overflow-hidden rounded-lg border border-slate-800 bg-slate-900 shadow-xl">
                <header class="flex items-start justify-between gap-4 border-b border-slate-800 px-4 py-4 sm:px-5">
                    <div>
                        <p class="text-xs font-semibold uppercase text-slate-500">{{ isQuote ? 'Quote' : 'Receipt' }}</p>
                        <h2 class="mt-1 font-mono text-base font-bold text-white">{{ isQuote ? sale.quote_number : sale.number }}</h2>
                        <p v-if="completedAt(sale.sold_at)" class="mt-1 text-xs text-slate-500">{{ completedAt(sale.sold_at) }}</p>
                    </div>
                    <span class="rounded-md bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-300">Completed</span>
                </header>

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                    <div class="flex items-center justify-between text-sm"><span class="font-semibold text-slate-200">Items</span><span class="text-slate-500">{{ itemCount }} {{ itemCount === 1 ? 'unit' : 'units' }}</span></div>
                    <div class="mt-3 divide-y divide-slate-800">
                        <div v-for="line in sale.lines" :key="line.id" class="flex items-start justify-between gap-4 py-2.5 first:pt-0">
                            <div class="min-w-0"><p class="truncate text-sm font-medium text-slate-200">{{ line.item_name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ Number(line.quantity) }} &times; {{ formatMoney(line.unit_price, sale.currency) }}</p></div>
                            <span class="shrink-0 text-sm font-semibold tabular-nums text-white">{{ formatMoney(line.line_total, sale.currency) }}</span>
                        </div>
                    </div>

                    <dl class="mt-3 space-y-2 border-t border-slate-800 pt-3 text-sm">
                        <div class="flex justify-between gap-4 text-slate-400"><dt>Subtotal</dt><dd class="tabular-nums">{{ formatMoney(sale.subtotal, sale.currency) }}</dd></div>
                        <div v-if="Number(sale.discount_total) > 0" class="flex justify-between gap-4 text-emerald-300"><dt>Discount</dt><dd class="tabular-nums">-{{ formatMoney(sale.discount_total, sale.currency) }}</dd></div>
                        <div v-if="Number(sale.tax_total) > 0" class="flex justify-between gap-4 text-slate-400"><dt>Tax</dt><dd class="tabular-nums">{{ formatMoney(sale.tax_total, sale.currency) }}</dd></div>
                        <div v-if="Number(sale.tip_amount) > 0" class="flex justify-between gap-4 text-slate-400"><dt>Tip</dt><dd class="tabular-nums">{{ formatMoney(sale.tip_amount, sale.currency) }}</dd></div>
                        <div class="flex justify-between gap-4 pt-1 text-base font-bold text-white"><dt>Total</dt><dd class="tabular-nums">{{ formatMoney(grandTotal, sale.currency) }}</dd></div>
                    </dl>

                    <div v-if="sale.payments?.length" class="mt-4 border-t border-slate-800 pt-3">
                        <p class="text-xs font-semibold uppercase text-slate-500">Payments</p>
                        <div v-for="(payment, index) in sale.payments" :key="`${payment.method_code}-${index}`" class="mt-2 flex justify-between gap-4 text-sm"><span class="text-slate-300">{{ paymentMethodName(payment.method_code) }}</span><span class="font-semibold tabular-nums text-white">{{ formatMoney(payment.amount, sale.currency) }}</span></div>
                    </div>
                </div>

                <footer class="border-t border-slate-800 p-4 sm:p-5">
                    <div class="grid gap-2" :class="terminalStore.terminal?.has_printer ? 'sm:grid-cols-2' : ''">
                        <button v-if="terminalStore.terminal?.has_printer" type="button" class="relative flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-700 bg-slate-800 px-4 text-sm font-semibold text-slate-200 transition hover:bg-slate-700 hover:text-white disabled:opacity-50" :disabled="printing" @click="onPrintReceipt">
                            <svg v-if="printing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" /></svg>
                            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 17H4V9h16v8h-2m-12-4h12v8H6v-8z" /></svg>
                            {{ printing ? 'Printing...' : (printSuccess ? 'Printed' : 'Print receipt') }}
                            <kbd v-if="!printing" class="absolute right-3 rounded bg-slate-900 px-1.5 py-0.5 text-[10px] text-slate-400">P</kbd>
                        </button>
                        <button type="button" class="relative flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-700 bg-slate-800 px-4 text-sm font-semibold text-slate-200 transition hover:bg-slate-700 hover:text-white disabled:opacity-50" :disabled="downloading" @click="onDownloadReceipt">
                            <svg v-if="downloading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" /></svg>
                            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M5 19h14" /></svg>
                            {{ downloading ? 'Downloading...' : (downloadSuccess ? 'Downloaded' : 'Download PDF') }}
                            <kbd v-if="!downloading" class="absolute right-3 rounded bg-slate-900 px-1.5 py-0.5 text-[10px] text-slate-400">D</kbd>
                        </button>
                    </div>
                    <div v-if="printError || downloadError" role="alert" class="mt-3 rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-200">{{ printError || downloadError }}</div>
                </footer>
            </section>
        </main>
    </div>
</template>
