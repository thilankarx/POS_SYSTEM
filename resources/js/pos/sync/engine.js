import { apiFetch, ApiError } from '../api/client.js';
import { getCartSnapshot, saveCartSnapshot } from '../db/database.js';
import { getAllQueue, dequeue, removeOpsForCart } from '../db/queue.js';

/**
 * Every queued op carries `localRef`: the target line/payment's permanent
 * client-side `_tempId` (never the server id, which starts null and is only
 * known after the op that creates it has synced) — except `add_kit`, whose
 * `localRef` is an *array* of `_tempId`s, one per optimistic component
 * line, since a single kit add produces N lines from one op. Because the
 * queue is drained strictly in the order operations were made, by the time
 * an op referencing a given `_tempId` runs, the op that created it has
 * already completed and populated `.id` — so resolving ids at *processing*
 * time here, rather than at enqueue time, is always safe.
 */
async function runOp(op, snapshot) {
    switch (op.type) {
        case 'create_cart': {
            const res = await apiFetch('/carts', { method: 'POST', body: op.payload });
            return { patch: { id: res.data.id, status: res.data.status } };
        }
        case 'add_line': {
            const res = await apiFetch(`/carts/${snapshot.id}/lines`, { method: 'POST', body: op.payload });
            return { lineUpdate: { tempId: op.localRef, data: res.data } };
        }
        case 'update_line': {
            const line = snapshot.lines.find((candidate) => candidate._tempId === op.localRef);
            if (!line?.id) {
                // The line's own add_line hasn't resolved yet (normal --
                // see the docblock above), or its _tempId no longer exists
                // at all, e.g. resumeFromOtherTerminal() re-minted every
                // _tempId out from under a still-queued edit. Either way we
                // cannot resolve *which* server line to PATCH. Signal the
                // drain to drop just this op (not the whole cart's queue --
                // every other queued op is unaffected) and tell the cashier,
                // rather than silently discarding the edit while reporting
                // success.
                return { dropOpOnly: true, message: 'A queued line edit could no longer be matched and was dropped; please redo the change if it is still needed.' };
            }
            await apiFetch(`/carts/${snapshot.id}/lines/${line.id}`, { method: 'PATCH', body: op.payload });
            // A still-in-flight add_line for this same line can resolve after
            // this update was enqueued and overwrite the whole line (including
            // quantity) with the server's original value -- re-apply this
            // op's own payload on top so a queued edit never gets silently
            // discarded by an earlier op that finishes later.
            return { lineUpdate: { tempId: op.localRef, data: op.payload } };
        }
        case 'add_kit': {
            const res = await apiFetch(`/carts/${snapshot.id}/kit-lines`, { method: 'POST', body: op.payload });
            return { kitLines: { tempIds: op.localRef, data: res.data } };
        }
        case 'remove_line': {
            // cart.js filters the line out of the local cart before this op
            // is enqueued, so it is normally gone from the snapshot by the
            // time we get here -- the server id it carries in its payload is
            // what makes the DELETE resolvable. The snapshot lookup is only
            // a fallback for the narrow window where the line's own add_line
            // is still mid-flight (id not yet assigned anywhere). If neither
            // yields an id, a silent no-op would report success without a
            // DELETE, leaving the item live server-side -- surface it.
            const lineId = op.payload?.id
                ?? snapshot.lines.find((candidate) => candidate._tempId === op.localRef)?.id;
            if (!lineId) {
                return { dropOpOnly: true, message: 'A queued item removal could not be confirmed; check the cart before completing the sale.' };
            }
            await apiFetch(`/carts/${snapshot.id}/lines/${lineId}`, { method: 'DELETE' });
            return { removeLine: op.localRef };
        }
        case 'add_payment': {
            const { idempotency_key: idempotencyKey, ...body } = op.payload;
            const res = await apiFetch(`/carts/${snapshot.id}/payments`, { method: 'POST', body, idempotencyKey });
            return { paymentUpdate: { tempId: op.localRef, data: res.data } };
        }
        case 'remove_payment': {
            // Same as remove_line: the payment is filtered out locally before
            // this op is enqueued, so the id it carries in its payload is
            // what resolves the DELETE; the snapshot lookup is only a
            // mid-flight fallback.
            const paymentId = op.payload?.id
                ?? snapshot.payments.find((candidate) => candidate._tempId === op.localRef)?.id;
            if (paymentId) {
                await apiFetch(`/carts/${snapshot.id}/payments/${paymentId}`, { method: 'DELETE' });
            }
            return { removePayment: op.localRef };
        }
        case 'suspend': {
            await apiFetch(`/carts/${snapshot.id}/suspend`, { method: 'POST' });
            return { patch: { status: 'suspended' } };
        }
        case 'abandon': {
            await apiFetch(`/carts/${snapshot.id}/abandon`, { method: 'POST' });
            return { patch: { status: 'abandoned' } };
        }
        case 'apply_coupon': {
            const res = await apiFetch(`/carts/${snapshot.id}/coupon`, { method: 'PATCH', body: op.payload });
            return { patch: { coupon_code: res.data.coupon_code } };
        }
        case 'remove_coupon': {
            const res = await apiFetch(`/carts/${snapshot.id}/coupon`, { method: 'DELETE' });
            return { patch: { coupon_code: res.data.coupon_code } };
        }
        case 'set_tip': {
            const res = await apiFetch(`/carts/${snapshot.id}/tip`, { method: 'PATCH', body: op.payload });
            return { patch: { tip_amount: res.data.tip_amount } };
        }
        case 'attach_customer': {
            const res = await apiFetch(`/carts/${snapshot.id}/customer`, { method: 'PATCH', body: op.payload });
            return { patch: { customer_id: res.data.customer_id, customer: res.data.customer } };
        }
        case 'remove_customer': {
            const res = await apiFetch(`/carts/${snapshot.id}/customer`, { method: 'DELETE' });
            return { patch: { customer_id: res.data.customer_id, customer: res.data.customer } };
        }
        case 'complete': {
            const res = await apiFetch(`/carts/${snapshot.id}/complete`, {
                method: 'POST',
                body: {},
                idempotencyKey: op.payload.idempotency_key,
            });
            return { complete: res.data };
        }
        default:
            throw new Error(`Unknown queued operation type: ${op.type}`);
    }
}

