import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

const { apiFetch } = vi.hoisted(() => ({ apiFetch: vi.fn() }));

vi.mock('../api/client.js', () => ({
    apiFetch,
    ApiError: class ApiError extends Error {
        constructor(message, status) {
            super(message);
            this.status = status;
        }
    },
}));

import { useKitchenStore } from './kitchen.js';

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('kitchen store', () => {
    it('fetches tickets for a location', async () => {
        apiFetch.mockResolvedValueOnce({
            data: [{ cart_id: 1, table: { id: 1, name: 'Patio A' }, sent_at: '2026-01-01T00:00:00Z', lines: [{ id: 10, item_name: 'Cola', quantity: '1' }] }],
        });

        const store = useKitchenStore();
        await store.fetchTickets(2);

        expect(apiFetch).toHaveBeenCalledWith('/kitchen/tickets?stock_location_id=2');
        expect(store.tickets).toHaveLength(1);
        expect(store.loading).toBe(false);
    });

    it('keeps the previous tickets on a failed fetch instead of blanking the queue', async () => {
        const store = useKitchenStore();
        store.tickets = [{ cart_id: 1, table: null, sent_at: '2026-01-01T00:00:00Z', lines: [{ id: 10, item_name: 'Cola', quantity: '1' }] }];
        apiFetch.mockRejectedValueOnce(new Error('Network error'));

        await store.fetchTickets(2);

        expect(store.tickets).toHaveLength(1);
    });

    it('removes a prepared line from its ticket', async () => {
        apiFetch.mockResolvedValueOnce({ message: 'Marked prepared.' });

        const store = useKitchenStore();
        store.tickets = [{
            cart_id: 1,
            table: { id: 1, name: 'Patio A' },
            sent_at: '2026-01-01T00:00:00Z',
            lines: [
                { id: 10, item_name: 'Cola', quantity: '1' },
                { id: 11, item_name: 'Water', quantity: '1' },
            ],
        }];

        await store.prepareLine(10);

        expect(apiFetch).toHaveBeenCalledWith('/kitchen/lines/10/prepare', { method: 'POST' });
        expect(store.tickets).toHaveLength(1);
        expect(store.tickets[0].lines).toEqual([{ id: 11, item_name: 'Water', quantity: '1' }]);
    });

    it('drops a ticket entirely once its last line is prepared', async () => {
        apiFetch.mockResolvedValueOnce({ message: 'Marked prepared.' });

        const store = useKitchenStore();
        store.tickets = [{
            cart_id: 1,
            table: { id: 1, name: 'Patio A' },
            sent_at: '2026-01-01T00:00:00Z',
            lines: [{ id: 10, item_name: 'Cola', quantity: '1' }],
        }];

        await store.prepareLine(10);

        expect(store.tickets).toHaveLength(0);
    });
});
