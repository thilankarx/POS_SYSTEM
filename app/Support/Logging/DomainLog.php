<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Domain exceptions are business-rule refusals: they are caught at the
 * Livewire boundary and rendered as a message next to the field that caused
 * them, so they never reach Laravel's exception handler and, until this
 * existed, left no trace anywhere at all.
 *
 * That is fine for "you forgot a condition" and badly wrong for "the label
 * printer refused the connection" -- both took the same silent path. Logging
 * them here gives support a record of what the system refused and to whom,
 * without the noise of a stack trace: these are expected outcomes, so they
 * are warnings, not errors.
 */
final class DomainLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function refused(Throwable $e, array $context = []): void
    {
        Log::warning($e->getMessage(), array_merge([
            'exception' => $e::class,
            'user_id' => auth()->id(),
        ], $context));
    }
}
