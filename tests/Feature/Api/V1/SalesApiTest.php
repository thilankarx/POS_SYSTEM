<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\CartPayment;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Terminal;
use App\Domain\Sales\Support\PrintConnectorFactory;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakePrintConnectorFactory;

beforeEach(function () {
    $this->seed();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();
});

function apiPrintSale(): Sale
{
    $shift = openShiftFor(test()->cashier, test()->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => test()->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => test()->location->id,
        'user_id' => test()->cashier->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);
    CartLine::create([
        'cart_id' => $cart->id,
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->cash->id, 'amount' => '1.38']);

    return app(CompleteSaleAction::class)->execute($cart->fresh());
}

it('prints a receipt for a completed sale over the API', function () {
    $this->terminal->update(['printer_connector' => Terminal::CONNECTOR_NETWORK, 'receipt_printer' => '127.0.0.1:9100']);
    $sale = apiPrintSale();

    $this->app->instance(PrintConnectorFactory::class, new FakePrintConnectorFactory);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/sales/{$sale->id}/print-receipt")
        ->assertOk()
        ->assertJson(['message' => 'Receipt sent to the printer.']);
});

it('returns a 422 when printing to an unconfigured terminal', function () {
    $sale = apiPrintSale();

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/sales/{$sale->id}/print-receipt")
        ->assertStatus(422)
        ->assertJson(['message' => 'This terminal has no receipt printer configured.']);
});

it('opens the cash drawer for a configured terminal over the API', function () {
    $this->terminal->update(['printer_connector' => Terminal::CONNECTOR_NETWORK, 'receipt_printer' => '127.0.0.1:9100']);
    openShiftFor($this->cashier, $this->terminal);

    $this->app->instance(PrintConnectorFactory::class, new FakePrintConnectorFactory);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/terminals/{$this->terminal->id}/open-drawer")
        ->assertOk()
        ->assertJson(['message' => 'Drawer opened.']);
});

it('rejects opening the drawer on a terminal outside the user\'s locations', function () {
    $otherLocation = StockLocation::create(['name' => 'Other', 'code' => 'OTHER']);
    $otherTerminal = Terminal::create([
        'name' => 'Other Terminal',
        'code' => 'T-OTHER',
        'stock_location_id' => $otherLocation->id,
        'printer_connector' => Terminal::CONNECTOR_NETWORK,
        'receipt_printer' => '127.0.0.1:9100',
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/terminals/{$otherTerminal->id}/open-drawer")
        ->assertForbidden();
});
