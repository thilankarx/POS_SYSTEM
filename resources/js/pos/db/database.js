import { openDB } from 'idb';

const DB_NAME = 'pos-offline';
const DB_VERSION = 1;

let dbPromise = null;

export function getDb() {
    if (!dbPromise) {
        dbPromise = openDB(DB_NAME, DB_VERSION, {
            upgrade(db) {
                if (!db.objectStoreNames.contains('queue')) {
                    db.createObjectStore('queue', { keyPath: 'seq', autoIncrement: true });
                }
                if (!db.objectStoreNames.contains('cart_snapshot')) {
                    db.createObjectStore('cart_snapshot', { keyPath: 'client_uuid' });
                }
            },
        });
    }
    return dbPromise;
}

export async function getCartSnapshot(clientUuid) {
    const db = await getDb();
    return db.get('cart_snapshot', clientUuid);
}

export async function saveCartSnapshot(snapshot) {
    const db = await getDb();
    // `snapshot` is usually a Pinia store's reactive state object (a Vue
    // Proxy) — IndexedDB's structured clone algorithm throws a
    // DataCloneError on that in Chromium, so it has to be unwrapped to a
    // plain object first. A JSON round-trip is enough here since the
    // snapshot only ever holds plain data (strings/numbers/booleans/null),
    // never Dates, Maps, or functions.
    await db.put('cart_snapshot', JSON.parse(JSON.stringify(snapshot)));
}

export async function deleteCartSnapshot(clientUuid) {
    const db = await getDb();
    await db.delete('cart_snapshot', clientUuid);
}

export async function getAllCartSnapshots() {
    const db = await getDb();
    return db.getAll('cart_snapshot');
}
