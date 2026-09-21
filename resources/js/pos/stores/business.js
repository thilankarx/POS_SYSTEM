import { defineStore } from 'pinia';
import { getItem, setItem } from './storage.js';
import { apiFetch } from '../api/client.js';

function readBusinessType() {
    return getItem('business_type') || 'retail';
}

export const useBusinessStore = defineStore('business', {
    state: () => ({
        businessType: readBusinessType(),
    }),
    getters: {
        isHardwareMode: (state) => state.businessType === 'hardware',
        isRestaurantMode: (state) => state.businessType === 'restaurant',
    },
    actions: {
        async refresh() {
            try {
                const res = await apiFetch('/config');
                this.businessType = res.business_type;
                setItem('business_type', res.business_type);
            } catch {
                // Offline or unauthenticated — keep whatever was cached locally
                // (defaults to 'retail' if nothing has ever been fetched).
            }
        },
    },
});
