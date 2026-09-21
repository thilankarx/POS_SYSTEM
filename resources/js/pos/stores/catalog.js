import { defineStore } from 'pinia';
import { apiFetch } from '../api/client.js';
import { getItem, setItem } from './storage.js';

function readCached() {
    const raw = getItem('catalog');
    return raw ? JSON.parse(raw) : [];
}

function readCachedKits() {
    const raw = getItem('catalogKits');
    return raw ? JSON.parse(raw) : [];
}

function readTerminalStockLocationId() {
    const raw = getItem('terminal');
    if (!raw) {
        return null;
    }
    try {
        return JSON.parse(raw).stock_location_id ?? null;
    } catch {
        return null;
    }
}

export const useCatalogStore = defineStore('catalog', {
    state: () => ({
        items: readCached(),
        kits: readCachedKits(),
    }),
    getters: {
        byBarcode: (state) => (barcode) => state.items.find((item) => (item.barcodes ?? []).includes(barcode)),
        search: (state) => (term) => {
            const needle = term.toLowerCase();
            return state.items.filter(
                (item) => item.name.toLowerCase().includes(needle) || item.sku.toLowerCase().includes(needle),
            );
        },
        searchKits: (state) => (term) => {
            const needle = term.toLowerCase();
            return state.kits.filter(
                (kit) => kit.name.toLowerCase().includes(needle) || kit.kit_number.toLowerCase().includes(needle),
            );
        },
    },
    actions: {
        // Best-effort, same pattern as paymentMethods: on failure (offline,
        // most commonly) this silently keeps whatever was cached from the
        // last successful refresh — which is what lets barcode scans and
        // search still resolve to real items while offline.
        async refresh() {
            try {
                const stockLocationId = readTerminalStockLocationId();
                const locationParam = stockLocationId ? `&stock_location_id=${stockLocationId}` : '';
                let page = 1;
                let all = [];
                for (;;) {
                    const res = await apiFetch(`/items?per_page=100&page=${page}${locationParam}`);
                    all = all.concat(res.data);
                    if (!res.meta || page >= res.meta.last_page) {
                        break;
                    }
                    page += 1;
                }
                this.items = all;
                setItem('catalog', JSON.stringify(all));
            } catch {
                // keep the cache
            }
        },

        // Same best-effort/keep-the-cache shape as refresh() above.
        async refreshKits() {
            try {
                let page = 1;
                let all = [];
                for (;;) {
                    const res = await apiFetch(`/item-kits?per_page=100&page=${page}`);
                    all = all.concat(res.data);
                    if (!res.meta || page >= res.meta.last_page) {
                        break;
                    }
                    page += 1;
                }
                this.kits = all;
                setItem('catalogKits', JSON.stringify(all));
            } catch {
                // keep the cache
            }
        },
    },
});
