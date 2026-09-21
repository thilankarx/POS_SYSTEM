<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemKit;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->cola = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $this->water = Item::where('sku', 'BEV-WATER-500')->firstOrFail();
    $this->bread = Item::where('sku', 'BAK-BREAD-WHT')->firstOrFail();
});

function activeKitCart(User $user, Terminal $terminal): Cart
{
    $shift = openShiftFor($user, $terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();

    return Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $location->id,
        'user_id' => $user->id,
        'sale_type' => 'pos',
        'status' => Cart::STATUS_ACTIVE,
    ]);
}

/** @param  array<int, array{0: Item, 1: string}>  $components  [item, quantity] pairs */
function makeKit(array $overrides, array $components): ItemKit
{
    $kit = ItemKit::create(array_merge([
        'kit_number' => 'KIT-'.Str::random(8),
        'name' => 'Test Kit',
        'discount_value' => '0',
        'discount_type' => 'percent',
        'price_option' => 'kit',
        'print_option' => 'all',
    ], $overrides));

    $kit->items()->sync(collect($components)->mapWithKeys(
        fn (array $pair, int $index) => [$pair[0]->id => ['quantity' => $pair[1], 'sequence' => $index]]
    ));

    return $kit;
}

it('explodes a kit into one line per component', function () {
    $cart = activeKitCart($this->cashier, $this->terminal);
    $kit = makeKit([], [[$this->cola, '2'], [$this->water, '1']]);
    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->postJson("/api/v1/carts/{$cart->id}/kit-lines", ['item_kit_id' => $kit->id])
        ->assertCreated();

    expect($response->json('data'))->toHaveCount(2)
        ->and($cart->lines()->count())->toBe(2);

    $colaLine = $cart->lines()->where('item_id', $this->cola->id)->firstOrFail();
    $waterLine = $cart->lines()->where('item_id', $this->water->id)->firstOrFail();

    expect($colaLine->item_kit_id)->toBe($kit->id)
        ->and((string) $colaLine->quantity)->toBe('2.000')
        ->and($waterLine->item_kit_id)->toBe($kit->id)
        ->and((string) $waterLine->quantity)->toBe('1.000');
});

it('scales component quantities by the kit quantity requested', function () {
    $cart = activeKitCart($this->cashier, $this->terminal);
    $kit = makeKit([], [[$this->cola, '2']]);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/kit-lines", ['item_kit_id' => $kit->id, 'quantity' => '3'])
        ->assertCreated();

    expect((string) $cart->lines()->firstOrFail()->quantity)->toBe('6.000');
});

it('applies no per-line discount when price_option is components', function () {
    $cart = activeKitCart($this->cashier, $this->terminal);
    $kit = makeKit([
        'discount_value' => '10',
        'discount_type' => 'percent',
        'price_option' => 'components',
    ], [[$this->cola, '1'], [$this->water, '1']]);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/kit-lines", ['item_kit_id' => $kit->id])->assertCreated();

    foreach ($cart->lines as $line) {
        expect((string) $line->discount_value)->toBe('0.0000');
    }
});

it('applies the same percent discount to every component line', function () {
    $cart = activeKitCart($this->cashier, $this->terminal);
    $kit = makeKit([
        'discount_value' => '15',
        'discount_type' => 'percent',
        'price_option' => 'kit',
    ], [[$this->cola, '1'], [$this->water, '1']]);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/kit-lines", ['item_kit_id' => $kit->id])->assertCreated();

    foreach ($cart->lines as $line) {
        expect((string) $line->discount_value)->toBe('15.0000')
            ->and($line->discount_type)->toBe('percent');
    }
});

it('apportions a fixed kit discount across components with the remainder on the last line', function () {
    $cart = activeKitCart($this->cashier, $this->terminal);
    $kit = makeKit([
        'discount_value' => '1.00',
        'discount_type' => 'fixed',
        'price_option' => 'kit',
    ], [[$this->cola, '1'], [$this->water, '1'], [$this->bread, '1']]);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/kit-lines", ['item_kit_id' => $kit->id])->assertCreated();

    $lines = $cart->lines()->orderBy('line_number')->get();
    expect($lines)->toHaveCount(3);

    $sum = $lines->reduce(fn (string $carry, $line) => bcadd($carry, (string) $line->discount_value, 4), '0');
    expect($sum)->toBe('1.0000');

    foreach ($lines as $line) {
        expect($line->discount_type)->toBe('fixed')
            ->and(bccomp((string) $line->discount_value, '0', 4) >= 0)->toBeTrue();
    }
});

it('never merges two additions of the same kit into one set of lines', function () {
    $cart = activeKitCart($this->cashier, $this->terminal);
    $kit = makeKit([], [[$this->cola, '1']]);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/kit-lines", ['item_kit_id' => $kit->id])->assertCreated();
    $this->postJson("/api/v1/carts/{$cart->id}/kit-lines", ['item_kit_id' => $kit->id])->assertCreated();

    expect($cart->lines()->count())->toBe(2);
});

it('rejects an unknown kit id', function () {
    $cart = activeKitCart($this->cashier, $this->terminal);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/kit-lines", ['item_kit_id' => 999999])
        ->assertUnprocessable();
});

it('forbids adding a kit to a cart the user neither owns nor can operate at', function () {
    $cart = activeKitCart($this->cashier, $this->terminal);
    $kit = makeKit([], [[$this->cola, '1']]);

    $stranger = User::factory()->create();
    $stranger->assignRole('Cashier');
    Sanctum::actingAs($stranger, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/kit-lines", ['item_kit_id' => $kit->id])
        ->assertForbidden();

    expect($cart->lines()->count())->toBe(0);
});
