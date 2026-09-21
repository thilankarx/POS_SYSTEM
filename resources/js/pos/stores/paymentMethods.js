import { defineStore } from 'pinia';
import { apiFetch } from '../api/client.js';
import { getItem, setItem } from './storage.js';

function readCached() {
    const raw = getItem('payment_methods');
    return raw ? JSON.parse(raw) : [];
}

export const usePaymentMethodsStore = defineStore('paymentMethods', {
    state: () => ({
        methods: readCached(),
    }),
    actions: {
        // Best-effort refresh: on failure (offline, most commonly) this
        // silently keeps whatever was cached from the last time it
        // succeeded, which is what lets the Payment screen still offer
        // tender options while offline instead of coming up empty.
        async refresh() {
            try {
                const res = await apiFetch('/payment-methods');
                this.methods = res.data;
                setItem('payment_methods', JSON.stringify(res.data));
            } catch {
                // keep the cache
            }
        },
    },
});
