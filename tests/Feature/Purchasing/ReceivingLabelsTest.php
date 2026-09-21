<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Support\LabelPrinterConnectorFactory;
use App\Domain\Purchasing\Actions\ReceiveGoodsAction;
use App\Domain\Purchasing\Models\Receiving;
use App\Livewire\Purchasing\Receivings\Labels;
use Livewire\Livewire;
use Tests\Support\FakeLabelPrinterConnectorFactory;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->stockClerk = User::factory()->create();
    $this->stockClerk->assignRole('Stock Clerk');
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
});

function receivingWithFreshItem(): Receiving
{
    $item = Item::create([
        'sku' => 'RCV-LBL-'.uniqid(),
        'name' => 'Freshly Received Widget',
        'stock_type' => Item::STOCK_TYPE_STOCKED,
    ]);

    return app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: null,
        supplier: test()->supplier,
        location: test()->location,
        user: test()->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [['item_id' => $item->id, 'quantity' => '5', 'unit_cost' => '2.00', 'lot_number' => 'LOT-'.uniqid(), 'selling_price' => '4.50']],
    );
}

it('lets a stock clerk print labels for a receiving, assigning a barcode as a side effect', function () {
    $this->location->update(['label_printer_connector' => StockLocation::CONNECTOR_NETWORK, 'label_printer' => '127.0.0.1:9100']);
    $receiving = receivingWithFreshItem();
    $item = $receiving->lines->first()->item;
    expect($item->barcodes()->where('is_primary', true)->exists())->toBeFalse();

    $fake = new FakeLabelPrinterConnectorFactory;
    $this->app->instance(LabelPrinterConnectorFactory::class, $fake);

    $this->actingAs($this->stockClerk)->get(route('receivings.labels', $receiving))->assertOk();

    Livewire::actingAs($this->stockClerk)
        ->test(Labels::class, ['receiving' => $receiving])
        ->call('printLabels')
        ->assertHasNoErrors();

    expect($item->fresh()->barcodes()->where('is_primary', true)->exists())->toBeTrue()
        ->and($fake->connector->captured)->toContain($item->sku);
});

it('forbids a user without receivings.manage from opening the labels screen', function () {
    $receiving = receivingWithFreshItem();

    $this->actingAs($this->cashier)->get(route('receivings.labels', $receiving))->assertForbidden();
});

it('shows a clean error instead of a crash when the location has no label printer configured', function () {
    $receiving = receivingWithFreshItem();

    Livewire::actingAs($this->stockClerk)
        ->test(Labels::class, ['receiving' => $receiving])
        ->call('printLabels')
        ->assertHasErrors('printer');
});
