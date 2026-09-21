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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();
});

function apiReceiptSale(): Sale
{
    $shift = openShiftFor(test()->cashier, test()->terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => test()->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $location->id,
        'user_id' => test()->cashier->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);
    CartLine::create([
        'cart_id' => $cart->id,
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $location->id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->cash->id, 'amount' => '1.38']);

    return app(CompleteSaleAction::class)->execute($cart->fresh());
}

it('downloads a receipt pdf via the authenticated api', function () {
    $sale = apiReceiptSale();

    Sanctum::actingAs($this->cashier, ['*']);

    $response = $this->get("/api/v1/sales/{$sale->id}/receipt");

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('requires authentication to download a receipt via the api', function () {
    $sale = apiReceiptSale();

    $this->getJson("/api/v1/sales/{$sale->id}/receipt")->assertUnauthorized();
});
