<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { useTerminalStore } from '../stores/terminal.js';
import { apiFetch, ApiError } from '../api/client.js';

const router = useRouter();
const authStore = useAuthStore();
const terminalStore = useTerminalStore();
const terminals = ref([]);
const loading = ref(true);
const error = ref(null);
const choosingId = ref(null);
const brandIcon = '/icons/icon-192.png';
const appName = import.meta.env.VITE_APP_NAME;

async function loadTerminals() {
    loading.value = true;
    error.value = null;

    try {
        const res = await apiFetch('/terminals');
        terminals.value = res.data;
    } catch (e) {
        error.value = e instanceof ApiError
            ? e.message
            : 'Unable to load terminals. Check the connection and try again.';
    } finally {
        loading.value = false;
    }
}

function choose(terminal) {
    choosingId.value = terminal.id;
    terminalStore.select(terminal);
    router.push({ name: 'register' });
}

function signOut() {
    terminalStore.clear();
    authStore.logout();
    router.replace({ name: 'login' });
}

onMounted(loadTerminals);
</script>

<template>
    <main class="relative flex min-h-full flex-col overflow-auto bg-slate-950">
        <div class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></div>

        <header class="flex min-h-16 items-center justify-between gap-4 border-b border-slate-800 bg-slate-900/80 px-4 py-3 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <img :src="brandIcon" alt="" class="h-9 w-9 shrink-0 rounded-md">
                <div class="min-w-0 leading-tight">
                    <p class="truncate text-sm font-bold text-white">{{ appName }}</p>
                    <p class="text-xs text-slate-500">Terminal setup</p>
                </div>
            </div>
            <div class="flex min-w-0 items-center gap-3">
                <p class="hidden max-w-48 truncate text-sm text-slate-400 sm:block">{{ authStore.user?.name }}</p>
                <button type="button" class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-700 bg-slate-800 px-3 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-700 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" @click="signOut">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 6v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1" /></svg>
                    <span class="hidden sm:inline">Sign out</span>
                </button>
            </div>
        </header>

        <section class="mx-auto flex w-full max-w-5xl flex-1 flex-col px-4 py-8 sm:px-6 sm:py-12">
            <div class="mb-7 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="mb-1 text-xs font-bold uppercase text-emerald-400">Workstation</p>
                    <h1 class="text-2xl font-bold text-white sm:text-3xl">Choose a terminal</h1>
                    <p class="mt-2 max-w-xl text-sm text-slate-400">Select the register and stock location you are working from.</p>
                </div>
                <span v-if="!loading && !error && terminals.length" class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-400 ring-1 ring-slate-800">
                    {{ terminals.length }} {{ terminals.length === 1 ? 'terminal' : 'terminals' }} available
                </span>
            </div>

            <div v-if="loading" class="grid gap-3 md:grid-cols-2" aria-label="Loading terminals" aria-live="polite">
                <div v-for="index in 4" :key="index" class="h-32 animate-pulse rounded-lg border border-slate-800 bg-slate-900"></div>
            </div>

            <div v-else-if="error" role="alert" class="flex flex-col items-start gap-4 rounded-lg border border-red-500/30 bg-red-500/10 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 9v3m0 4h.01M4 20h16L12 4 4 20z" /></svg>
                    <div>
                        <p class="font-semibold text-red-100">Terminals could not be loaded</p>
                        <p class="mt-1 text-sm text-red-200/80">{{ error }}</p>
                    </div>
                </div>
                <button type="button" class="h-10 shrink-0 rounded-md bg-red-400 px-4 text-sm font-bold text-slate-950 hover:bg-red-300" @click="loadTerminals">Try again</button>
            </div>

            <div v-else-if="terminals.length === 0" class="grid min-h-64 place-items-center rounded-lg border border-dashed border-slate-700 bg-slate-900/40 px-6 text-center">
                <div>
                    <svg class="mx-auto h-10 w-10 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4zM8 18v2m8-2v2M8 10h8m-8 4h5" /></svg>
                    <h2 class="mt-3 font-semibold text-slate-200">No terminals available</h2>
                    <p class="mt-1 text-sm text-slate-500">Ask an administrator to assign a terminal to your account.</p>
                </div>
            </div>

            <div v-else class="grid gap-3 md:grid-cols-2">
                <button v-for="terminal in terminals" :key="terminal.id" type="button" :disabled="choosingId !== null" class="group flex min-h-32 items-center gap-4 rounded-lg border border-slate-800 bg-slate-900 p-4 text-left shadow-lg transition hover:border-emerald-500/50 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:cursor-wait disabled:opacity-60 sm:p-5" @click="choose(terminal)">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-md bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/20 transition group-hover:bg-emerald-500 group-hover:text-slate-950">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v11H4zM8 20h8m-6-4v4m4-4v4M8 9h8m-8 3h5" /></svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-start justify-between gap-2">
                            <span class="truncate text-base font-bold text-white">{{ terminal.name }}</span>
                            <svg v-if="choosingId === terminal.id" class="h-4 w-4 shrink-0 animate-spin text-emerald-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" /></svg>
                            <svg v-else class="h-4 w-4 shrink-0 text-slate-600 transition group-hover:translate-x-0.5 group-hover:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                        </span>
                        <span class="mt-1 block truncate text-sm text-slate-400">{{ terminal.stock_location_name }}</span>
                        <span class="mt-3 flex flex-wrap gap-2">
                            <span class="rounded bg-slate-950 px-2 py-1 text-[11px] font-semibold text-slate-400">{{ terminal.code }}</span>
                            <span class="rounded px-2 py-1 text-[11px] font-semibold" :class="terminal.shift_open ? 'bg-emerald-500/10 text-emerald-300' : 'bg-amber-500/10 text-amber-300'">{{ terminal.shift_open ? 'Shift open' : 'No open shift' }}</span>
                            <span v-if="terminal.has_printer" class="rounded bg-sky-500/10 px-2 py-1 text-[11px] font-semibold text-sky-300">Printer ready</span>
                        </span>
                    </span>
                </button>
            </div>
        </section>
    </main>
</template>
