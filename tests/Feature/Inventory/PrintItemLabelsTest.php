<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBarcode;
use App\Domain\Inventory\Actions\PrintItemLabelsAction;
use App\Domain\Inventory\Exceptions\LabelPrintingException;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Inventory\Support\LabelPrinterConnectorFactory;
use Tests\Support\FakeLabelPrinterConnectorFactory;

beforeEach(function () {
    $this->seed();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
});

function labelItem(array $overrides = []): Item
{
    $item = Item::create(array_merge([
        'sku' => 'LBL-'.uniqid(),
        'name' => 'Test Widget',
        'stock_type' => Item::STOCK_TYPE_STOCKED,
    ], $overrides));

    StockLot::create([
        'item_id' => $item->id,
        'lot_number' => 'LOT-'.uniqid(),
        'cost_price' => '5.00',
        'selling_price' => '9.99',
    ]);

    return $item;
}

function labelLine(Item $item, int $quantity): array
{
    return [
        'item' => $item,
        'stock_lot_id' => StockLot::where('item_id', $item->id)->firstOrFail()->id,
        'quantity' => $quantity,
    ];
}

it('assigns a barcode to an item with none and includes it in the printed label', function () {
    $this->location->update(['label_printer_connector' => StockLocation::CONNECTOR_NETWORK, 'label_printer' => '127.0.0.1:9100']);
    $item = labelItem(['sku' => 'LBL-NOBARCODE']);

    $fake = new FakeLabelPrinterConnectorFactory;
    $this->app->instance(LabelPrinterConnectorFactory::class, $fake);

    app(PrintItemLabelsAction::class)->execute($this->location, [labelLine($item, 1)]);

    $barcode = $item->barcodes()->where('is_primary', true)->first();
    expect($barcode)->not->toBeNull()
        ->and($barcode->barcode)->toBe('LBL-NOBARCODE')
        ->and($fake->connector->captured)->toContain('LBL-NOBARCODE')
        ->and($fake->connector->captured)->toContain('9.99')
        ->and($fake->connector->captured)->toContain('Test Widget');
});

it('reuses an existing primary barcode instead of overwriting it', function () {
    $this->location->update(['label_printer_connector' => StockLocation::CONNECTOR_NETWORK, 'label_printer' => '127.0.0.1:9100']);
    $item = labelItem();
    $item->barcodes()->create(['barcode' => 'EXISTING123', 'type' => 'ean13', 'is_primary' => true]);

    $fake = new FakeLabelPrinterConnectorFactory;
    $this->app->instance(LabelPrinterConnectorFactory::class, $fake);

    app(PrintItemLabelsAction::class)->execute($this->location, [labelLine($item, 1)]);

    expect(ItemBarcode::where('item_id', $item->id)->count())->toBe(1)
        ->and($fake->connector->captured)->toContain('EXISTING123');
});

it('prints one label job per requested copy', function () {
    $this->location->update(['label_printer_connector' => StockLocation::CONNECTOR_NETWORK, 'label_printer' => '127.0.0.1:9100']);
    $item = labelItem();

    $fake = new FakeLabelPrinterConnectorFactory;
    $this->app->instance(LabelPrinterConnectorFactory::class, $fake);

    app(PrintItemLabelsAction::class)->execute($this->location, [labelLine($item, 3)]);

    expect(substr_count($fake->connector->captured, 'PRINT 1,1'))->toBe(3);
});

it('refuses to print when the location has no label printer configured', function () {
    $item = labelItem();

    expect(fn () => app(PrintItemLabelsAction::class)->execute($this->location, [labelLine($item, 1)]))
        ->toThrow(LabelPrintingException::class, 'no label printer configured');
});
