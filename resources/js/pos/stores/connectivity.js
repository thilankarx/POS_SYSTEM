import { defineStore } from 'pinia';
import { pingServer } from '../api/client.js';

// `navigator.onLine` only answers "is there any network at all" -- it stays
// true when the shop's internet is down but the LAN (and this app's own
// server) is still reachable, and it can't tell us the server PC itself has
// gone away while the register's wifi is fine. So the source of truth here
// is an actual probe of our own `/api/v1/ping`. Poll harder while we think
// we're offline so a recovered link is picked up quickly; back off once
// online so a healthy register isn't firing a request every few seconds all
// shift.
const POLL_ONLINE_MS = 20000;
const POLL_OFFLINE_MS = 5000;

// Kept in module scope rather than store state: a timer handle and a
// callback ref are plumbing, not reactive data.
let timer = null;
let onOnlineCb = null;
let checking = false;

export const useConnectivityStore = defineStore('connectivity', {
    state: () => ({
        // Optimistic until the first probe resolves (a few seconds). A brief
        // wrong "online" at boot is harmless; starting "offline" would flash
        // the banner on every load.
        online: typeof navigator === 'undefined' ? true : navigator.onLine,
        initialized: false,
    }),
    actions: {
        setOnline(next) {
            const was = this.online;
            this.online = next;
            // Fire the reconnect hook only on a real offline -> online edge,
            // so it drains the queue once per recovery, not every poll.
            if (next && !was) {
                onOnlineCb?.();
            }
        },

        async check() {
            if (checking) {
                return;
            }
            checking = true;
            try {
                this.setOnline(await pingServer());
            } finally {
                checking = false;
                this.schedule();
            }
        },

        schedule() {
            if (typeof window === 'undefined') {
                return;
            }
            clearTimeout(timer);
            timer = setTimeout(
                () => this.check(),
                this.online ? POLL_ONLINE_MS : POLL_OFFLINE_MS,
            );
        },

        init(onOnline) {
            if (this.initialized || typeof window === 'undefined') {
                return;
            }
            this.initialized = true;
            onOnlineCb = onOnline ?? null;

            // Browser link events are still the fastest hint we get -- treat
            // them as a nudge to re-probe now instead of waiting for the next
            // scheduled poll. `offline` we can trust immediately; `online`
            // only means "a link exists", so confirm it with a probe.
            window.addEventListener('online', () => this.check());
            window.addEventListener('offline', () => this.setOnline(false));
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.check();
                }
            });

            this.check();
        },
    },
});
