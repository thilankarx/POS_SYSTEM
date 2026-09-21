<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Documents\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/**
 * Allocates gap-free document numbers under a row lock.
 *
 * OSPOS rendered these from a token template and then wrote the counter back
 * into the `app_config` key/value table as a string
 * (`last_used_invoice_number`), read-modify-write with no lock. Two cashiers
 * completing a sale in the same instant could be issued the same invoice
 * number -- which, for an invoice number, is a compliance problem, not just a
 * bug.
 */
final class DocumentNumberGenerator
{
    /**
     * @param  string  $key  invoice | quote | work_order | sale | receiving | ...
     * @param  string|null  $scope  e.g. '2026' for sequences that reset yearly
     */
    public function next(string $key, ?string $scope = null): string
    {
        $scope ??= 'global';
        $config = config("pos.documents.sequences.{$key}", []);

        return DB::transaction(function () use ($key, $scope, $config) {
            $sequence = DocumentSequence::query()
                ->where('key', $key)
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = DocumentSequence::create([
                    'key' => $key,
                    'scope' => $scope,
                    'prefix' => $config['prefix'] ?? strtoupper(substr($key, 0, 3)),
                    'padding' => $config['padding'] ?? 6,
                    'next_value' => 1,
                ]);

                // Re-read under the lock so concurrent creators serialise.
                $sequence = DocumentSequence::query()
                    ->whereKey($sequence->id)
                    ->lockForUpdate()
                    ->first();
            }

            $value = (int) $sequence->next_value;
            $sequence->update(['next_value' => $value + 1]);

            return $this->format($sequence, $value, $scope);
        });
    }

    /**
     * Read-only preview of what next() would return, without locking,
     * creating, or incrementing anything. Safe to call repeatedly (e.g. on
     * every category-dropdown change in a form) without skipping numbers.
     */
    public function peek(string $key, ?string $scope = null): string
    {
        $scope ??= 'global';
        $config = config("pos.documents.sequences.{$key}", []);

        $sequence = DocumentSequence::query()
            ->where('key', $key)
            ->where('scope', $scope)
            ->first();

        if ($sequence === null) {
            $sequence = new DocumentSequence([
                'key' => $key,
                'scope' => $scope,
                'prefix' => $config['prefix'] ?? strtoupper(substr($key, 0, 3)),
                'padding' => $config['padding'] ?? 6,
                'next_value' => 1,
            ]);
        }

        return $this->format($sequence, (int) $sequence->next_value, $scope);
    }

    private function format(DocumentSequence $sequence, int $value, string $scope): string
    {
        $number = str_pad((string) $value, (int) $sequence->padding, '0', STR_PAD_LEFT);
        $parts = array_filter([
            $sequence->prefix,
            $scope !== 'global' ? $scope : null,
            $number,
        ]);

        return implode('-', $parts);
    }
}
