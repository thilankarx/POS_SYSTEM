import { copyFileSync, existsSync } from 'node:fs';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

// vite-plugin-pwa always writes `manifest.webmanifest` into the main Vite
// build's outDir (`public/build/`, from laravel-vite-plugin) regardless of
// the PWA `outDir` override below — but it still records the service
// worker's precache entry for it as if it lived at the PWA outDir's root
// (`public/manifest.webmanifest`). Left alone, that one precache entry
// 404s, which fails the whole service worker install (Workbox aborts the
// entire precache batch on any single miss, and the worker goes straight
// from `installing` to `redundant`). Copying the real file to where the
// precache entry expects it — after every build, since the filename is
// stable — closes that gap without fighting the plugin's internals.
function syncWebManifestToSiteRoot() {
    return {
        name: 'sync-webmanifest-to-site-root',
        closeBundle() {
            const from = 'public/build/manifest.webmanifest';
            if (existsSync(from)) {
                copyFileSync(from, 'public/manifest.webmanifest');
            }
        },
    };
}

export default defineConfig(({ mode }) => {
    // Vite only exposes VITE_-prefixed env vars to frontend code via
    // import.meta.env -- this config file itself runs in Node, outside that
    // pipeline, so the manifest below needs loadEnv() to read the same value.
    const appName = loadEnv(mode, process.cwd(), '').VITE_APP_NAME || 'Laravel';

    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/css/pos.css',
                    'resources/js/pos/main.js',
                ],
                refresh: true,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
                    }),
                ],
            }),
            tailwindcss(),
            vue(),
            VitePWA({
                strategies: 'generateSW',
                registerType: 'autoUpdate',
                injectRegister: false,
                // `sw.js` must be served from the site root, not `/build/`, so its
                // default scope can cover `/pos` — a service worker can never
                // widen its own scope past the directory it's served from
                // without a `Service-Worker-Allowed` header, which plain
                // Apache/XAMPP static hosting here doesn't send.
                outDir: 'public',
                includeAssets: ['icons/icon-192.png', 'icons/icon-512.png'],
                manifest: {
                    id: '/pos',
                    name: `${appName} Register`,
                    short_name: 'Register',
                    description: `${appName} point-of-sale register`,
                    start_url: '/pos',
                    scope: '/pos',
                    display: 'standalone',
                    background_color: '#0f172a',
                    theme_color: '#0f172a',
                    icons: [
                        { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
                        { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
                    ],
                },
                workbox: {
                    // Without these, a newly-activated worker doesn't take
                    // control of pages that were already open when it finished
                    // installing — it only controls page loads that start
                    // *after* activation. That leaves the very session that
                    // triggered the install uncontrolled, so its own
                    // dynamic-import route chunks bypass the SW and 404 the
                    // instant the network drops, defeating offline navigation
                    // on a first visit.
                    skipWaiting: true,
                    clientsClaim: true,
                    navigateFallback: '/pos',
                    globPatterns: ['**/*.{js,css,html}'],
                    runtimeCaching: [
                        {
                            urlPattern: /\/api\/v1\/(items|payment-methods|terminals)/,
                            handler: 'NetworkFirst',
                            options: { cacheName: 'pos-api-cache', networkTimeoutSeconds: 3 },
                        },
                    ],
                },
            }),
            syncWebManifestToSiteRoot(),
        ],
        server: {
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
