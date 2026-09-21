<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemKit;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\SerialNumber;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Actions\AddKitToCartAction;
use App\Domain\Sales\Actions\CloseShiftAction;
use App\Domain\Sales\CartPricer;
use App\Domain\Sales\Models\Cart;
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

function cartReadyToComplete(User $user, Terminal $terminal, PaymentMethod $method, string $amount = '1.38'): Cart
{
    $shift = openShiftFor($user, $terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $location->id,
        'user_id' => $user->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);

    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $cart->lines()->create([
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $location->id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);

    $cart->payments()->create([
        'payment_method_id' => $method->id,
        'amount' => $amount,
    ]);

    return $cart;
}

it('completes a cart, decrements stock, and marks it completed', function () {
    $cart = cartReadyToComplete($this->cashier, $this->terminal, $this->cash);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $before = $item->stockLevels()->where('stock_location_id', $location->id)->value('quantity');

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->postJson("/api/v1/carts/{$cart->id}/complete", [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated();

    expect($response->json('data.status'))->toBe(Sale::STATUS_COMPLETED)
        ->and($cart->fresh()->status)->toBe(Cart::STATUS_COMPLETED);

    $after = $item->stockLevels()->where('stock_location_id', $location->id)->value('quantity');
    expect(bcsub((string) $before, (string) $after, 3))->toBe('1.000');
});

it('completes a quote cart with no payment and no stock movement, minting a quote_number', function () {
    $shift = openShiftFor($this->cashier, $this->terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $before = $item->stockLevels()->where('stock_location_id', $location->id)->value('quantity');

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $location->id,
        'user_id' => $this->cashier->id,
        'sale_type' => Sale::TYPE_QUOTE,
        'status' => Cart::STATUS_ACTIVE,
    ]);
    $cart->lines()->create([
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $location->id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->postJson("/api/v1/carts/{$cart->id}/complete", [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated();

    expect($response->json('data.status'))->toBe(Sale::STATUS_COMPLETED)
        ->and($response->json('data.quote_number'))->not->toBeNull()
        ->and($response->json('data.paid_total'))->toBe('0.00');

    $after = $item->stockLevels()->where('stock_location_id', $location->id)->value('quantity');
    expect((string) $after)->toBe((string) $before);
});

it('requires an Idempotency-Key header', function () {
    $cart = cartReadyToComplete($this->cashier, $this->terminal, $this->cash);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/complete")->assertStatus(422);
});

it('returns clean 4xx json, not a 500, when the shift is closed', function () {
    $cart = cartReadyToComplete($this->cashier, $this->terminal, $this->cash);
    app(CloseShiftAction::class)->execute($cart->shift, $this->cashier, [['denomination' => '100', 'count' => 1]]);

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->postJson("/api/v1/carts/{$cart->id}/complete", [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ]);

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(400)
        ->and($response->getStatusCode())->toBeLessThan(500)
        ->and($response->json('message'))->toContain('shift');
});

it('replays identically for the same Idempotency-Key without creating a second Sale', function () {
    $cart = cartReadyToComplete($this->cashier, $this->terminal, $this->cash);
    Sanctum::actingAs($this->cashier, ['*']);    $key = (string) Str::uuid();

    $first = $this->postJson("/api/v1/carts/{$cart->id}/complete", [], ['Idempotency-Key' => $key]);
    $second = $this->postJson("/api/v1/carts/{$cart->id}/complete", [], ['Idempotency-Key' => $key]);

    expect($first->json())->toEqual($second->json())
        ->and($first->getStatusCode())->toBe($second->getStatusCode())
        ->and(Sale::count())->toBe(1);
});

it('rejects a reused key with a different request body', function () {
    $cartA = cartReadyToComplete($this->cashier, $this->terminal, $this->cash);
    $cartB = cartReadyToComplete($this->cashier, $this->terminal, $this->cash);
    Sanctum::actingAs($this->cashier, ['*']);    $key = (string) Str::uuid();

    $this->postJson("/api/v1/carts/{$cartA->id}/complete", [], ['Idempotency-Key' => $key])->assertCreated();
    $this->postJson("/api/v1/carts/{$cartB->id}/complete", [], ['Idempotency-Key' => $key])->assertStatus(409);
});

it('rejects completing an empty cart', function () {
    $shift = openShiftFor($this->cashier, $this->terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $location->id,
        'user_id' => $this->cashier->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/complete", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Cannot complete a sale with no lines.']);
});

it('rejects an underpaid cart', function () {
    $cart = cartReadyToComplete($this->cashier, $this->terminal, $this->cash, amount: '0.10');
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/complete", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'still due'));
});

it('deducts stock per component and tags sale lines with item_kit_id when a kit sale completes', function () {
    $shift = openShiftFor($this->cashier, $this->terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $cola = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $water = Item::where('sku', 'BEV-WATER-500')->firstOrFail();

    $kit = ItemKit::create([
        'kit_number' => 'KIT-CHECKOUT',
        'name' => 'Checkout Kit',
        'discount_value' => '0',
        'discount_type' => 'percent',
        'price_option' => 'kit',
        'print_option' => 'all',
    ]);
    $kit->items()->sync([
        $cola->id => ['quantity' => '2', 'sequence' => 0],
        $water->id => ['quantity' => '1', 'sequence' => 1],
    ]);

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $location->id,
        'user_id' => $this->cashier->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);

    app(AddKitToCartAction::class)->execute($cart, $kit);

    $totals = app(CartPricer::class)->price($cart->fresh('lines.item'));
    $cart->payments()->create(['payment_method_id' => $this->cash->id, 'amount' => (string) $totals->total->getAmount()]);

    $colaBefore = $cola->stockLevels()->where('stock_location_id', $location->id)->value('quantity');
    $waterBefore = $water->stockLevels()->where('stock_location_id', $location->id)->value('quantity');

    Sanctum::actingAs($this->cashier, ['*']);    $response = $this->postJson("/api/v1/carts/{$cart->id}/complete", [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated();

    $sale = Sale::findOrFail($response->json('data.id'));

    $colaAfter = $cola->stockLevels()->where('stock_location_id', $location->id)->value('quantity');
    $waterAfter = $water->stockLevels()->where('stock_location_id', $location->id)->value('quantity');
    expect(bcsub((string) $colaBefore, (string) $colaAfter, 3))->toBe('2.000')
        ->and(bcsub((string) $waterBefore, (string) $waterAfter, 3))->toBe('1.000');

    expect($sale->lines()->count())->toBe(2);
    foreach ($sale->lines as $line) {
        expect($line->item_kit_id)->toBe($kit->id);
    }
});

function serializedCartReadyToComplete(User $user, Terminal $terminal, PaymentMethod $method, ?string $serial): Cart
{
    $shift = openShiftFor($user, $terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $item->update(['is_serialized' => true]);

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $location->id,
        'user_id' => $user->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);

    $cart->lines()->create([
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $location->id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
        'serial' => $serial,
    ]);

    $cart->payments()->create(['payment_method_id' => $method->id, 'amount' => '1.38']);

    return $cart;
}

it('transitions an assigned, in-stock serial to sold on completion', function () {
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $serial = SerialNumber::create([
        'item_id' => $item->id,
        'serial' => 'SER-001',
        'stock_location_id' => $location->id,
        'status' => SerialNumber::STATUS_IN_STOCK,
    ]);

    $cart = serializedCartReadyToComplete($this->cashier, $this->terminal, $this->cash, 'SER-001');
    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->postJson("/api/v1/carts/{$cart->id}/complete", [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated();

    $sale = Sale::findOrFail($response->json('data.id'));
    $serial->refresh();

    expect($serial->status)->toBe(SerialNumber::STATUS_SOLD)
        ->and($serial->sold_on_sale_id)->toBe($sale->id);
});

it('rejects completing a sale with a serialized line that has no serial assigned', function () {
    $cart = serializedCartReadyToComplete($this->cashier, $this->terminal, $this->cash, null);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/complete", [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertStatus(422)->assertJsonFragment(['message' => 'A serial number is required for [Cola 330ml] before this sale can complete.']);
});

it('rejects completing a sale with a serial that is already sold', function () {
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    SerialNumber::create([
        'item_id' => $item->id,
        'serial' => 'SER-002',
        'stock_location_id' => $location->id,
        'status' => SerialNumber::STATUS_SOLD,
    ]);

    $cart = serializedCartReadyToComplete($this->cashier, $this->terminal, $this->cash, 'SER-002');
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/complete", [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertStatus(422)->assertJsonFragment(['message' => 'Serial [SER-002] is not available to sell.']);
});

it('blocks completion of a kit whose component is serialized and has no serial assigned, without special-casing', function () {
    $shift = openShiftFor($this->cashier, $this->terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $cola = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $cola->update(['is_serialized' => true]);
    $water = Item::where('sku', 'BEV-WATER-500')->firstOrFail();

    $kit = ItemKit::create([
        'kit_number' => 'KIT-SERIAL',
        'name' => 'Serialized Kit',
        'discount_value' => '0',
        'discount_type' => 'percent',
        'price_option' => 'kit',
        'print_option' => 'all',
    ]);
    $kit->items()->sync([
        $cola->id => ['quantity' => '1', 'sequence' => 0],
        $water->id => ['quantity' => '1', 'sequence' => 1],
    ]);

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $location->id,
        'user_id' => $this->cashier->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);
    app(AddKitToCartAction::class)->execute($cart, $kit);

    $totals = app(CartPricer::class)->price($cart->fresh('lines.item'));
    $cart->payments()->create(['payment_method_id' => $this->cash->id, 'amount' => (string) $totals->total->getAmount()]);

    Sanctum::actingAs($this->cashier, ['*']);    $this->postJson("/api/v1/carts/{$cart->id}/complete", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'A serial number is required for [Cola 330ml] before this sale can complete.']);
});
