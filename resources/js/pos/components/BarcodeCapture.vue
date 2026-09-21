<script setup>
import { onMounted, onBeforeUnmount } from 'vue';

const emit = defineEmits(['scan']);

let buffer = '';
let lastKeyTime = 0;

function onKeydown(event) {
    const target = event.target;
    if (target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) {
        return;
    }

    const now = Date.now();
    if (now - lastKeyTime > 200) {
        buffer = '';
    }
    lastKeyTime = now;

    if (event.key === 'Enter') {
        const code = buffer.trim();
        buffer = '';
        if (code) {
            emit('scan', code);
        }
        return;
    }
    if (event.key.length === 1) {
        buffer += event.key;
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <span class="sr-only" aria-hidden="true"></span>
</template>
