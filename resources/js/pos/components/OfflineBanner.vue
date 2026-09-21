<script setup>
import { computed } from 'vue';
import { useConnectivityStore } from '../stores/connectivity.js';
import { useCartStore } from '../stores/cart.js';

const connectivity = useConnectivityStore();
const cart = useCartStore();

const label = computed(() => {
    if (!connectivity.online) {
        return "Offline — can't reach the server. Sales are saved on this register and finish once it reconnects.";
    }
    if (cart.syncing || cart.hasUnsyncedChanges) {
        return 'Syncing…';
    }
    return null;
});
</script>

<template>
    <div
        v-if="label"
        class="px-4 py-1 text-center text-sm font-medium"
        :class="connectivity.online ? 'bg-amber-500 text-amber-950' : 'bg-red-600 text-white'"
    >
        {{ label }}
    </div>
</template>