function applyResult(snapshot, result) {
    if (result.patch) {
        Object.assign(snapshot, result.patch);
    }
    if (result.lineUpdate) {
        const line = snapshot.lines.find((candidate) => candidate._tempId === result.lineUpdate.tempId);
        if (line) {
            Object.assign(line, result.lineUpdate.data, { _tempId: line._tempId });

            // AddCartLineAction merges a same-item/same-lot add_line into an
            // existing server row instead of creating a second one (see its
            // own docblock) -- when two *separate* queued add_line ops for
            // the same item both resolve, the later one's response is that
            // same merged row, so this line and an earlier op's line now
            // share one server id. Without this, the cart would keep both
            // as separate rows -- one holding the stale pre-merge quantity
            // -- double-counting the item locally (and, if the cashier
            // removes what looks like the "other" line, deleting the whole
            // merged row instead of the smaller amount they meant to). Keep
            // only this line (it just received the authoritative merged
            // data) and drop any other local line now sharing its id.
            if (line.id != null) {
                snapshot.lines = snapshot.lines.filter(
                    (candidate) => candidate._tempId === line._tempId || candidate.id !== line.id,
                );
            }
        }
    }
    if (result.kitLines) {
        result.kitLines.tempIds.forEach((tempId, index) => {
            const line = snapshot.lines.find((candidate) => candidate._tempId === tempId);
            const data = result.kitLines.data[index];
            if (line && data) {
                Object.assign(line, data, { _tempId: line._tempId });
            }
        });
    }
    if (result.removeLine) {
        snapshot.lines = snapshot.lines.filter((candidate) => candidate._tempId !== result.removeLine);
    }
    if (result.paymentUpdate) {
        const payment = snapshot.payments.find((candidate) => candidate._tempId === result.paymentUpdate.tempId);
        if (payment) {
            Object.assign(payment, result.paymentUpdate.data, { _tempId: payment._tempId });
        }
    }
    if (result.removePayment) {
        snapshot.payments = snapshot.payments.filter((candidate) => candidate._tempId !== result.removePayment);
    }
    if (result.complete) {
        snapshot.status = 'completed';
        snapshot.completedSale = result.complete;
    }
}

