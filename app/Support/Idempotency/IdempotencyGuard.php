<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Makes a money-moving endpoint safely replayable under a client-supplied
 * `Idempotency-Key`. The guard wraps its own transaction around the callback
 * so the resulting row (e.g. a Sale) and the cached response commit or roll
 * back together — the key/response row commits only if the callback's own
 * writes do too, so a crash between them can't leave a cached response for a
 * mutation that never actually happened.
 *
 * Keys are scoped per user (`idempotency_keys` is uniquely keyed on
 * (user_id, key), not on key alone) — a terminal that mints keys from a
 * small counter instead of a UUID must only ever collide with its own
 * requests, never block a different terminal's legitimate one.
 */
final class IdempotencyGuard
{
    /**
     * @param  Closure(): array{status: int, body: mixed}  $callback
     * @return array{status: int, body: mixed}
     */
    public function handle(string $key, string $endpoint, string $requestHash, ?int $userId, Closure $callback): array
    {
        return DB::transaction(function () use ($key, $endpoint, $requestHash, $userId, $callback) {
            $existing = IdempotencyKey::where('key', $key)->where('user_id', $userId)->lockForUpdate()->first();
            $combinedHash = hash('sha256', $endpoint.'|'.$requestHash);

            if ($existing !== null && $existing->expires_at->isFuture()) {
                if ($existing->request_hash !== $combinedHash) {
                    throw IdempotencyConflictException::mismatchedRequest();
                }

                return $existing->response;
            }

            $result = $callback();

            IdempotencyKey::updateOrCreate(['key' => $key, 'user_id' => $userId], [
                'endpoint' => $endpoint,
                'request_hash' => $combinedHash,
                'response' => $result,
                'expires_at' => now()->addHours((int) config('pos.idempotency.ttl_hours', 48)),
            ]);

            return $result;
        });
    }
}
