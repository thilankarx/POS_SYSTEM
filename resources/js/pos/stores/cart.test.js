import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

vi.mock('../db/database.js', () => ({
    getCartSnapshot: vi.fn(async () => null),
    saveCartSnapshot: vi.fn(async () => {}),
    deleteCartSnapshot: vi.fn(async () => {}),
    getAllCartSnapshots: vi.fn(async () => []),
}));

vi.mock('../db/queue.js', () => ({
    enqueue: vi.fn(async () => 1),
    removeQueuedOpsForRef: vi.fn(async () => 0),
    countPendingForCart: vi.fn(async () => 0),
}));

vi.mock('../sync/engine.js', () => ({
    processQueue: vi.fn(async () => {}),
}));

const catalogState = { items: [], kits: [] };
vi.mock('./catalog.js', () => ({
    useCatalogStore: () => catalogState,
}));

vi.mock('../api/client.js', () => ({
    apiFetch: vi.fn(async () => ({
        data: {
            totals: {
                subtotal: '0.00',
                discount_total: '0.00',
                tax_total: '0.00',
                rounding_adjustment: '0.00',
                total: '0.00',
            },
            currency: 'USD',
        },
    })),
    ApiError: class ApiError extends Error {},
}));

import { useCartStore } from './cart.js';
import { enqueue, removeQueuedOpsForRef } from '../db/queue.js';
import { getAllCartSnapshots, getCartSnapshot } from '../db/database.js';
import { apiFetch } from '../api/client.js';

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    // clearAllMocks() only clears call history, not a mockImplementation()
    // override a previous test installed (that needs resetAllMocks(), which
    // would also wipe every mock's default factory above) -- so a few tests
    // below that override getCartSnapshot to return a fixed cart would
    // otherwise leak that cart into every test running after them. Restore
    // the module's own default explicitly instead.
    getCartSnapshot.mockImplementation(async () => null);
    catalogState.items = [];
    catalogState.kits = [];
});

