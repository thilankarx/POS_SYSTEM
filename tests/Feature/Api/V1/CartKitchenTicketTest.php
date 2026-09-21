<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Support\KitchenPrinterConnectorFactory;
use App\Domain\Sales\Exceptions\KitchenPrintingException;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\DinnerTable;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Tests\Support\FakeKitchenPrinterConnectorFactory;

beforeEach(function () {
    $this->seed();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->shift = openShiftFor($this->cashier, $this->terminal);
    $this->table = DinnerTable::create([
        'name' => 'Patio A',
        'stock_location_id' => $this->location->id,
        'seats' => 4,
        'status' => DinnerTable::STATUS_OCCUPIED,
    ]);

    $this->cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $this->shift->id,
        'stock_location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'dinner_table_id' => $this->table->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);

    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $this->line = $this->cart->lines()->create([
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $this->location->id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);
});

it('sends unsent lines to the kitchen and marks them sent', function () {
    $this->location->update(['kitchen_printer_connector' => StockLocation::CONNECTOR_NETWORK, 'kitchen_printer' => '127.0.0.1:9100']);
    $this->app->instance(KitchenPrinterConnectorFactory::class, new FakeKitchenPrinterConnectorFactory);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$this->cart->id}/kitchen-ticket")
        ->assertOk()
        ->assertJsonPath('print_error', null);

    expect($this->line->fresh()->kitchen_sent_at)->not->toBeNull();
});

it('picks up a line added after the first ticket on a second call', function () {
    $this->location->update(['kitchen_printer_connector' => StockLocation::CONNECTOR_NETWORK, 'kitchen_printer' => '127.0.0.1:9100']);
    $this->app->instance(KitchenPrinterConnectorFactory::class, new FakeKitchenPrinterConnectorFactory);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$this->cart->id}/kitchen-ticket")->assertOk();

    $item = Item::where('sku', 'BAK-BREAD-WHT')->firstOrFail();
    $newLine = $this->cart->lines()->create([
        'line_number' => 2,
        'item_id' => $item->id,
        'stock_location_id' => $this->location->id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);

    $this->postJson("/api/v1/carts/{$this->cart->id}/kitchen-ticket")->assertOk();

    expect($newLine->fresh()->kitchen_sent_at)->not->toBeNull();
});

it('refuses with 422 when nothing is unsent', function () {
    $this->location->update(['kitchen_printer_connector' => StockLocation::CONNECTOR_NETWORK, 'kitchen_printer' => '127.0.0.1:9100']);
    $this->app->instance(KitchenPrinterConnectorFactory::class, new FakeKitchenPrinterConnectorFactory);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$this->cart->id}/kitchen-ticket")->assertOk();
    $this->postJson("/api/v1/carts/{$this->cart->id}/kitchen-ticket")->assertUnprocessable();
});

it('still succeeds and marks the line sent when the location has no kitchen printer configured', function () {
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$this->cart->id}/kitchen-ticket")
        ->assertOk()
        ->assertJsonPath('print_error', null);

    expect($this->line->fresh()->kitchen_sent_at)->not->toBeNull();
});

it('still succeeds but reports print_error when a configured printer is unreachable', function () {
    $this->location->update(['kitchen_printer_connector' => StockLocation::CONNECTOR_NETWORK, 'kitchen_printer' => '127.0.0.1:9100']);
    $this->app->instance(KitchenPrinterConnectorFactory::class, new class extends KitchenPrinterConnectorFactory
    {
        public function resolve(StockLocation $location): PrintConnector
        {
            throw KitchenPrintingException::connectionFailed('timed out');
        }
    });

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->postJson("/api/v1/carts/{$this->cart->id}/kitchen-ticket")->assertOk();

    expect($response->json('print_error'))->toContain('timed out')
        ->and($this->line->fresh()->kitchen_sent_at)->not->toBeNull();
});

it('rejects a user who cannot view the cart', function () {
    $other = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($other, ['*']);
    $this->postJson("/api/v1/carts/{$this->cart->id}/kitchen-ticket")->assertForbidden();
});

it('rejects an unauthenticated request', function () {
    $this->postJson("/api/v1/carts/{$this->cart->id}/kitchen-ticket")->assertUnauthorized();
});
