import { defineStore } from 'pinia';
import { apiFetch, ApiError } from '../api/client.js';

export const useTablesStore = defineStore('tables', {
    state: () => ({
        tables: [],
        loading: false,
    }),
    actions: {
        async fetchForLocation(stockLocationId) {
            this.loading = true;
            try {
                const res = await apiFetch(`/dinner-tables?stock_location_id=${stockLocationId}`);
                this.tables = res.data;
            } catch {
                // Offline or the request failed -- keep whatever was already
                // loaded rather than blanking the floor view.
            } finally {
                this.loading = false;
            }
        },

        // Returns the suspended cart parked against this table, or null if
        // none exists (the table is available, or its order is still active
        // on whichever terminal opened it).
        async findOpenCart(tableId) {
            try {
                const res = await apiFetch(`/dinner-tables/${tableId}/cart`);
                return res.data;
            } catch (e) {
                if (e instanceof ApiError && e.status === 404) {
                    return null;
                }
                throw e;
            }
        },
    },
});
