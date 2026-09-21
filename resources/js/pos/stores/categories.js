import { defineStore } from 'pinia';
import { apiFetch } from '../api/client.js';
import { getItem, setItem } from './storage.js';

function readCached() {
    const raw = getItem('categories');
    return raw ? JSON.parse(raw) : [];
}

export const useCategoriesStore = defineStore('categories', {
    state: () => ({
        categories: readCached(),
    }),
    actions: {
        // Best-effort, same pattern as catalog/paymentMethods: on failure
        // (offline, most commonly) this silently keeps whatever was cached
        // from the last successful refresh.
        async refresh() {
            try {
                const res = await apiFetch('/categories');
                this.categories = res.data;
                setItem('categories', JSON.stringify(res.data));
            } catch {
                // keep the cache
            }
        },
    },
});
