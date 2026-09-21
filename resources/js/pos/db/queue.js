import { getDb } from './database.js';

/**
 * `seq` is the IndexedDB store's auto-increment keyPath, so `getAllQueue()`
 * (backed by `getAll()`) is guaranteed to return rows in ascending seq
 * order — that ordering is what the sync engine relies on to replay
 * mutations against a cart in the order the cashier made them.
 */
export async function enqueue(type, clientCartUuid, payload, localRef = null) {
    const db = await getDb();
    return db.add('queue', { type, clientCartUuid, payload, localRef, createdAt: Date.now() });
}

export async function getAllQueue() {
    const db = await getDb();
    return db.getAll('queue');
}

export async function dequeue(seq) {
    const db = await getDb();
    await db.delete('queue', seq);
}

export async function removeOpsForCart(clientCartUuid) {
    const db = await getDb();
    const all = await db.getAll('queue');
    await Promise.all(
        all.filter((op) => op.clientCartUuid === clientCartUuid).map((op) => db.delete('queue', op.seq)),
    );
}

/**
 * Cancels every not-yet-sent queued op referencing `localRef` (a line's or
 * payment's `_tempId`) for this cart. Used when the cashier removes
 * something before its own `add_line`/`add_payment` has synced: the correct
 * effect is "it never happened", not "create it, then try to delete
 * something the delete op can't find" -- which used to report success
 * without ever sending the DELETE, silently leaving the item live
 * server-side. Returns how many ops were cancelled, so the caller can tell
 * whether there was anything left to cancel (there may be none, if the
 * create op is already mid-flight -- see runOp()'s remove_line/remove_payment
 * comment in sync/engine.js for that narrower residual race).
 */
export async function removeQueuedOpsForRef(clientCartUuid, localRef) {
    const db = await getDb();
    const all = await db.getAll('queue');
    const matching = all.filter((op) => op.clientCartUuid === clientCartUuid && op.localRef === localRef);
    await Promise.all(matching.map((op) => db.delete('queue', op.seq)));

    return matching.length;
}

export async function countPendingForCart(clientCartUuid) {
    const all = await getAllQueue();
    return all.filter((op) => op.clientCartUuid === clientCartUuid).length;
}
