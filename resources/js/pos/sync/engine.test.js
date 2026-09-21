import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../api/client.js', () => {
    class ApiError extends Error {
        constructor(message, status, body, isNetworkError = false) {
            super(message);
            this.status = status;
            this.body = body;
            this.isNetworkError = isNetworkError;
            this.isRateLimited = false;
            this.retryAfter = null;
        }
    }
    return { apiFetch: vi.fn(), ApiError };
});

vi.mock('../db/database.js', () => ({
    getCartSnapshot: vi.fn(),
    saveCartSnapshot: vi.fn(),
}));

vi.mock('../db/queue.js', () => ({
    getAllQueue: vi.fn(),
    dequeue: vi.fn(),
    removeOpsForCart: vi.fn(),
}));

import { apiFetch, ApiError } from '../api/client.js';
import { getCartSnapshot } from '../db/database.js';
import { getAllQueue, dequeue, removeOpsForCart } from '../db/queue.js';
import { processQueue } from './engine.js';

function makeSnapshot(overrides = {}) {
    return {
        client_uuid: 'cart-1',
        id: null,
        status: 'active',
        lines: [],
        payments: [],
        totals: { subtotal: '0', discount_total: '0', tax_total: '0', rounding_adjustment: '0', total: '0' },
        pendingOps: 0,
        lastError: null,
        completedSale: null,
        ...overrides,
    };
}

beforeEach(() => {
    vi.clearAllMocks();
});