/**
 * Drains the whole queue, one request at a time, strictly in seq order.
 *
 * `error.isNetworkError` decides retryable-later vs fatal-for-this-cart --
 * and it is true whenever `fetch()` itself rejected, which covers both "the
 * request never reached the server" AND "it was sent but the response was
 * lost" (a dropped connection mid-response looks identical to the browser).
 * A retry in that second case can re-run an op that already landed
 * server-side; `complete` is safe to retry regardless, because it always
 * carries an `Idempotency-Key` the server de-duplicates on, but the other
 * op types have no such key and a retried add_line/add_payment/create_cart
 * can create a second row. That residual double-apply risk is accepted
 * (client.js's own 401 and parse-failure handling closes off the specific
 * cases that turned it into forever-retrying, at least), not eliminated --
 * eliminating it fully means giving every mutating cart endpoint its own
 * idempotency key, which these do not have yet.
 *
 * A real (non-network) error response is not transient: the cart's local
 * state has diverged from the server's (e.g. it's no longer active), so
 * every remaining queued op for that *specific* cart is dropped and the
 * error recorded on its snapshot for the UI to surface — but other carts'
 * queued ops, if any, keep draining. `complete` is the one exception: a 5xx
 * on it is treated as retryable rather than fatal, since destroying the
 * queued sale on a transient server error is worse than the residual
 * double-apply risk above, and the Idempotency-Key means a retry is safe
 * either way.
 *
 * Called from several independent places (every cart mutation's own
 * `sync()`, the app's reconnect handler, boot) that can overlap in time —
 * e.g. reconnecting fires a drain in the background while the cashier is
 * already tapping "Add payment", which enqueues and calls this again before
 * the first drain reaches that op. Two concurrent loops both reading
 * `getCartSnapshot()` around the same time could race: one could read a
 * cart's server `id` as still-null because the other's `create_cart`
 * response hadn't been persisted yet. `inFlight` collapses concurrent
 * callers onto the one active drain, which is safe because its loop always
 * re-reads the queue from IndexedDB fresh each iteration — anything
 * enqueued after the drain started is still picked up.
 *
 * `inFlight` alone only excludes callers *within this tab* -- IndexedDB is
 * shared across every tab of the origin, so two tabs of the register (or
 * the PWA plus a browser tab left open) each hold their own `inFlight` and
 * would each read and act on the same queue row before either had deleted
 * it, double-sending it. `navigator.locks` extends the same exclusion
 * across tabs; browsers without the Web Locks API just fall back to
 * `inFlight`'s single-tab guarantee, same as before.
 */
let inFlight = null;

// A single pending "retry after the rate-limit window resets" timer. The
// drain stops the moment it hits a 429 (keeping the whole queue); without
// this, a cart left idle afterwards would not sync again until the
// cashier's next action. Bounded, and coalesced so repeated 429s in one
// pass don't stack timers.
let retryTimer = null;
function scheduleRetry(retryAfterSeconds) {
    if (retryTimer || typeof setTimeout === 'undefined') {
        return;
    }
    const delayMs = Math.min(Math.max(retryAfterSeconds || 2, 1), 30) * 1000;
    retryTimer = setTimeout(() => {
        retryTimer = null;
        processQueue();
    }, delayMs);
}

