<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { useKitchenStore } from '../stores/kitchen.js';
import { useTerminalStore } from '../stores/terminal.js';
import { useAuthStore } from '../stores/auth.js';

const router = useRouter();
const kitchen = useKitchenStore();
const terminalStore = useTerminalStore();
const authStore = useAuthStore();

const now = ref(Date.now());
const preparingIds = ref(new Set());

function minutesAgo(isoString) {
    const minutes = Math.floor((now.value - new Date(isoString).getTime()) / 60000);

    return minutes <= 0 ? 'just now' : `${minutes} min ago`;
}

async function markPrepared(lineId) {
    preparingIds.value.add(lineId);
    try {
        await kitchen.prepareLine(lineId);
    } finally {
        preparingIds.value.delete(lineId);
    }
}

function backToRegister() {
    router.push({ name: 'register' });
}

// A kitchen-only login (no sales.create ability) never has a register to
// go back to -- give it a way to sign out instead, same as Register.vue's
// own signOut().
function signOut() {
    authStore.logout();
    router.push({ name: 'login' });
}

let pollTimer = null;
let clockTimer = null;

onMounted(() => {
    const locationId = terminalStore.terminal?.stock_location_id;
    if (locationId) {
        kitchen.fetchTickets(locationId);
        pollTimer = setInterval(() => kitchen.fetchTickets(locationId), 8000);
    }
    clockTimer = setInterval(() => { now.value = Date.now(); }, 30000);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
    clearInterval(clockTimer);
});
</script>

<template>
    <div class="flex h-full flex-col bg-slate-950">
        <header class="flex items-center justify-between bg-slate-900 px-4 py-3 shadow-md border-b border-slate-800">
            <h1 class="text-lg font-semibold text-white">Kitchen</h1>
            <button
                v-if="authStore.user?.can_operate_register"
                type="button"
                class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-700 transition-colors"
                @click="backToRegister"
            >
                Back to register
            </button>
            <button
                v-else
                type="button"
                class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-700 transition-colors"
                @click="signOut"
            >
                Log out
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-4">
            <p v-if="kitchen.tickets.length === 0" class="mt-12 text-center text-slate-500">
                No tickets waiting.
            </p>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <div
                    v-for="ticket in kitchen.tickets"
                    :key="ticket.cart_id"
                    class="rounded-xl bg-slate-800/80 border border-slate-700/50 p-4 shadow-sm"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <span class="font-semibold text-white">{{ ticket.table?.name ?? 'Order' }}</span>
                        <span class="text-xs text-slate-400">{{ minutesAgo(ticket.sent_at) }}</span>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="line in ticket.lines"
                            :key="line.id"
                            class="flex items-center justify-between gap-2 rounded-lg bg-slate-900/60 px-3 py-2"
                        >
                            <div>
                                <div class="text-sm font-medium text-white">{{ line.quantity }} x {{ line.item_name }}</div>
                                <div v-if="line.description" class="text-xs text-slate-400">{{ line.description }}</div>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-md bg-emerald-700 px-3 py-1.5 text-xs font-semibold uppercase text-white hover:bg-emerald-600 disabled:opacity-50 transition-colors"
                                :disabled="preparingIds.has(line.id)"
                                @click="markPrepared(line.id)"
                            >
                                Done
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
