<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Reporting\Queries\CategoryReportQuery;
use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\CartPayment;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();
    $this->user = User::where('username', 'cashier')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    $this->shift = Shift::create([
        'terminal_id' => $this->terminal->id,
        'opened_by_user_id' => $this->user->id,
        'opening_float' => '100.00',
        'status' => Shift::STATUS_OPEN,
        'opened_at' => now(),
    ]);
});

function completeCategoryReportSale(array $lines, string $amount): Sale
{
    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => test()->terminal->id,
        'shift_id' => test()->shift->id,
        'stock_location_id' => test()->location->id,
        'user_id' => test()->user->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);
    foreach ($lines as $i => [$sku, $quantity]) {
        $item = Item::where('sku', $sku)->firstOrFail();
        CartLine::create([
            'cart_id' => $cart->id,
            'line_number' => $i + 1,
            'item_id' => $item->id,
            'stock_location_id' => $cart->stock_location_id,
            'quantity' => $quantity,
            'unit_price' => demoPriceFor($item),
            'cost_price' => demoPriceFor($item, 'cost_price'),
        ]);
    }
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->cash->id, 'amount' => $amount]);

    return app(CompleteSaleAction::class)->execute($cart->fresh());
}

it('aggregates quantity, revenue, cost and margin per category', function () {
    completeCategoryReportSale([['BEV-COLA-330', '2'], ['BAK-CROIS', '1']], '10.00');

    $rows = app(CategoryReportQuery::class)->byCategory(now()->subDay(), now()->addDay())->keyBy('category_name');

    expect($rows)->toHaveKey('Beverages')
        ->and($rows)->toHaveKey('Bakery')
        ->and((string) $rows['Beverages']->quantity)->toBe('2.000');
});

it('excludes sales outside the date range', function () {
    completeCategoryReportSale([['BEV-COLA-330', '1']], '1.38');

    $rows = app(CategoryReportQuery::class)->byCategory(now()->addDays(5), now()->addDays(10));

    expect($rows)->toHaveCount(0);
});

it('filters by stock location', function () {
    completeCategoryReportSale([['BEV-COLA-330', '1']], '1.38');

    $otherLocation = StockLocation::where('code', 'WH')->firstOrFail();
    $rows = app(CategoryReportQuery::class)->byCategory(now()->subDay(), now()->addDay(), $otherLocation->id);

    expect($rows)->toHaveCount(0);
});
