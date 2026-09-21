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

import { useTablesStore } from './tables.js';
import { ApiError } from '../api/client.js';

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('tables store', () => {
    it('fetches tables for a location', async () => {
        apiFetch.mockResolvedValueOnce({ data: [{ id: 1, name: 'T1', seats: 4, status: 'available' }] });

        const store = useTablesStore();
        await store.fetchForLocation(2);

        expect(apiFetch).toHaveBeenCalledWith('/dinner-tables?stock_location_id=2');
        expect(store.tables).toHaveLength(1);
        expect(store.loading).toBe(false);
    });

    it('keeps the previous tables on a failed fetch instead of blanking the floor', async () => {
        const store = useTablesStore();
        store.tables = [{ id: 1, name: 'T1', seats: 4, status: 'available' }];
        apiFetch.mockRejectedValueOnce(new ApiError('Network error', 0));

        await store.fetchForLocation(2);

        expect(store.tables).toHaveLength(1);
    });

    it('returns the parked cart for an occupied table', async () => {
        apiFetch.mockResolvedValueOnce({ data: { id: 5, client_uuid: 'abc' } });

        const store = useTablesStore();
        const cart = await store.findOpenCart(1);

        expect(apiFetch).toHaveBeenCalledWith('/dinner-tables/1/cart');
        expect(cart.id).toBe(5);
    });

    it('returns null (not a thrown error) when no cart is parked at the table', async () => {
        apiFetch.mockRejectedValueOnce(new ApiError('Not Found', 404));

        const store = useTablesStore();
        const cart = await store.findOpenCart(1);

        expect(cart).toBeNull();
    });

    it('rethrows a non-404 error', async () => {
        apiFetch.mockRejectedValueOnce(new ApiError('Network error', 0));

        const store = useTablesStore();
        await expect(store.findOpenCart(1)).rejects.toThrow('Network error');
    });
});
