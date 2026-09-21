<script setup>
import { onMounted } from 'vue';
import { RouterView } from 'vue-router';
import OfflineBanner from './components/OfflineBanner.vue';
import { useConnectivityStore } from './stores/connectivity.js';
import { usePaymentMethodsStore } from './stores/paymentMethods.js';
import { useCatalogStore } from './stores/catalog.js';
import { useCartStore } from './stores/cart.js';
import { useBusinessStore } from './stores/business.js';
import { useCategoriesStore } from './stores/categories.js';

const connectivity = useConnectivityStore();
const paymentMethods = usePaymentMethodsStore();
const catalog = useCatalogStore();
const cart = useCartStore();
const business = useBusinessStore();
const categories = useCategoriesStore();

// Always drain the queue through the cart store's own `sync()`, never the
// raw engine `processQueue()` directly — `sync()` is what reloads the
// store's in-memory `cart` from IndexedDB afterward. Skipping that step
// leaves the store holding a stale object (e.g. still `id: null` after a
// background drain has already resolved the real server id), and the next
// store-driven mutation would then blindly re-persist that stale object,
// clobbering what the drain just wrote.
function onReconnect() {
    cart.sync();
    paymentMethods.refresh();
    catalog.refresh();
    catalog.refreshKits();
    business.refresh();
    categories.refresh();
}

onMounted(() => {
    connectivity.init(onReconnect);
    if (connectivity.online) {
        onReconnect();
    }
});
</script>

<template>
    <div class="flex h-full flex-col">
        <OfflineBanner />
        <RouterView class="min-h-0 flex-1" />
    </div>
</template>
