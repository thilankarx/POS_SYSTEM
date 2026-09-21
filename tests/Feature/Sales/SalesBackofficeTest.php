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
use App\Domain\Sales\Models\ReturnReason;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Terminal;
use App\Livewire\Sales\Index;
use App\Livewire\Sales\Refund;
use App\Livewire\Sales\VoidSale;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('Accountant');

    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->accountant->stockLocations()->sync([$this->location->id]);
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    $this->shift = openShiftFor($this->cashier, $this->terminal);
});

function backofficeSale(): Sale
{
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => test()->terminal->id,
        'shift_id' => test()->shift->id,
        'stock_location_id' => test()->location->id,
        'user_id' => test()->cashier->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);

    CartLine::create([
        'cart_id' => $cart->id,
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => test()->location->id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);

    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->cash->id, 'amount' => '1.38']);

    return app(CompleteSaleAction::class)->execute($cart->fresh());
}

it('lets a Cashier list and view sales', function () {
    $sale = backofficeSale();

    $this->actingAs($this->cashier)->get(route('sales.index'))->assertOk();
    $this->actingAs($this->cashier)->get(route('sales.show', $sale))->assertOk();
});

it('lets an Accountant list, view and download the receipt for a sale', function () {
    $sale = backofficeSale();

    $this->actingAs($this->accountant)->get(route('sales.index'))->assertOk();
    $this->actingAs($this->accountant)->get(route('sales.show', $sale))->assertOk();
    $this->actingAs($this->accountant)->get(route('sales.receipt', $sale))->assertOk();
});

it('filters the sales index by number', function () {
    $sale = backofficeSale();

    Livewire::actingAs($this->cashier)
        ->test(Index::class)
        ->set('search', $sale->number)
        ->assertSee($sale->number);

    Livewire::actingAs($this->cashier)
        ->test(Index::class)
        ->set('search', 'no-such-number')
        ->assertDontSee($sale->number);
});

it('downloads a receipt pdf for a sale from the back office', function () {
    $sale = backofficeSale();

    $response = $this->actingAs($this->cashier)->get(route('sales.receipt', $sale));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('denies the refund page to a Cashier but allows it for an admin', function () {
    $sale = backofficeSale();

    $this->actingAs($this->cashier)->get(route('sales.refund', $sale))->assertForbidden();
    $this->actingAs($this->admin)->get(route('sales.refund', $sale))->assertOk();
});

it('processes a return through the Livewire refund form', function () {
    $sale = backofficeSale();
    $line = $sale->lines->first();
    $reason = ReturnReason::where('code', 'wrong_item')->firstOrFail();

    Livewire::actingAs($this->admin)
        ->test(Refund::class, ['sale' => $sale])
        ->set("quantities.{$line->id}", '1')
        ->set('return_reason_id', $reason->id)
        ->set('refund_payment_method_id', $this->cash->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($sale->fresh()->status)->toBe(Sale::STATUS_REFUNDED);
});

it('denies the void page to a Cashier but allows it for an admin', function () {
    $sale = backofficeSale();

    $this->actingAs($this->cashier)->get(route('sales.void', $sale))->assertForbidden();
    $this->actingAs($this->admin)->get(route('sales.void', $sale))->assertOk();
});

it('voids a sale through the Livewire void form', function () {
    $sale = backofficeSale();

    Livewire::actingAs($this->admin)
        ->test(VoidSale::class, ['sale' => $sale])
        ->set('reason', 'Rung up on the wrong customer')
        ->call('save')
        ->assertHasNoErrors();

    expect($sale->fresh()->status)->toBe(Sale::STATUS_VOIDED);
});

it('redirects back with a flash error instead of a raw JSON 422 when opening the void page for an already-voided sale', function () {
    $sale = backofficeSale();
    $sale->update(['status' => Sale::STATUS_VOIDED]);

    Livewire::actingAs($this->admin)
        ->test(VoidSale::class, ['sale' => $sale])
        ->assertRedirect(route('sales.show', $sale));

    expect(session('error'))->not->toBeNull();
});

it('redirects back with a flash error instead of a raw JSON 422 when opening the refund page for a voided sale', function () {
    $sale = backofficeSale();
    $sale->update(['status' => Sale::STATUS_VOIDED]);

    Livewire::actingAs($this->admin)
        ->test(Refund::class, ['sale' => $sale])
        ->assertRedirect(route('sales.show', $sale));

    expect(session('error'))->not->toBeNull();
});
