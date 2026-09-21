import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router.js';

const app = createApp(App);
app.use(createPinia());
app.use(router);
app.mount('#app');

if ('serviceWorker' in navigator) {
    if (import.meta.env.PROD) {
        // Registered by hand, not via `virtual:pwa-register`: that helper hardcodes
        // Vite's asset base (`/build/`) as both the script URL and the scope, but
        // `sw.js` is deliberately built to the site root (see vite.config.js) so its
        // default scope can cover `/pos` — a scope no `/build/`-served script could
        // ever reach without a `Service-Worker-Allowed` header this static host
        // doesn't send.
        navigator.serviceWorker.register('/sw.js', { scope: '/pos' }).catch(() => {});
    } else {
        // `sw.js` is only ever a stale artifact from a previous `npm run
        // build` during `vite dev` (generateSW never runs in dev). Left
        // registered, it self-activates (skipWaiting/clientsClaim) mid-
        // session and serves its own day-old precached build plus stale
        // NetworkFirst-cached `/api/v1/items` responses instead of what the
        // dev server and current database actually have — unregister it so
        // dev sessions never fight a phantom production build.
        navigator.serviceWorker.getRegistrations().then((registrations) => {
            registrations.forEach((registration) => registration.unregister());
        });
    }
}