describe('processQueue', () => {
    it('drains queued ops in order and reconciles server ids', async () => {
        const snapshot = makeSnapshot({
            pendingOps: 2,
            lines: [{ _tempId: 'local-1', id: null, item_id: 5, quantity: '1', unit_price: '2.00' }],
        });

        let queue = [
            { seq: 1, type: 'create_cart', clientCartUuid: 'cart-1', payload: { client_uuid: 'cart-1', terminal_id: 1 }, localRef: null },
            { seq: 2, type: 'add_line', clientCartUuid: 'cart-1', payload: { item_id: 5, quantity: '1' }, localRef: 'local-1' },
        ];

        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        dequeue.mockImplementation(async (seq) => {
            queue = queue.filter((op) => op.seq !== seq);
        });
        apiFetch
            .mockResolvedValueOnce({ data: { id: 42, status: 'active' } })
            .mockResolvedValueOnce({ data: { id: 99, item_id: 5, quantity: '1', unit_price: '2.00' } });

        await processQueue();

        expect(apiFetch).toHaveBeenNthCalledWith(1, '/carts', expect.objectContaining({ method: 'POST' }));
        expect(apiFetch).toHaveBeenNthCalledWith(2, '/carts/42/lines', expect.objectContaining({ method: 'POST' }));
        expect(snapshot.id).toBe(42);
        expect(snapshot.lines[0].id).toBe(99);
        expect(snapshot.pendingOps).toBe(0);
        expect(queue).toHaveLength(0);
    });

    it('collapses two queued add_line ops for the same item into one local line when the server merges them', async () => {
        // AddCartLineAction merges a same-item add_line into an existing
        // server row rather than creating a second one -- so when both
        // locally-distinct queued ops resolve, the second op's response is
        // the *same* merged row (bumped quantity) that the first op's line
        // already holds. Without collapsing them, the cart would show two
        // local lines for one server row, one of them stuck at the stale
        // pre-merge quantity.
        const snapshot = makeSnapshot({
            id: 42,
            pendingOps: 2,
            lines: [
                { _tempId: 'local-1', id: null, item_id: 5, quantity: '1' },
                { _tempId: 'local-2', id: null, item_id: 5, quantity: '1' },
            ],
        });

        let queue = [
            { seq: 1, type: 'add_line', clientCartUuid: 'cart-1', payload: { item_id: 5, quantity: '1' }, localRef: 'local-1' },
            { seq: 2, type: 'add_line', clientCartUuid: 'cart-1', payload: { item_id: 5, quantity: '1' }, localRef: 'local-2' },
        ];

        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        dequeue.mockImplementation(async (seq) => {
            queue = queue.filter((op) => op.seq !== seq);
        });
        apiFetch
            .mockResolvedValueOnce({ data: { id: 99, item_id: 5, quantity: '1' } })
            .mockResolvedValueOnce({ data: { id: 99, item_id: 5, quantity: '2' } });

        await processQueue();

        expect(snapshot.lines).toHaveLength(1);
        expect(snapshot.lines[0]).toMatchObject({ _tempId: 'local-2', id: 99, quantity: '2' });
    });

    it('posts an add_kit op to the kit-lines endpoint and zips server lines back onto the right local lines', async () => {
        const snapshot = makeSnapshot({
            id: 42,
            pendingOps: 1,
            lines: [
                { _tempId: 'local-a', id: null, item_id: 9, item_kit_id: 7, quantity: '2' },
                { _tempId: 'local-b', id: null, item_id: 10, item_kit_id: 7, quantity: '1' },
            ],
        });

        let queue = [
            {
                seq: 1,
                type: 'add_kit',
                clientCartUuid: 'cart-1',
                payload: { item_kit_id: 7, quantity: '1' },
                localRef: ['local-a', 'local-b'],
            },
        ];

        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        dequeue.mockImplementation(async (seq) => {
            queue = queue.filter((op) => op.seq !== seq);
        });
        apiFetch.mockResolvedValueOnce({
            data: [
                { id: 101, item_id: 9, item_kit_id: 7, quantity: '2', unit_price: '1.50' },
                { id: 102, item_id: 10, item_kit_id: 7, quantity: '1', unit_price: '1.00' },
            ],
        });

        await processQueue();

        expect(apiFetch).toHaveBeenCalledWith('/carts/42/kit-lines', expect.objectContaining({ method: 'POST', body: { item_kit_id: 7, quantity: '1' } }));
        expect(snapshot.lines[0]).toMatchObject({ _tempId: 'local-a', id: 101, unit_price: '1.50' });
        expect(snapshot.lines[1]).toMatchObject({ _tempId: 'local-b', id: 102, unit_price: '1.00' });
        expect(queue).toHaveLength(0);
    });

    it('stops the whole drain on a network-level failure and leaves the op queued', async () => {
        const snapshot = makeSnapshot({ pendingOps: 1 });
        const queue = [
            { seq: 1, type: 'create_cart', clientCartUuid: 'cart-1', payload: { client_uuid: 'cart-1', terminal_id: 1 }, localRef: null },
        ];
        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        apiFetch.mockRejectedValueOnce(new ApiError('Network error', 0, null, true));

        await processQueue();

        expect(dequeue).not.toHaveBeenCalled();
        expect(removeOpsForCart).not.toHaveBeenCalled();
    });

    it('drops all queued ops for a cart on a real error response, without touching other carts', async () => {
        const snapshotA = makeSnapshot({ client_uuid: 'cart-a', id: 10, pendingOps: 1 });
        const snapshotB = makeSnapshot({ client_uuid: 'cart-b', id: 20, pendingOps: 1 });
        snapshotA.lines = [{ _tempId: 'local-1', id: 7, quantity: '1' }];

        let queue = [
            { seq: 1, type: 'update_line', clientCartUuid: 'cart-a', payload: { quantity: '2' }, localRef: 'local-1' },
            { seq: 2, type: 'create_cart', clientCartUuid: 'cart-b', payload: { client_uuid: 'cart-b', terminal_id: 1 }, localRef: null },
        ];

        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async (uuid) => (uuid === 'cart-a' ? snapshotA : snapshotB));
        dequeue.mockImplementation(async (seq) => {
            queue = queue.filter((op) => op.seq !== seq);
        });
        removeOpsForCart.mockImplementation(async (uuid) => {
            queue = queue.filter((op) => op.clientCartUuid !== uuid);
        });
        apiFetch
            .mockRejectedValueOnce(new ApiError('Cart is not active.', 422, null, false))
            .mockResolvedValueOnce({ data: { id: 20, status: 'active' } });

        await processQueue();

        expect(removeOpsForCart).toHaveBeenCalledWith('cart-a');
        expect(snapshotA.lastError).toBe('Cart is not active.');
        expect(snapshotB.id).toBe(20);
        expect(queue).toHaveLength(0);
    });

    it('keeps the entire queue intact on a 429 and schedules a retry', async () => {
        vi.useFakeTimers();
        try {
            const snapshot = makeSnapshot({ client_uuid: 'cart-1', id: 10, pendingOps: 1 });
            snapshot.lines = [{ _tempId: 'local-1', id: 7, quantity: '1' }];
            let queue = [
                { seq: 1, type: 'update_line', clientCartUuid: 'cart-1', payload: { quantity: '2' }, localRef: 'local-1' },
            ];
            getAllQueue.mockImplementation(async () => queue);
            getCartSnapshot.mockImplementation(async () => snapshot);
            dequeue.mockImplementation(async (seq) => {
                queue = queue.filter((op) => op.seq !== seq);
            });

            const rateLimited = new ApiError('slow down', 429, null, false);
            rateLimited.isRateLimited = true;
            rateLimited.retryAfter = 3;
            apiFetch.mockRejectedValueOnce(rateLimited);

            await processQueue();

            // Nothing dropped, nothing dequeued, no scary error surfaced.
            expect(dequeue).not.toHaveBeenCalled();
            expect(removeOpsForCart).not.toHaveBeenCalled();
            expect(snapshot.lastError).toBeNull();

            // A retry is scheduled; when it fires and the limiter has reset,
            // the op drains normally.
            apiFetch.mockResolvedValueOnce({ data: { id: 7, quantity: '2' } });
            await vi.advanceTimersByTimeAsync(3000);
            expect(apiFetch).toHaveBeenCalledTimes(2);
        } finally {
            vi.clearAllTimers();
            vi.useRealTimers();
        }
    });

    it('reuses the same Idempotency-Key across retries of the same queued complete op', async () => {
        const snapshot = makeSnapshot({ id: 55, pendingOps: 1 });
        let queue = [
            { seq: 1, type: 'complete', clientCartUuid: 'cart-1', payload: { idempotency_key: 'fixed-key-123' }, localRef: null },
        ];
        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        dequeue.mockImplementation(async (seq) => {
            queue = queue.filter((op) => op.seq !== seq);
        });

        apiFetch.mockRejectedValueOnce(new ApiError('Network error', 0, null, true));
        await processQueue();
        expect(apiFetch).toHaveBeenNthCalledWith(
            1,
            '/carts/55/complete',
            expect.objectContaining({ idempotencyKey: 'fixed-key-123' }),
        );

        apiFetch.mockResolvedValueOnce({ data: { id: 1, total: '10.00', change_given: '0.00', number: 'S-1' } });
        await processQueue();
        expect(apiFetch).toHaveBeenNthCalledWith(
            2,
            '/carts/55/complete',
            expect.objectContaining({ idempotencyKey: 'fixed-key-123' }),
        );
        expect(snapshot.status).toBe('completed');
    });

    it('sends add_payment with its idempotency key as a header, not as a body field', async () => {
        const snapshot = makeSnapshot({
            id: 55,
            pendingOps: 1,
            payments: [{ _tempId: 'pay-1', id: null, amount: '10.00' }],
        });
        let queue = [
            {
                seq: 1,
                type: 'add_payment',
                clientCartUuid: 'cart-1',
                payload: { payment_method_id: 2, amount: '10.00', idempotency_key: 'pay-1' },
                localRef: 'pay-1',
            },
        ];
        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        dequeue.mockImplementation(async (seq) => {
            queue = queue.filter((op) => op.seq !== seq);
        });
        apiFetch.mockResolvedValueOnce({ data: { id: 7, payment_method_id: 2, amount: '10.00' } });

        await processQueue();

        expect(apiFetch).toHaveBeenCalledWith('/carts/55/payments', {
            method: 'POST',
            body: { payment_method_id: 2, amount: '10.00' },
            idempotencyKey: 'pay-1',
        });
        expect(snapshot.payments[0].id).toBe(7);
    });

    it('suspends a cart and patches status onto the snapshot', async () => {
        const snapshot = makeSnapshot({ id: 30, pendingOps: 1, status: 'suspended' });
        let queue = [{ seq: 1, type: 'suspend', clientCartUuid: 'cart-1', payload: {}, localRef: null }];
        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        dequeue.mockImplementation(async (seq) => {
            queue = queue.filter((op) => op.seq !== seq);
        });
        apiFetch.mockResolvedValueOnce({ data: { status: 'suspended' } });

        await processQueue();

        expect(apiFetch).toHaveBeenCalledWith('/carts/30/suspend', expect.objectContaining({ method: 'POST' }));
        expect(snapshot.status).toBe('suspended');
    });

    it('abandons a cart and patches status onto the snapshot', async () => {
        const snapshot = makeSnapshot({ id: 31, pendingOps: 1, status: 'abandoned' });
        let queue = [{ seq: 1, type: 'abandon', clientCartUuid: 'cart-1', payload: {}, localRef: null }];
        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        dequeue.mockImplementation(async (seq) => {
            queue = queue.filter((op) => op.seq !== seq);
        });
        apiFetch.mockResolvedValueOnce({ data: { status: 'abandoned' } });

        await processQueue();

        expect(apiFetch).toHaveBeenCalledWith('/carts/31/abandon', expect.objectContaining({ method: 'POST' }));
        expect(snapshot.status).toBe('abandoned');
    });

    it('applies a coupon and patches coupon_code onto the snapshot', async () => {
        const snapshot = makeSnapshot({ id: 30, pendingOps: 1 });
        let queue = [
            { seq: 1, type: 'apply_coupon', clientCartUuid: 'cart-1', payload: { code: 'SAVE10' }, localRef: null },
        ];
        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        dequeue.mockImplementation(async (seq) => {
            queue = queue.filter((op) => op.seq !== seq);
        });
        apiFetch.mockResolvedValueOnce({ data: { coupon_code: 'SAVE10' } });

        await processQueue();

        expect(apiFetch).toHaveBeenCalledWith('/carts/30/coupon', expect.objectContaining({ method: 'PATCH', body: { code: 'SAVE10' } }));
        expect(snapshot.coupon_code).toBe('SAVE10');
    });

    it('updates a line and re-applies every field in its own payload, not just quantity', async () => {
        const snapshot = makeSnapshot({
            id: 30,
            pendingOps: 1,
            lines: [{ _tempId: 'local-1', id: 5, quantity: '1', unit_price: '1.50', description: null, price_overridden: false }],
        });
        let queue = [
            {
                seq: 1,
                type: 'update_line',
                clientCartUuid: 'cart-1',
                payload: { unit_price: '1.00', price_overridden: true, description: 'Cut to 2.5m' },
                localRef: 'local-1',
            },
        ];
        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        dequeue.mockImplementation(async (seq) => {
            queue = queue.filter((op) => op.seq !== seq);
        });
        apiFetch.mockResolvedValueOnce({ data: { id: 5 } });

        await processQueue();

        const line = snapshot.lines[0];
        expect(line.unit_price).toBe('1.00');
        expect(line.price_overridden).toBe(true);
        expect(line.description).toBe('Cut to 2.5m');
    });

    it('surfaces an invalid coupon as lastError without touching other queued ops', async () => {
        const snapshot = makeSnapshot({ id: 30, pendingOps: 1 });
        let queue = [
            { seq: 1, type: 'apply_coupon', clientCartUuid: 'cart-1', payload: { code: 'BAD' }, localRef: null },
        ];
        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        removeOpsForCart.mockImplementation(async (uuid) => {
            queue = queue.filter((op) => op.clientCartUuid !== uuid);
        });
        apiFetch.mockRejectedValueOnce(new ApiError('This coupon code is invalid, expired, or no longer available.', 422, null, false));

        await processQueue();

        expect(snapshot.lastError).toBe('This coupon code is invalid, expired, or no longer available.');
        expect(removeOpsForCart).toHaveBeenCalledWith('cart-1');
    });

    it('attaches a customer and patches customer_id/customer onto the snapshot', async () => {
        const snapshot = makeSnapshot({ id: 30, pendingOps: 1 });
        let queue = [
            { seq: 1, type: 'attach_customer', clientCartUuid: 'cart-1', payload: { customer_id: 7 }, localRef: null },
        ];
        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        dequeue.mockImplementation(async (seq) => {
            queue = queue.filter((op) => op.seq !== seq);
        });
        apiFetch.mockResolvedValueOnce({ data: { customer_id: 7, customer: { id: 7, company_name: 'Acme' } } });

        await processQueue();

        expect(snapshot.customer_id).toBe(7);
        expect(snapshot.customer).toEqual({ id: 7, company_name: 'Acme' });
    });

    it('does not wipe the cart when create_cart fails, and lets other carts keep draining', async () => {
        const snapshotA = makeSnapshot({ client_uuid: 'cart-a', id: null, pendingOps: 1 });
        const snapshotB = makeSnapshot({ client_uuid: 'cart-b', id: null, pendingOps: 1 });

        let queue = [
            { seq: 1, type: 'create_cart', clientCartUuid: 'cart-a', payload: { client_uuid: 'cart-a', terminal_id: 1 }, localRef: null },
            { seq: 2, type: 'create_cart', clientCartUuid: 'cart-b', payload: { client_uuid: 'cart-b', terminal_id: 1 }, localRef: null },
        ];
        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async (uuid) => (uuid === 'cart-a' ? snapshotA : snapshotB));
        dequeue.mockImplementation(async (seq) => {
            queue = queue.filter((op) => op.seq !== seq);
        });
        apiFetch
            .mockRejectedValueOnce(new ApiError('The shift for this terminal is not open.', 422, null, false))
            .mockResolvedValueOnce({ data: { id: 99, status: 'active' } });

        await processQueue();

        // cart-a's create_cart op is neither dequeued nor wiped -- it's
        // still there to retry once a shift is open, not silently deleted.
        expect(removeOpsForCart).not.toHaveBeenCalledWith('cart-a');
        expect(queue.some((op) => op.clientCartUuid === 'cart-a')).toBe(true);
        expect(snapshotA.lastError).toBe('The shift for this terminal is not open.');
        // cart-b was not blocked behind cart-a.
        expect(snapshotB.id).toBe(99);
    });

    it('does not wipe the cart when remove_line cannot resolve its target -- drops only that op', async () => {
        const snapshot = makeSnapshot({ id: 40, pendingOps: 2 });
        // Simulates the exact scenario the fix closes: an item was added and
        // removed offline before either op synced. cart.js's removeLine()
        // would normally cancel the add_line locally so this op is never
        // enqueued at all -- this test exercises engine.js's own defensive
        // fallback directly, for the narrow case where it still runs.
        let queue = [{ seq: 1, type: 'remove_line', clientCartUuid: 'cart-1', payload: {}, localRef: 'local-1' }];
        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        dequeue.mockImplementation(async (seq) => {
            queue = queue.filter((op) => op.seq !== seq);
        });

        await processQueue();

        expect(apiFetch).not.toHaveBeenCalled();
        expect(removeOpsForCart).not.toHaveBeenCalled();
        expect(dequeue).toHaveBeenCalledWith(1);
        expect(snapshot.lastError).toMatch(/could not be confirmed/);
        // The dropped op still counts against pendingOps -- otherwise the
        // "syncing…" badge stays on with an empty queue behind it.
        expect(snapshot.pendingOps).toBe(1);
    });

    it('deletes an already-synced line using the id carried on the op, even though it is gone from the snapshot', async () => {
        // cart.js filters the line out of the cart before enqueuing
        // remove_line, so the snapshot here has no matching line -- the drain
        // must fall back to op.payload.id to resolve the DELETE.
        const snapshot = makeSnapshot({ id: 40, pendingOps: 1, lines: [] });
        let queue = [{ seq: 1, type: 'remove_line', clientCartUuid: 'cart-1', payload: { id: 501 }, localRef: 'local-1' }];
        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        dequeue.mockImplementation(async (seq) => {
            queue = queue.filter((op) => op.seq !== seq);
        });
        apiFetch.mockResolvedValueOnce({});

        await processQueue();

        expect(apiFetch).toHaveBeenCalledWith('/carts/40/lines/501', { method: 'DELETE' });
        expect(dequeue).toHaveBeenCalledWith(1);
        expect(snapshot.lastError).toBeNull();
        expect(snapshot.pendingOps).toBe(0);
    });

    it('treats a 401 as retryable, leaving the queue intact instead of wiping it', async () => {
        const snapshot = makeSnapshot({ pendingOps: 1 });
        const queue = [
            { seq: 1, type: 'create_cart', clientCartUuid: 'cart-1', payload: { client_uuid: 'cart-1', terminal_id: 1 }, localRef: null },
        ];
        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        // isNetworkError: true, matching client.js's 401 handling.
        apiFetch.mockRejectedValueOnce(new ApiError('Unauthorized', 401, null, true));

        await processQueue();

        expect(dequeue).not.toHaveBeenCalled();
        expect(removeOpsForCart).not.toHaveBeenCalled();
    });

    it('retries a 5xx on complete instead of destroying the queued sale', async () => {
        const snapshot = makeSnapshot({ id: 55, pendingOps: 1 });
        const queue = [
            { seq: 1, type: 'complete', clientCartUuid: 'cart-1', payload: { idempotency_key: 'key-1' }, localRef: null },
        ];
        getAllQueue.mockImplementation(async () => queue);
        getCartSnapshot.mockImplementation(async () => snapshot);
        apiFetch.mockRejectedValueOnce(new ApiError('Bad Gateway', 502, null, false));

        await processQueue();

        expect(dequeue).not.toHaveBeenCalled();
        expect(removeOpsForCart).not.toHaveBeenCalled();
    });
});
