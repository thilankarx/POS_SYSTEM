<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Category;
use App\Domain\Documents\DocumentNumberGenerator;

/**
 * Items no longer take a hand-typed SKU. It is generated as
 * "{CATEGORY_CODE}-{sequence}" (e.g. FAST-000001), or "GEN-{sequence}" for
 * items with no category / no category code. The category code doubles as
 * the DocumentNumberGenerator "scope" for the 'item_sku' sequence key, so
 * each category gets its own gap-free counter with no new table.
 */
final class GenerateItemSkuAction
{
    public const DEFAULT_SCOPE = 'GEN';

    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    /**
     * Non-mutating preview for the UI -- call this on every category change
     * before saving.
     */
    public function preview(?Category $category): string
    {
        return $this->numbers->peek('item_sku', $this->scopeFor($category));
    }

    /**
     * Consumes (burns) the next number for the category. Call exactly once,
     * inside the same transaction as the Item insert.
     */
    public function execute(?Category $category): string
    {
        return $this->numbers->next('item_sku', $this->scopeFor($category));
    }

    private function scopeFor(?Category $category): string
    {
        $code = $category?->code;

        return $code !== null && $code !== '' ? strtoupper($code) : self::DEFAULT_SCOPE;
    }
}