export function processQueue() {
    if (!inFlight) {
        inFlight = runExclusive().finally(() => {
            inFlight = null;
        });
    }
    return inFlight;
}

function runExclusive() {
    if (typeof navigator !== 'undefined' && navigator.locks?.request) {
        return navigator.locks.request('pos-sync-queue', () => drainQueue());
    }

    return drainQueue();
}

async function drainQueue() {
    // Carts whose create_cart failed this pass (see below) -- skipped for
    // the rest of the loop so they don't block every *other* cart's ops
    // behind them, without deleting anything.
    const blockedCarts = new Set();

    for (;;) {
        const ops = await getAllQueue();
        const op = ops.find((candidate) => !blockedCarts.has(candidate.clientCartUuid));
        if (!op) {
            return;
        }
        const snapshot = await getCartSnapshot(op.clientCartUuid);
        if (!snapshot) {
            await dequeue(op.seq);
            continue;
        }

        let result;
        try {
            result = await runOp(op, snapshot);
        } catch (error) {
            // A 429 from the API rate limiter is always transient -- the
            // window resets within seconds. Leave the entire queue intact
            // (for every cart, not just this one), schedule a retry, and
            // stop this pass. Dropping queued ops here would lose sales
            // purely because the cashier scanned quickly.
            if (error instanceof ApiError && error.isRateLimited) {
                scheduleRetry(error.retryAfter);
                return;
            }

            // Nothing has been created server-side for this cart yet, so
            // there is nothing to lose by leaving its queue intact -- unlike
            // every other op, wiping it here would only destroy data for no
            // benefit. The common cause is no shift being open yet on this
            // terminal (an offline sale syncing before the morning shift is
            // opened): once one is, the retry succeeds and the rest of this
            // cart's queue drains normally. Previously this cart's *entire*
            // queue -- every line, every payment, the sale itself -- was
            // deleted the instant create_cart failed for any reason.
            if (op.type === 'create_cart' && error instanceof ApiError && !error.isNetworkError) {
                snapshot.lastError = error.message;
                await saveCartSnapshot(snapshot);
                blockedCarts.add(op.clientCartUuid);
                continue;
            }

            // A 5xx on `complete` is retried like a network error rather
            // than treated as fatal: the sale may well have already
            // committed server-side (a proxy can drop the response after
            // the transaction succeeds), and its Idempotency-Key makes a
            // retry safe either way -- replayed if it committed, applied
            // once if it didn't. Destroying the queued sale here is exactly
            // the "ring it again on a new cart, bank it twice" failure mode
            // this key exists to prevent.
            const isRetryableCompleteError = op.type === 'complete' && error instanceof ApiError && error.status >= 500;

            if (error instanceof ApiError && !error.isNetworkError && !isRetryableCompleteError) {
                snapshot.lastError = error.message;
                snapshot.pendingOps = 0;
                await saveCartSnapshot(snapshot);
                await removeOpsForCart(snapshot.client_uuid);
                continue;
            }
            return;
        }

        if (result.dropOpOnly) {
            // Only this op is abandoned -- every other queued op for the
            // cart (and the cart itself) is untouched and keeps draining
            // normally. Still count it against pendingOps: it bumped the
            // counter when it was enqueued, and leaving it uncounted pins
            // the "syncing…" badge on with an empty queue behind it.
            snapshot.lastError = result.message;
            snapshot.pendingOps = Math.max(0, (snapshot.pendingOps ?? 1) - 1);
            await saveCartSnapshot(snapshot);
            await dequeue(op.seq);
            continue;
        }

        applyResult(snapshot, result);
        snapshot.pendingOps = Math.max(0, (snapshot.pendingOps ?? 1) - 1);
        snapshot.lastError = null;
        await saveCartSnapshot(snapshot);
        await dequeue(op.seq);

        if (op.type === 'complete') {
            await removeOpsForCart(snapshot.client_uuid);
        }
    }
}
