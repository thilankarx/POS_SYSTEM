<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Reporting\Queries\ShiftReportQuery;
use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\CartPayment;
use App\Domain\Sales\Models\CashMovement;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\ShiftCashCount;
use App\Domain\Sales\Models\Terminal;
use App\Support\Money\Money;
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
        'expected_cash' => '110.00',
        'counted_cash' => '108.00',
        'cash_variance' => '-2.00',
        'status' => Shift::STATUS_OPEN,
        'opened_at' => now(),
    ]);
});

it('lists shifts in range with sales total and count via withSum/withCount', function () {
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $this->shift->id,
        'stock_location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);
    CartLine::create([
        'cart_id' => $cart->id,
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $this->location->id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    app(CompleteSaleAction::class)->execute($cart->fresh());

    $shifts = app(ShiftReportQuery::class)->list(now()->subDay(), now()->addDay());
    $row = $shifts->firstWhere('id', $this->shift->id);

    expect($row)->not->toBeNull()
        ->and((int) $row->sales_count)->toBe(1)
        ->and(Money::of($row->sales_total)->getAmount()->__toString())->toBe('1.38');
});

it('filters shifts by terminal', function () {
    $otherTerminal = Terminal::where('code', 'T2')->firstOrFail();

    $query = app(ShiftReportQuery::class);
    $from = now()->subDay();
    $to = now()->addDay();

    expect($query->list($from, $to, $this->terminal->id)->total())->toBeGreaterThanOrEqual(1)
        ->and($query->list($from, $to, $otherTerminal->id)->total())->toBe(0);
});

it('returns denomination breakdown for a shift', function () {
    ShiftCashCount::create(['shift_id' => $this->shift->id, 'denomination' => '20.00', 'count' => 3, 'subtotal' => '60.00']);
    ShiftCashCount::create(['shift_id' => $this->shift->id, 'denomination' => '5.00', 'count' => 2, 'subtotal' => '10.00']);

    $rows = app(ShiftReportQuery::class)->denominationBreakdown($this->shift);

    expect($rows)->toHaveCount(2)
        ->and((string) $rows->first()->denomination->getAmount())->toBe('20.00');
});

it('returns cash movements for a shift', function () {
    CashMovement::create(['shift_id' => $this->shift->id, 'user_id' => $this->user->id, 'direction' => 'out', 'amount' => '25.00', 'reason' => 'Petty cash']);

    $rows = app(ShiftReportQuery::class)->cashMovements($this->shift);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->reason)->toBe('Petty cash')
        ->and($rows->first()->user->id)->toBe($this->user->id);
});

it('exports shifts as a lazy collection', function () {
    $rows = app(ShiftReportQuery::class)->shiftsForExport(now()->subDay(), now()->addDay())->all();

    $exported = collect($rows)->firstWhere('id', $this->shift->id);

    expect($exported)->not->toBeNull()
        ->and($exported->terminal->code)->toBe('T1');
});
