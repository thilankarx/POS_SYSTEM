<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `idempotency_keys.key` was globally unique, so any two callers that ever
 * picked the same key value -- e.g. two terminals both minting keys from a
 * small counter instead of a UUID -- collided. The first caller's key wins
 * and every subsequent caller with that value gets a mismatched-request 409
 * for the key's whole TTL, even against a completely unrelated cart, on a
 * completely legitimate request. Scoping the uniqueness to (user_id, key)
 * keeps a key colliding only within one user's own requests, which is what
 * "idempotency key" is supposed to mean.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['user_id', 'key']);
            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'key']);
            $table->dropIndex(['key']);
            $table->unique('key');
        });
    }
};
