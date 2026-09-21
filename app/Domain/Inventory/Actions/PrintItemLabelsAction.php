<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Catalog\Actions\AssignItemBarcodeAction;
use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Exceptions\LabelPrintingException;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Inventory\Support\LabelPrinterConnectorFactory;
use App\Domain\Inventory\Support\TsplLabelBuilder;

final class PrintItemLabelsAction
{
    public function __construct(
        private readonly LabelPrinterConnectorFactory $connectors,
        private readonly AssignItemBarcodeAction $assignBarcode,
        private readonly TsplLabelBuilder $labels,
    ) {}

    /**
     * A label always prints the price of the specific lot it was received
     * against -- not a flat item price, since old and new stock of the
     * same item can now be priced differently.
     *
     * @param  list<array{item: Item, stock_lot_id: int, quantity: int}>  $lines
     */
    public function execute(StockLocation $location, array $lines): void
    {
        $connector = $this->connectors->resolve($location);

        try {
            foreach ($lines as $line) {
                $item = $line['item'];
                $stockLot = StockLot::findOrFail($line['stock_lot_id']);

                if ($stockLot->selling_price === null) {
                    throw LabelPrintingException::lotHasNoPrice($item->name);
                }

                $barcode = $this->assignBarcode->execute($item);

                $connector->write($this->labels->buildMany(
                    sku: $item->sku,
                    price: (string) $stockLot->selling_price->getAmount(),
                    barcode: $barcode->barcode,
                    quantity: $line['quantity'],
                    name: $item->name,
                ));
            }
        } finally {
            $connector->finalize();
        }
    }
}