describe('cart store', () => {
    it('starts a new sale and queues create_cart, idempotent on client_uuid', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        expect(store.cart.status).toBe('active');
        expect(store.cart.terminal_id).toBe(1);
        expect(enqueue).toHaveBeenCalledWith(
            'create_cart',
            store.cart.client_uuid,
            expect.objectContaining({ terminal_id: 1, client_uuid: store.cart.client_uuid }),
        );
    });

    it('passes the dinner table through to the optimistic cart and the create_cart op', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 }, 'pos', { id: 7, name: 'Patio A' });

        expect(store.cart.dinner_table_id).toBe(7);
        expect(store.cart.dinner_table).toEqual({ id: 7, name: 'Patio A' });
        expect(enqueue).toHaveBeenCalledWith(
            'create_cart',
            store.cart.client_uuid,
            expect.objectContaining({ dinner_table_id: 7 }),
        );
    });

    it('merges a repeated scan of the same item into one line, not two', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        const item = { id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' };
        await store.addLine(item);
        await store.addLine(item);

        expect(store.cart.lines).toHaveLength(1);
        expect(store.cart.lines[0].quantity).toBe('2');
        expect(enqueue).toHaveBeenCalledWith('add_line', store.cart.client_uuid, { item_id: 9, quantity: '1', stock_lot_id: null }, expect.any(String));
        expect(enqueue).toHaveBeenCalledWith('update_line', store.cart.client_uuid, { quantity: '2' }, store.cart.lines[0]._tempId);
    });

    it('resets kitchen_sent on a line when merging more quantity into it', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        const item = { id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' };
        await store.addLine(item);
        store.cart.lines[0].kitchen_sent = true;

        await store.addLine(item);

        expect(store.cart.lines[0].kitchen_sent).toBe(false);
    });

    it.each(['incrementLine', 'decrementLine'])('resets kitchen_sent when %s changes quantity', async (method) => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });
        await store.addLine({ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' }, '2');
        store.cart.lines[0].kitchen_sent = true;

        await store[method](store.cart.lines[0]);

        expect(store.cart.lines[0].kitchen_sent).toBe(false);
    });

    it('resets kitchen_sent when a note is set on an already-sent line', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });
        await store.addLine({ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' });
        store.cart.lines[0].kitchen_sent = true;

        await store.setLineNote(store.cart.lines[0], 'No ice');

        expect(store.cart.lines[0].kitchen_sent).toBe(false);
    });

    it('adds a new line instead of merging when the existing line is already prepared', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        const item = { id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' };
        await store.addLine(item);
        store.cart.lines[0].kitchen_prepared = true;

        await store.addLine(item);

        expect(store.cart.lines).toHaveLength(2);
        expect(store.cart.lines[0].quantity).toBe('1');
        expect(store.cart.lines[1].quantity).toBe('1');
    });

    it('refuses to increase the quantity of an already-prepared line', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });
        await store.addLine({ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' });
        store.cart.lines[0].kitchen_prepared = true;

        await expect(store.incrementLine(store.cart.lines[0])).rejects.toThrow('already prepared');
        await expect(store.setLineQuantity(store.cart.lines[0], 2)).rejects.toThrow('already prepared');
        expect(store.cart.lines[0].quantity).toBe('1');
    });

    it('allows decreasing the quantity of an already-prepared line', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });
        await store.addLine({ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' }, '2');
        store.cart.lines[0].kitchen_prepared = true;
        store.cart.lines[0].kitchen_sent = true;

        await store.setLineQuantity(store.cart.lines[0], 1);

        expect(store.cart.lines[0].quantity).toBe('1');
        // A decrease on already-prepared food doesn't need to resurrect
        // the ticket -- kitchen_sent stays as it was.
        expect(store.cart.lines[0].kitchen_sent).toBe(true);
    });

    it('refreshTotals pulls kitchen_sent/kitchen_prepared from the server without touching anything else', async () => {
        // This is the only way a register ever learns the kitchen display
        // (no push/broadcast in this app) marked a line prepared -- without
        // it, re-ordering the same dish would keep merging into a line the
        // kitchen already closed out.
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });
        await store.addLine({ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' });

        store.cart.id = 42;
        store.cart.lines[0].id = 100;
        store.cart.lines[0].kitchen_prepared = false;

        apiFetch.mockResolvedValueOnce({
            data: {
                totals: { subtotal: '1.50', discount_total: '0.00', tax_total: '0.00', rounding_adjustment: '0.00', total: '1.50' },
                currency: 'USD',
                lines: [{ id: 100, kitchen_sent: true, kitchen_prepared: true }],
            },
        });

        await store.refreshTotals();

        expect(store.cart.lines[0].kitchen_prepared).toBe(true);
        expect(store.cart.lines[0].kitchen_sent).toBe(true);
        expect(store.cart.lines[0].quantity).toBe('1');
    });

    it('merges another scan after the server assigns a stock lot to the first line', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        const item = { id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' };
        await store.addLine(item);
        store.cart.lines[0].stock_lot_id = 5;
        await store.addLine(item);

        expect(store.cart.lines).toHaveLength(1);
        expect(store.cart.lines[0].quantity).toBe('2');
        expect(enqueue).toHaveBeenLastCalledWith(
            'update_line',
            store.cart.client_uuid,
            { quantity: '2' },
            store.cart.lines[0]._tempId,
        );
    });

    it('adding a kit pushes one optimistic line per cached component and enqueues one add_kit op', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        catalogState.items = [
            { id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' },
            { id: 10, name: 'Water', sku: 'WATER', unit_price: '1.00' },
        ];

        const kit = { id: 7, name: 'Starter Bundle', discount_type: 'percent', discount_value: '10', items: [
            { item_id: 9, quantity: '2' },
            { item_id: 10, quantity: '1' },
        ] };

        await store.addKit(kit);

        expect(store.cart.lines).toHaveLength(2);
        expect(store.cart.lines[0]).toMatchObject({ item_id: 9, item_kit_id: 7, quantity: '2', discount_value: '10', discount_type: 'percent' });
        expect(store.cart.lines[1]).toMatchObject({ item_id: 10, item_kit_id: 7, quantity: '1' });
        expect(enqueue).toHaveBeenCalledWith(
            'add_kit',
            store.cart.client_uuid,
            { item_kit_id: 7, quantity: '1' },
            [store.cart.lines[0]._tempId, store.cart.lines[1]._tempId],
        );
    });

    it('leaves a fixed kit discount at 0 optimistically, unlike a percent discount', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        catalogState.items = [{ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' }];

        await store.addKit({ id: 7, discount_type: 'fixed', discount_value: '1.00', items: [{ item_id: 9, quantity: '1' }] });

        expect(store.cart.lines[0].discount_value).toBe('0');
        expect(store.cart.lines[0].discount_type).toBeNull();
    });

    it('adding the same kit twice creates two independent sets of lines', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        catalogState.items = [{ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' }];

        const kit = { id: 7, discount_type: 'percent', discount_value: '0', items: [{ item_id: 9, quantity: '1' }] };
        await store.addKit(kit);
        await store.addKit(kit);

        expect(store.cart.lines).toHaveLength(2);
        expect(store.cart.lines[0]._tempId).not.toBe(store.cart.lines[1]._tempId);
    });

    it('removing a line drops it below zero quantity instead of going negative', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        await store.addLine({ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' });
        const line = store.cart.lines[0];
        await store.decrementLine(line);

        expect(store.cart.lines).toHaveLength(0);
        // The line was never confirmed created server-side (id is still
        // null), so removing it cancels the queued add_line instead of
        // queuing a delete that would find nothing to delete server-side.
        expect(removeQueuedOpsForRef).toHaveBeenCalledWith(store.cart.client_uuid, line._tempId);
        expect(enqueue).not.toHaveBeenCalledWith('remove_line', expect.anything(), expect.anything(), expect.anything());
    });

    it('carries stock_at_location from the item onto the new cart line', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        await store.addLine({ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50', stock_at_location: '240.000' });

        expect(store.cart.lines[0].stock_at_location).toBe('240.000');
    });

    it('starts a quote cart and threads sale_type through create_cart', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 }, 'quote');

        expect(store.cart.sale_type).toBe('quote');
        expect(enqueue).toHaveBeenCalledWith(
            'create_cart',
            store.cart.client_uuid,
            expect.objectContaining({ terminal_id: 1, sale_type: 'quote' }),
        );
    });

    it('overrides a line unit price and marks it overridden', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        await store.addLine({ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' });
        const line = store.cart.lines[0];
        await store.setLinePrice(line, '1.00');

        expect(store.cart.lines[0].unit_price).toBe('1.00');
        expect(store.cart.lines[0].price_overridden).toBe(true);
        expect(enqueue).toHaveBeenCalledWith(
            'update_line',
            store.cart.client_uuid,
            { unit_price: '1.00', price_overridden: true },
            line._tempId,
        );
    });

    it('sets a line note', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        await store.addLine({ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' });
        const line = store.cart.lines[0];
        await store.setLineNote(line, 'Cut to 2.5m');

        expect(store.cart.lines[0].description).toBe('Cut to 2.5m');
        expect(enqueue).toHaveBeenCalledWith(
            'update_line',
            store.cart.client_uuid,
            { description: 'Cut to 2.5m' },
            line._tempId,
        );
    });

    it('sets a line serial', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        await store.addLine({ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' });
        const line = store.cart.lines[0];
        await store.setLineSerial(line, 'SER-001');

        expect(store.cart.lines[0].serial).toBe('SER-001');
        expect(enqueue).toHaveBeenCalledWith(
            'update_line',
            store.cart.client_uuid,
            { serial: 'SER-001' },
            line._tempId,
        );
    });

    it('sets a line to an explicit manually-entered quantity', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        await store.addLine({ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' });
        const line = store.cart.lines[0];
        await store.setLineQuantity(line, '25');

        expect(store.cart.lines[0].quantity).toBe('25');
        expect(enqueue).toHaveBeenCalledWith('update_line', store.cart.client_uuid, { quantity: '25' }, line._tempId);
    });

    it('setting a line quantity to zero removes it', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        await store.addLine({ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' });
        const line = store.cart.lines[0];
        await store.setLineQuantity(line, '0');

        expect(store.cart.lines).toHaveLength(0);
        // Unsynced line (id still null): cancel the queued add_line rather
        // than queue a delete for something the server has never heard of.
        expect(removeQueuedOpsForRef).toHaveBeenCalledWith(store.cart.client_uuid, line._tempId);
    });

    it('removing an already-synced line queues a real delete', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        await store.addLine({ id: 9, name: 'Cola', sku: 'COLA', unit_price: '1.50' });
        const line = store.cart.lines[0];
        line.id = 501; // simulate the add_line op having already synced

        await store.removeLine(line);

        expect(store.cart.lines).toHaveLength(0);
        // Carries the server id so the drain can resolve the DELETE even
        // though the line is already gone from the snapshot.
        expect(enqueue).toHaveBeenCalledWith('remove_line', store.cart.client_uuid, { id: 501 }, line._tempId);
        expect(removeQueuedOpsForRef).not.toHaveBeenCalled();
    });

    it('parking a cart marks it suspended and queues a suspend op', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });
        store.cart.lines.push({ id: 1, item_id: 1, quantity: '1', unit_price: '1.00' });
        const clientUuid = store.cart.client_uuid;

        await store.parkCurrent();

        expect(enqueue).toHaveBeenCalledWith('suspend', clientUuid, {});
        expect(store.cart).toBeNull();
    });

    it('parking an empty cart abandons it instead of suspending it', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });
        const clientUuid = store.cart.client_uuid;

        await store.parkCurrent();

        expect(enqueue).toHaveBeenCalledWith('abandon', clientUuid, {});
        expect(enqueue).not.toHaveBeenCalledWith('suspend', clientUuid, {});
        expect(store.cart).toBeNull();
    });

    it('starting a new sale over an empty cart abandons the empty one', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });
        const emptyUuid = store.cart.client_uuid;

        await store.startNewSale({ id: 1, stock_location_id: 2 });

        expect(enqueue).toHaveBeenCalledWith('abandon', emptyUuid, {});
        expect(store.cart.client_uuid).not.toBe(emptyUuid);
    });

    it('starting a new sale over a cart with lines leaves it set aside', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });
        store.cart.lines.push({ id: 1, item_id: 1, quantity: '1', unit_price: '1.00' });
        const busyUuid = store.cart.client_uuid;

        await store.startNewSale({ id: 1, stock_location_id: 2 });

        expect(enqueue).not.toHaveBeenCalledWith('abandon', busyUuid, {});
    });

    it('leaves empty carts out of the parked list and abandons them', async () => {
        const store = useCartStore();
        const empty = { client_uuid: 'empty-1', status: 'active', lines: [], payments: [] };
        const busy = { client_uuid: 'busy-1', status: 'suspended', lines: [{ id: 1 }], payments: [] };
        getAllCartSnapshots.mockImplementationOnce(async () => [empty, busy]);

        await store.refreshParked();

        expect(store.parked.map((cart) => cart.client_uuid)).toEqual(['busy-1']);
        expect(empty.status).toBe('abandoned');
        expect(enqueue).toHaveBeenCalledWith('abandon', 'empty-1', {});
    });

    it('deleting a parked cart marks it abandoned and queues an abandon op', async () => {
        const store = useCartStore();
        const parked = {
            client_uuid: 'parked-1',
            id: 9,
            status: 'suspended',
            lines: [],
            payments: [],
        };
        getCartSnapshot.mockImplementation(async () => parked);

        await store.deleteParked('parked-1');

        expect(parked.status).toBe('abandoned');
        expect(enqueue).toHaveBeenCalledWith('abandon', 'parked-1', {});
    });

    it('abandoning a cart parked on another terminal calls the API directly', async () => {
        const store = useCartStore();

        await store.abandonOtherTerminalCart(55);

        expect(apiFetch).toHaveBeenCalledWith('/carts/55/abandon', { method: 'POST' });
    });

    it('applies and removes a coupon code, queuing the matching op', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        await store.applyCoupon('SAVE10');
        expect(enqueue).toHaveBeenCalledWith('apply_coupon', store.cart.client_uuid, { code: 'SAVE10' });

        await store.removeCoupon();
        expect(enqueue).toHaveBeenCalledWith('remove_coupon', store.cart.client_uuid, {});
    });

    it('sets a tip, updating the cart optimistically and queuing the matching op', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        await store.setTip('2.50');

        expect(store.cart.tip_amount).toBe('2.50');
        expect(enqueue).toHaveBeenCalledWith('set_tip', store.cart.client_uuid, { tip_amount: '2.50' });
    });

    it('folds the tip into dueRemaining alongside the sale total and payments made so far', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });
        store.cart.totals.total = '10.00';

        expect(store.dueRemaining).toBe(10);

        await store.setTip('2.00');
        expect(store.dueRemaining).toBe(12);

        store.cart.payments.push({ _tempId: 'p1', amount: '5.00' });
        expect(store.dueRemaining).toBe(7);
    });

    it('reports change due from the gap between what was tendered and what each payment covered', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });
        store.cart.totals.total = '10.00';

        expect(store.changeDue).toBe(0);

        // Card payment carries no tendered value -- never contributes change.
        store.cart.payments.push({ _tempId: 'p1', amount: '4.00', tendered: null });
        expect(store.changeDue).toBe(0);

        // Cash: customer handed over 10 to cover the remaining 6.
        store.cart.payments.push({ _tempId: 'p2', amount: '6.00', tendered: '10.00' });
        expect(store.changeDue).toBe(4);
    });

    it('reports change due when a payment\'s amount alone overpays, with no tendered value at all', async () => {
        // The exact regression this fixed: a payment booked at 150.00
        // against a 100.00 total, with tendered left null/absent, used to
        // report 0.00 change here (only the tenderedChange branch was
        // computed) while the completed sale's own total correctly showed
        // 50.00 owed back -- see the changeDue getter's docblock.
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });
        store.cart.totals.total = '100.00';

        store.cart.payments.push({ _tempId: 'p1', amount: '150.00', tendered: null });
        expect(store.changeDue).toBe(50);
    });

    it('attaches and removes a customer, queuing the matching op', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });

        const customer = { id: 42, company_name: 'Acme' };
        await store.attachCustomer(customer);
        expect(store.cart.customer_id).toBe(42);
        expect(store.cart.customer).toEqual(customer);
        expect(enqueue).toHaveBeenCalledWith('attach_customer', store.cart.client_uuid, { customer_id: 42 });

        await store.removeCustomer();
        expect(store.cart.customer_id).toBeNull();
        expect(store.cart.customer).toBeNull();
        expect(enqueue).toHaveBeenCalledWith('remove_customer', store.cart.client_uuid, {});
    });

    // Parking suspends the cart server-side, so resume has to reactivate it or
    // the next line/payment op 422s and the sync engine discards the whole
    // cart. Regression test for exactly that.
    it('resuming a parked cart reactivates it and queues the server-side resume', async () => {
        const store = useCartStore();
        await store.startNewSale({ id: 1, stock_location_id: 2 });
        const clientUuid = store.cart.client_uuid;
        await store.parkCurrent();

        // What IndexedDB hands back on resume: the cart as parking left it.
        const parked = {
            client_uuid: clientUuid,
            id: 7,
            status: 'suspended',
            terminal_id: 1,
            stock_location_id: 2,
            lines: [],
            payments: [],
            totals: {
                subtotal: '0.00',
                discount_total: '0.00',
                tax_total: '0.00',
                rounding_adjustment: '0.00',
                total: '0.00',
            },
            currency: 'USD',
            pendingOps: 0,
            lastError: null,
            completedSale: null,
            completeIdempotencyKey: null,
            coupon_code: null,
            customer_id: null,
            customer: null,
        };
        getCartSnapshot.mockImplementation(async () => parked);

        await store.resume(clientUuid);

        expect(store.cart.status).toBe('active');
        expect(enqueue).toHaveBeenCalledWith('create_cart', clientUuid, {
            client_uuid: clientUuid,
            terminal_id: 1,
        });
    });

    it('resuming a cart that was never suspended does not re-queue create_cart', async () => {
        const store = useCartStore();
        const active = {
            client_uuid: 'abc',
            id: 8,
            status: 'active',
            terminal_id: 1,
            stock_location_id: 2,
            lines: [],
            payments: [],
            totals: {
                subtotal: '0.00',
                discount_total: '0.00',
                tax_total: '0.00',
                rounding_adjustment: '0.00',
                total: '0.00',
            },
            currency: 'USD',
            pendingOps: 0,
            lastError: null,
            completedSale: null,
            completeIdempotencyKey: null,
            coupon_code: null,
            customer_id: null,
            customer: null,
        };
        getCartSnapshot.mockImplementation(async () => active);

        await store.resume('abc');

        expect(store.cart.status).toBe('active');
        expect(enqueue).not.toHaveBeenCalledWith('create_cart', 'abc', expect.anything());
    });
});
