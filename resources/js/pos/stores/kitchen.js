import { defineStore } from 'pinia';
import { apiFetch } from '../api/client.js';

export const useKitchenStore = defineStore('kitchen', {
    state: () => ({
        tickets: [],
        loading: false,
    }),
    actions: {
        async fetchTickets(stockLocationId) {
            this.loading = true;
            try {
                const res = await apiFetch(`/kitchen/tickets?stock_location_id=${stockLocationId}`);
                this.tickets = res.data;
            } catch {
                // Offline or the request failed -- keep whatever was already
                // on screen rather than blanking the queue mid-service.
            } finally {
                this.loading = false;
            }
        },

        // Removes the line locally as soon as the server confirms it, and
        // drops the whole ticket once it has no lines left, rather than
        // waiting for the next poll to notice.
        async prepareLine(lineId) {
            await apiFetch(`/kitchen/lines/${lineId}/prepare`, { method: 'POST' });

            this.tickets = this.tickets
                .map((ticket) => ({ ...ticket, lines: ticket.lines.filter((line) => line.id !== lineId) }))
                .filter((ticket) => ticket.lines.length > 0);
        },
    },
});
