<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { ApiError } from '../api/client.js';

const auth = useAuthStore();
const router = useRouter();
const username = ref('');
const password = ref('');
const error = ref(null);
const loading = ref(false);
const showPassword = ref(false);
const brandIcon = '/icons/icon-192.png';
const appName = import.meta.env.VITE_APP_NAME;

async function submit() {
    error.value = null;
    loading.value = true;
    try {
        await auth.login(username.value, password.value);
        router.push({ name: 'setup' });
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Unable to reach the server.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <main class="relative flex min-h-full items-center justify-center overflow-hidden bg-slate-950 px-4 py-8 sm:px-6">
        <div class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></div>
        <div class="w-full max-w-md">
            <div class="mb-7 flex items-center justify-center gap-3">
                <img :src="brandIcon" alt="" class="h-11 w-11 rounded-lg shadow-lg">
                <div class="leading-tight">
                    <p class="text-lg font-bold text-white">{{ appName }}</p>
                    <p class="text-xs font-medium uppercase text-slate-500">Point of sale</p>
                </div>
            </div>

            <form class="rounded-lg border border-slate-800 bg-slate-900 p-5 shadow-2xl sm:p-7" @submit.prevent="submit">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-white">Sign in to register</h1>
                    <p class="mt-1 text-sm text-slate-400">Use your staff account to continue.</p>
                </div>

                <div>
                    <label for="pos-username" class="mb-1.5 block text-sm font-semibold text-slate-200">Username</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M20 21a8 8 0 00-16 0m8-10a4 4 0 100-8 4 4 0 000 8z"/></svg>
                        <input
                            id="pos-username"
                            v-model.trim="username"
                            type="text"
                            autocomplete="username"
                            autocapitalize="none"
                            spellcheck="false"
                            required
                            autofocus
                            class="h-12 w-full rounded-md border border-slate-700 bg-slate-950 pl-10 pr-3 text-base text-white outline-none transition placeholder:text-slate-600 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"
                        >
                    </div>
                </div>
                <div class="mt-4">
                    <label for="pos-password" class="mb-1.5 block text-sm font-semibold text-slate-200">Password</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M7 10V7a5 5 0 0110 0v3m-11 0h12v10H6V10z"/></svg>
                        <input
                            id="pos-password"
                            v-model="password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            required
                            class="h-12 w-full rounded-md border border-slate-700 bg-slate-950 pl-10 pr-12 text-base text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"
                        >
                        <button type="button" class="absolute right-1.5 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-md text-slate-400 hover:bg-slate-800 hover:text-white" :aria-label="showPassword ? 'Hide password' : 'Show password'" :title="showPassword ? 'Hide password' : 'Show password'" @click="showPassword = !showPassword">
                            <svg v-if="!showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12zm10-2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.2A11 11 0 0112 5c6.5 0 10 7 10 7a18 18 0 01-2.1 3.1M6.6 6.6C3.6 8.4 2 12 2 12s3.5 7 10 7a10 10 0 004.1-.9"/></svg>
                        </button>
                    </div>
                </div>

                <div v-if="error" role="alert" class="mt-4 flex items-start gap-2 rounded-md border border-red-500/30 bg-red-500/10 px-3 py-2.5 text-sm text-red-200">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 9v3m0 4h.01M4 20h16L12 4 4 20z"/></svg>
                    <span>{{ error }}</span>
                </div>

                <button
                    type="submit"
                    :disabled="loading || !username || !password"
                    class="mt-6 inline-flex h-12 w-full items-center justify-center gap-2 rounded-md bg-emerald-500 px-4 text-base font-bold text-slate-950 shadow-lg transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg v-if="loading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                    {{ loading ? 'Signing in…' : 'Sign in' }}
                </button>
            </form>

            <p class="mt-5 text-center text-xs text-slate-600">Register access is recorded against your staff account.</p>
        </div>
    </main>
</template>
