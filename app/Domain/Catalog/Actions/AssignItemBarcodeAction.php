<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBarcode;

/**
 * Items may be created with no barcode (e.g. a hardware-store SKU that
 * never carried a manufacturer barcode). This assigns one on demand,
 * generated from the item's own already-unique SKU, so it can be printed
 * and scanned before it needs a "real" barcode entered by hand.
 */
final class AssignItemBarcodeAction
{
    public function execute(Item $item): ItemBarcode
    {
        $existing = $item->barcodes()->where('is_primary', true)->first();

        if ($existing !== null) {
            return $existing;
        }

        return $item->barcodes()->create([
            'barcode' => $item->sku,
            'type' => 'code128',
            'is_primary' => true,
        ]);
    }
}
