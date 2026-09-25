import { defineStore } from 'pinia';
import {
    getCartSnapshot,
    saveCartSnapshot,
    deleteCartSnapshot,
    getAllCartSnapshots,
} from '../db/database.js';
import { enqueue, removeQueuedOpsForRef, countPendingForCart } from '../db/queue.js';
import { processQueue } from '../sync/engine.js';
import { apiFetch, ApiError } from '../api/client.js';
import { useCatalogStore } from './catalog.js';
import { randomUuid } from '../lib/uuid.js';

function zeroTotals() {
    return {
        subtotal: '0.00',
        discount_total: '0.00',
        tax_total: '0.00',
        rounding_adjustment: '0.00',
        total: '0.00',
    };
}

let tempIdCounter = 0;
function nextTempId() {
    tempIdCounter += 1;
    return `local-${Date.now()}-${tempIdCounter}`;
}

export const useCartStore = defineStore('cart', {
    state: () => ({
        cart: null,
        parked: [],
        syncing: false,
    }),
    getters: {
        hasUnsyncedChanges: (state) => (state.cart?.pendingOps ?? 0) > 0,
        dueRemaining: (state) => {
            if (!state.cart) {
                return 0;
            }
            const total = Number(state.cart.totals.total) + Number(state.cart.tip_amount || 0);
            const paid = state.cart.payments.reduce((sum, payment) => sum + Number(payment.amount), 0);
            return Math.max(0, Math.round((total - paid) * 100) / 100);
        },
        // Cash to hand back. Two views of the same overpayment, same as
        // CompleteSaleAction: change-giving methods (cash) record what was
        // tendered over `amount`, so that overpayment lives in the
        // `tendered` gap -- but a payment booked directly above what's due
        // (no `tendered` at all, or a non-cash method) instead shows up as
        // `amount` itself exceeding the total. Take whichever is larger
        // rather than summing, so the two never double-count; this used to
        // only compute the first, so a payment whose `amount` alone
        // exceeded the total (e.g. amount and tendered both booked at
        // 150.00 against a 100.00 sale) showed 0.00 change here while the
        // completed sale's receipt printed 50.00.
        changeDue: (state) => {
            if (!state.cart) {
                return 0;
            }
            const total = Number(state.cart.totals.total) + Number(state.cart.tip_amount || 0);
            const paid = state.cart.payments.reduce((sum, payment) => sum + Number(payment.amount || 0), 0);
            const overpaid = Math.max(0, paid - total);

            const tenderedChange = state.cart.payments.reduce((sum, payment) => {
                const tendered = Number(payment.tendered || 0);
                const amount = Number(payment.amount || 0);
                return sum + Math.max(0, tendered - amount);
            }, 0);

            return Math.round(Math.max(tenderedChange, overpaid) * 100) / 100;
        },
    },
    actions: {
        async refreshParked() {
            const all = await getAllCartSnapshots();
            this.parked = all.filter(
                (candidate) =>
                    candidate.client_uuid !== this.cart?.client_uuid &&
                    (candidate.status === 'active' || candidate.status === 'suspended'),
            );
        },

        // dinnerTable is the full { id, name } tile the cashier tapped, not
        // just an id -- create_cart's sync op only ever patches { id, status }
        // from the server response (see engine.js), so the name has to come
        // from what the register already fetched for the floor rather than
        // waiting on a round trip.
        async startNewSale(terminal, saleType = 'pos', dinnerTable = null) {
            const clientUuid = randomUuid();
            const cart = {
                client_uuid: clientUuid,
                id: null,
                status: 'active',
                terminal_id: terminal.id,
                stock_location_id: terminal.stock_location_id,
                sale_type: saleType,
                lines: [],
                payments: [],
                totals: zeroTotals(),
                currency: 'USD',
                pendingOps: 1,
                lastError: null,
                completedSale: null,
                completeIdempotencyKey: null,
                coupon_code: null,
                customer_id: null,
                customer: null,
                tip_amount: '0',
                dinner_table_id: dinnerTable?.id ?? null,
                dinner_table: dinnerTable ? { id: dinnerTable.id, name: dinnerTable.name } : null,
            };
            await saveCartSnapshot(cart);
            await enqueue('create_cart', clientUuid, {
                client_uuid: clientUuid,
                terminal_id: terminal.id,
                sale_type: saleType,
                dinner_table_id: dinnerTable?.id ?? null,
            });
            this.cart = cart;
            await this.sync();
        },

        async parkCurrent() {
            if (!this.cart) {
                return;
            }
            // Marks the cart suspended server-side (not just locally set aside)
            // so it becomes discoverable by other terminals via
            // `listOtherTerminalCarts()` -- without this it would only ever
            // reappear in this device's own `parked` list.
            this.cart.status = 'suspended';
            await this._touch();
            await enqueue('suspend', this.cart.client_uuid, {});
            await this.sync();
            this.cart = null;
            await this.refreshParked();
        },

        // Parking suspends the cart server-side, so resuming has to flip it
        // back to active before anything else will be accepted: add_line,
        // add_payment and complete all guard on STATUS_ACTIVE and would 422,
        // which the sync engine treats as fatal -- it discards every queued
        // op for the cart, losing the sale. `create_cart` is the reactivation
        // path: POST /carts with an existing client_uuid resolves to
        // CreateOrResumeCartAction's resume branch, which reactivates and
        // reattributes the cart, and the op patches the authoritative status
        // back onto the snapshot. Queueing it rather than calling the API
        // inline keeps resume working offline and guarantees it drains ahead
        // of whatever the cashier does next.
        async resume(clientUuid) {
            const snapshot = await getCartSnapshot(clientUuid);
            if (!snapshot) {
                return;
            }

            this.cart = snapshot;

            if (snapshot.status === 'suspended') {
                this.cart.status = 'active';
                await this._touch();
                await enqueue('create_cart', clientUuid, {
                    client_uuid: clientUuid,
                    terminal_id: snapshot.terminal_id,
                });
            }

            await this.sync();
        },

        async deleteParked(clientUuid) {
            const snapshot = await getCartSnapshot(clientUuid);
            if (!snapshot) {
                return;
            }
            snapshot.status = 'abandoned';
            await saveCartSnapshot(snapshot);
            await enqueue('abandon', clientUuid, {});
            await this.refreshParked();
            await this.sync();
        },

        // Carts parked on other terminals have no local snapshot to flip
        // optimistically (see `resumeFromOtherTerminal` below) -- this is a
        // direct, online-only API call, same shape as that action.
        async abandonOtherTerminalCart(cartId) {
            await apiFetch(`/carts/${cartId}/abandon`, { method: 'POST' });
        },

        // Discovers a cart parked by a *different* terminal (server-sourced,
        // unlike `parked`, which is this device's own IndexedDB list).
        async listOtherTerminalCarts(terminalId) {
            const res = await apiFetch(`/carts?terminal_id=${terminalId}`);
            return res.data;
        },

        // Resuming a cart this device has never seen has no local snapshot to
        // read, so — unlike every other mutation here — this calls the API
        // directly rather than going through the offline queue: there is
        // nothing to optimistically apply locally until the server responds
        // with the cart's actual state. `POST /carts` with an existing
        // client_uuid resolves to the same resume path `store()` already
        // exercises server-side, reattributing terminal/shift/user to this
        // device.
        async resumeFromOtherTerminal(clientUuid, terminalId) {
            const res = await apiFetch('/carts', {
                method: 'POST',
                body: { client_uuid: clientUuid, terminal_id: terminalId },
            });
            const cart = {
                ...res.data,
                pendingOps: 0,
                lastError: null,
                completedSale: null,
                completeIdempotencyKey: null,
                lines: res.data.lines.map((line) => ({ ...line, _tempId: nextTempId() })),
                payments: res.data.payments.map((payment) => ({ ...payment, _tempId: nextTempId() })),
            };
            await saveCartSnapshot(cart);
            this.cart = cart;
            await this.sync();
        },

        async sync() {
            this.syncing = true;
            try {
                await processQueue();
            } finally {
                this.syncing = false;
            }
            if (this.cart) {
                const fresh = await getCartSnapshot(this.cart.client_uuid);
                if (fresh) {
                    // pendingOps is bumped optimistically per mutation and
                    // decremented as ops drain -- but an op that gets dropped
                    // (a remove_line that can't resolve its target) or
                    // cancelled (removeQueuedOpsForRef on a never-synced add)
                    // leaves it overcounting, which pins "syncing…" on for
                    // good. Reconcile against what's actually still queued.
                    fresh.pendingOps = await countPendingForCart(this.cart.client_uuid);
                    this.cart = fresh;
                    await saveCartSnapshot(this.cart);
                }
                await this.refreshTotals();
            }
            await this.refreshParked();
        },

        // Line/payment mutation endpoints only return the single row they
        // touched, never the cart's totals (only `create_cart`'s response
        // does, and only while the cart is still empty) — so totals would
        // otherwise never update after the first item is scanned. This
        // pulls the authoritative, CartPricer-computed totals once the
        // queue has drained. A failure here (offline, or nothing synced
        // yet) just leaves the last-known totals in place; the "estimated"
        // badge in the UI already communicates that they may be stale.
        async refreshTotals() {
            if (!this.cart?.id || this.cart.status === 'completed') {
                return;
            }
            try {
                const res = await apiFetch(`/carts/${this.cart.id}`);
                this.cart.totals = res.data.totals;
                this.cart.currency = res.data.currency;
                // Kitchen state is the one thing a different actor (the
                // kitchen display, possibly a different device entirely)
                // can change on a line without this register ever hearing
                // about it -- there's no push/broadcast in this app, so
                // pull it in here. A line already marked prepared must
                // never be merged into as if it were still open. Nothing
                // else about the line is touched -- everything else stays
                // purely optimistic/local by design.
                for (const serverLine of res.data.lines ?? []) {
                    const localLine = this.cart.lines.find((line) => line.id === serverLine.id);
                    if (localLine) {
                        localLine.kitchen_sent = serverLine.kitchen_sent;
                        localLine.kitchen_prepared = serverLine.kitchen_prepared;
                    }
                }
                await saveCartSnapshot(this.cart);
            } catch {
                // offline or transient — see comment above.
            }
        },

        async _touch() {
            this.cart.pendingOps = (this.cart.pendingOps ?? 0) + 1;
            await saveCartSnapshot(this.cart);
        },

        async addLine(item, quantity = '1', stockLotId = null, unitPrice = null) {
            if (!this.cart) {
                return;
            }
            // A line the kitchen has already prepared is never a merge
            // target -- mirrors AddCartLineAction server-side. Re-ordering
            // the same dish after it's done falls through to the "new
            // line" branch below instead of silently growing a closed
            // ticket's quantity.
            // The server may assign a FEFO stock lot after the first scan.
            // That automatic lot must not make the next scan look like a
            // different product locally: AddCartLineAction resolves the same
            // lot and merges it server-side. Only kit components and prepared
            // kitchen lines remain intentionally separate. An ordinary scan
            // (stockLotId null -- no explicit choice made) still merges into
            // any compatible line regardless of the lot the server already
            // resolved it to, same as before; only an *explicitly* chosen
            // lot (from the price picker) must match a line's lot exactly,
            // so two different chosen lots of the same item never merge into
            // one ambiguously-priced line.
            const existing = this.cart.lines.find(
                (line) => line.item_id === item.id && !line.item_kit_id && !line.kitchen_prepared
                    && (stockLotId === null || line.stock_lot_id === stockLotId),
            );
            if (existing) {
                existing.quantity = String(Number(existing.quantity) + Number(quantity));
                // Mirrors the server: more quantity on an already-fired line
                // is exactly what the kitchen needs to know about, so it
                // goes back to unsent rather than the badge lying that
                // everything on this cart has already reached the kitchen.
                existing.kitchen_sent = false;
                await this._touch();
                await enqueue('update_line', this.cart.client_uuid, { quantity: existing.quantity }, existing._tempId);
                await this.sync();
                return;
            }

            const tempId = nextTempId();
            this.cart.lines.push({
                _tempId: tempId,
                id: null,
                item_id: item.id,
                item_name: item.name,
                sku: item.sku,
                stock_lot_id: stockLotId,
                quantity: String(quantity),
                unit_price: unitPrice ?? item.unit_price,
                discount_value: '0',
                discount_type: null,
                price_overridden: false,
                stock_at_location: item.stock_at_location,
            });
            await this._touch();
            await enqueue('add_line', this.cart.client_uuid, { item_id: item.id, quantity: String(quantity), stock_lot_id: stockLotId }, tempId);
            await this.sync();
        },

        // Kit lines never merge with anything (mirrors the server's own
        // AddCartLineAction exclusion of item_kit_id-tagged lines) -- each
        // call adds a fresh, independent set of component lines. Discount
        // is only replicated optimistically for a percent kit discount
        // (exact regardless of how it's split); a fixed kit discount is
        // left at 0 here and arrives correct once `add_kit` syncs, same
        // "estimated until synced" tolerance `refreshTotals()` already
        // relies on elsewhere in this store.
        async addKit(kit, quantity = '1') {
            if (!this.cart) {
                return;
            }
            const catalog = useCatalogStore();
            const tempIds = [];

            for (const component of kit.items ?? []) {
                const item = catalog.items.find((candidate) => candidate.id === component.item_id);
                if (!item) {
                    continue;
                }
                const tempId = nextTempId();
                tempIds.push(tempId);
                this.cart.lines.push({
                    _tempId: tempId,
                    id: null,
                    item_id: item.id,
                    item_name: item.name,
                    sku: item.sku,
                    item_kit_id: kit.id,
                    stock_lot_id: null,
                    quantity: String(Number(component.quantity) * Number(quantity)),
                    unit_price: item.unit_price,
                    discount_value: kit.discount_type === 'percent' ? kit.discount_value : '0',
                    discount_type: kit.discount_type === 'percent' ? 'percent' : null,
                    price_overridden: false,
                    stock_at_location: item.stock_at_location,
                });
            }

            if (tempIds.length === 0) {
                return;
            }

            await this._touch();
            await enqueue('add_kit', this.cart.client_uuid, { item_kit_id: kit.id, quantity: String(quantity) }, tempIds);
            await this.sync();
        },

        // Bumping the quantity of a line the kitchen already prepared would
        // make a *closed* ticket silently read as a bigger order than what
        // was actually fired. Refused before anything is mutated or
        // enqueued -- rather than letting the server reject it, which
        // would otherwise wipe every other queued op on this cart via the
        // sync engine's generic op-failure handling (see drainQueue()).
        async incrementLine(line) {
            if (line.kitchen_prepared) {
                throw new Error('This item was already prepared by the kitchen. Add it again instead of increasing the quantity here.');
            }
            line.quantity = String(Number(line.quantity) + 1);
            line.kitchen_sent = false;
            await this._touch();
            await enqueue('update_line', this.cart.client_uuid, { quantity: line.quantity }, line._tempId);
            await this.sync();
        },

        async setLineQuantity(line, quantity) {
            if (Number(quantity) <= 0) {
                await this.removeLine(line);
                return;
            }
            if (line.kitchen_prepared && Number(quantity) > Number(line.quantity)) {
                throw new Error('This item was already prepared by the kitchen. Add it again instead of increasing the quantity here.');
            }
            line.quantity = String(quantity);
            if (!line.kitchen_prepared) {
                line.kitchen_sent = false;
            }
            await this._touch();
            await enqueue('update_line', this.cart.client_uuid, { quantity: line.quantity }, line._tempId);
            await this.sync();
        },

        async decrementLine(line) {
            const next = Number(line.quantity) - 1;
            if (next <= 0) {
                await this.removeLine(line);
                return;
            }
            line.quantity = String(next);
            if (!line.kitchen_prepared) {
                line.kitchen_sent = false;
            }
            await this._touch();
            await enqueue('update_line', this.cart.client_uuid, { quantity: line.quantity }, line._tempId);
            await this.sync();
        },

        async setLinePrice(line, unitPrice) {
            line.unit_price = String(unitPrice);
            line.price_overridden = true;
            await this._touch();
            await enqueue('update_line', this.cart.client_uuid, { unit_price: line.unit_price, price_overridden: true }, line._tempId);
            await this.sync();
        },

        async setLineNote(line, description) {
            line.description = description;
            if (!line.kitchen_prepared) {
                line.kitchen_sent = false;
            }
            await this._touch();
            await enqueue('update_line', this.cart.client_uuid, { description }, line._tempId);
            await this.sync();
        },

        async setLineSerial(line, serial) {
            line.serial = serial;
            await this._touch();
            await enqueue('update_line', this.cart.client_uuid, { serial }, line._tempId);
            await this.sync();
        },

        async removeLine(line) {
            this.cart.lines = this.cart.lines.filter((candidate) => candidate._tempId !== line._tempId);
            await this._touch();
            if (line.id) {
                // Already confirmed created server-side: a real DELETE has
                // to go out. Carry the server id on the op itself -- the
                // line is filtered out of the snapshot above, so the drain
                // can no longer look it up by _tempId.
                await enqueue('remove_line', this.cart.client_uuid, { id: line.id }, line._tempId);
            } else {
                // Never confirmed created server-side yet -- cancel
                // whatever queued op(s) would have created/updated it
                // instead of queuing a delete for something the delete op
                // has no id to find. Enqueueing remove_line here used to
                // silently no-op at drain time while still reporting
                // success, discarding the removal (see engine.js's
                // remove_line case).
                await removeQueuedOpsForRef(this.cart.client_uuid, line._tempId);
            }
            await this.sync();
        },

        async addPayment(method, amount, tendered, reference) {
            const tempId = nextTempId();
            this.cart.payments.push({
                _tempId: tempId,
                id: null,
                payment_method_id: method.id,
                method_code: method.code,
                amount: String(amount),
                tendered: tendered != null && tendered !== '' ? String(tendered) : null,
                reference: reference || null,
            });
            await this._touch();
            await enqueue(
                'add_payment',
                this.cart.client_uuid,
                {
                    payment_method_id: method.id,
                    amount: String(amount),
                    ...(tendered != null && tendered !== '' ? { tendered: String(tendered) } : {}),
                    ...(reference ? { reference } : {}),
                    // Unlike add_line/create_cart, this op has direct
                    // financial impact if a dropped-response retry (see
                    // client.js's timeout) or a cross-tab race (see
                    // engine.js's runExclusive() fallback for browsers
                    // without navigator.locks) resends it -- the tempId is
                    // already a unique value minted once per payment, so
                    // it doubles as the idempotency key with nothing extra
                    // to generate or store.
                    idempotency_key: tempId,
                },
                tempId,
            );
            await this.sync();
        },

        async removePayment(payment) {
            this.cart.payments = this.cart.payments.filter((candidate) => candidate._tempId !== payment._tempId);
            await this._touch();
            if (payment.id) {
                await enqueue('remove_payment', this.cart.client_uuid, { id: payment.id }, payment._tempId);
            } else {
                // Same reasoning as removeLine() above: nothing was ever
                // created server-side yet, so cancel the queued add_payment
                // instead of queuing a delete that would silently find
                // nothing to delete.
                await removeQueuedOpsForRef(this.cart.client_uuid, payment._tempId);
            }
            await this.sync();
        },

        async applyCoupon(code) {
            await this._touch();
            await enqueue('apply_coupon', this.cart.client_uuid, { code });
            await this.sync();
        },

        async removeCoupon() {
            await this._touch();
            await enqueue('remove_coupon', this.cart.client_uuid, {});
            await this.sync();
        },

        // Same shape as applyCoupon -- optimistic single-field patch, queued
        // and synced like every other cart mutation. Server-side this is
        // gated by sales.checkout, same as taking a payment.
        async setTip(amount) {
            this.cart.tip_amount = String(amount);
            await this._touch();
            await enqueue('set_tip', this.cart.client_uuid, { tip_amount: String(amount) });
            await this.sync();
        },

        // Read-only, online-only — there is no offline customer cache (unlike
        // catalog.js's item cache), so a search simply can't resolve offline.
        async searchCustomers(query) {
            try {
                const res = await apiFetch(`/customers?q=${encodeURIComponent(query)}`);
                return res.data;
            } catch (e) {
                if (e instanceof ApiError) {
                    return [];
                }
                throw e;
            }
        },

        async attachCustomer(customer) {
            this.cart.customer_id = customer.id;
            this.cart.customer = customer;
            await this._touch();
            await enqueue('attach_customer', this.cart.client_uuid, { customer_id: customer.id });
            await this.sync();
        },

        async removeCustomer() {
            this.cart.customer_id = null;
            this.cart.customer = null;
            await this._touch();
            await enqueue('remove_customer', this.cart.client_uuid, {});
            await this.sync();
        },

        async completeSale() {
            if (!this.cart) {
                return false;
            }
            if (!this.cart.completeIdempotencyKey) {
                this.cart.completeIdempotencyKey = randomUuid();
            }
            await this._touch();
            await enqueue('complete', this.cart.client_uuid, { idempotency_key: this.cart.completeIdempotencyKey });
            await this.sync();
            return this.cart?.status === 'completed';
        },

        async finishAndReset() {
            if (this.cart) {
                await deleteCartSnapshot(this.cart.client_uuid);
            }
            this.cart = null;
            await this.refreshParked();
        },
    },
});
