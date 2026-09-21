import { defineStore } from 'pinia';
import { apiFetch } from '../api/client.js';
import { getItem, setItem, removeItem } from './storage.js';
import { usePaymentMethodsStore } from './paymentMethods.js';
import { useCatalogStore } from './catalog.js';
import { useBusinessStore } from './business.js';
import { useCategoriesStore } from './categories.js';
import { useCartStore } from './cart.js';

function readUser() {
    const raw = getItem('user');
    return raw ? JSON.parse(raw) : null;
}

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: getItem('token'),
        user: readUser(),
    }),
    getters: {
        isAuthenticated: (state) => Boolean(state.token),
    },
    actions: {
        async login(username, password) {
            const data = await apiFetch('/login', {
                method: 'POST',
                body: { username, password, device_name: 'register-pwa' },
            });
            this.token = data.token;
            this.user = data.user;
            setItem('token', data.token);
            setItem('user', JSON.stringify(data.user));
            // Prime the payment-methods and catalog caches now, while we
            // know we're both online and authenticated — app boot alone
            // can't do this, since it runs before login and would just get
            // a 401.
            usePaymentMethodsStore().refresh();
            useCatalogStore().refresh();
            useCatalogStore().refreshKits();
            useBusinessStore().refresh();
            useCategoriesStore().refresh();
            // A prior token expiring mid-shift now leaves the offline queue
            // intact rather than wiping it (see client.js's 401 handling) --
            // draining it here means a fresh login recovers automatically
            // instead of waiting on the next network online/offline
            // transition to trigger a retry.
            useCartStore().sync();
        },
        logout() {
            this.token = null;
            this.user = null;
            removeItem('token');
            removeItem('user');
        },
    },
});
