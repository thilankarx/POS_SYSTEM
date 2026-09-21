import { getItem, removeItem } from '../stores/storage.js';

const BASE = '/api/v1';
const REQUEST_TIMEOUT_MS = 15000;
const PING_TIMEOUT_MS = 5000;

// Reachability probe for the connectivity store's heartbeat. Deliberately
// bypasses everything apiFetch does -- token, JSON parsing, ApiError
// translation -- because it only needs a yes/no on "did this server
// answer", and it has to keep working on the terminal-picker screen
// before any token exists. A slow or black-holed link resolves to `false`
// via the timeout rather than hanging the poll loop.
export async function pingServer(timeoutMs = PING_TIMEOUT_MS) {
    if (typeof navigator !== 'undefined' && !navigator.onLine) {
        return false;
    }
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const response = await fetch(`${BASE}/ping`, {
            method: 'GET',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: controller.signal,
        });
        return response.ok;
    } catch {
        return false;
    } finally {
        clearTimeout(timeout);
    }
}

export class ApiError extends Error {
    constructor(message, status, body, isNetworkError = false) {
        super(message);
        this.status = status;
        this.body = body;
        this.isNetworkError = isNetworkError;
        // Set only for a 429 from the API rate limiter. Kept distinct from
        // isNetworkError: the request *did* reach the server, but the
        // condition is transient (the window resets in seconds), so the
        // sync engine must treat it as retry-later, never as
        // fatal-for-this-cart.
        this.isRateLimited = false;
        this.retryAfter = null;
    }
}

export async function apiFetch(path, { method = 'GET', body, idempotencyKey } = {}) {
    const headers = { Accept: 'application/json' };
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }
    const token = getItem('token');
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    if (idempotencyKey) {
        headers['Idempotency-Key'] = idempotencyKey;
    }

    // Without a timeout, a captive portal or a black-holed connection leaves
    // this fetch pending forever -- and because processQueue() reuses the
    // same in-flight drain promise for every caller, one hung request stalls
    // sync for the whole app until the page is reloaded.
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

    let response;
    try {
        response = await fetch(BASE + path, {
            method,
            headers,
            body: body !== undefined ? JSON.stringify(body) : undefined,
            signal: controller.signal,
        });
    } catch {
        // Covers both an outright connection failure and our own timeout
        // abort -- both are "we don't know what happened server-side",
        // exactly the case isNetworkError already exists to express.
        throw new ApiError('Network error', 0, null, true);
    } finally {
        clearTimeout(timeout);
    }

    if (response.status === 401) {
        removeItem('token');
        // isNetworkError: true is deliberate here, even though the request
        // did reach the server -- it routes this into the sync engine's
        // "stop draining, keep the op queued, retry later" path instead of
        // its "this cart's state has genuinely diverged, drop everything
        // queued for it" path. An expired token is exactly the retryable
        // case: the queued sale is still perfectly valid, the caller just
        // needs to re-authenticate first. Treating it as fatal used to
        // delete every queued offline sale the moment a shift-long token
        // expired.
        throw new ApiError('Unauthorized', 401, null, true);
    }

    if (response.status === 429) {
        // API rate limiter tripped (a burst of fast scans, say). Transient
        // by definition -- surface it as its own kind so the sync engine
        // keeps every queued op and retries once the window resets, instead
        // of dropping the cart's queue the way it does for a genuine 4xx.
        const retryAfter = Number(response.headers.get('Retry-After')) || null;
        const err = new ApiError(
            'The register is sending requests faster than the server allows. It will catch up in a moment.',
            429,
            null,
        );
        err.isRateLimited = true;
        err.retryAfter = retryAfter;
        throw err;
    }

    if (response.status === 204) {
        return null;
    }

    let data = null;
    let parseFailed = false;
    try {
        data = await response.json();
    } catch {
        parseFailed = true;
    }

    if (!response.ok) {
        throw new ApiError(data?.message ?? 'Request failed', response.status, data);
    }

    if (parseFailed) {
        // A 2xx whose body couldn't be read (truncated response, a proxy
        // that swallowed it, ...) is not safely retryable the way a network
        // error is: the request may well have already been written
        // server-side, and most queued ops (add_line, add_payment,
        // create_cart) have no Idempotency-Key, so blindly retrying would
        // create a second row every attempt. Fatal-for-this-cart, not
        // silently retried forever.
        throw new ApiError('The server response could not be read.', response.status, null);
    }

    return data;
}
