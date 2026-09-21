<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Customer;
use App\Domain\Giftcards\Actions\IssueGiftcardAction;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Loyalty\Models\LoyaltyPackage;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Sales\CartPricer;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
});

function apiTestCustomer(): Customer
{
    $person = Person::factory()->create();

    return Customer::create(['person_id' => $person->id, 'company_name' => 'Test Co']);
}

function activeCartFor(User $user, Terminal $terminal): Cart
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

it('creates a cart when the terminal has an open shift', function () {
    openShiftFor($this->cashier, $this->terminal);
    Sanctum::actingAs($this->cashier, ['*']);
    $uuid = (string) Str::uuid();

    $response = $this->postJson('/api/v1/carts', [
        'client_uuid' => $uuid,
        'terminal_id' => $this->terminal->id,
    ])->assertCreated();

    expect($response->json('data.client_uuid'))->toBe($uuid)
        ->and($response->json('data.status'))->toBe('active');
});

it('is idempotent on client_uuid: the same value returns the same cart', function () {
    openShiftFor($this->cashier, $this->terminal);
    Sanctum::actingAs($this->cashier, ['*']);
    $uuid = (string) Str::uuid();
    $payload = ['client_uuid' => $uuid, 'terminal_id' => $this->terminal->id];

    $first = $this->postJson('/api/v1/carts', $payload)->assertCreated();
    $second = $this->postJson('/api/v1/carts', $payload)->assertOk();

    expect($first->json('data.id'))->toBe($second->json('data.id'))
        ->and(Cart::where('client_uuid', $uuid)->count())->toBe(1);
});

it('rejects cart creation when the terminal has no open shift', function () {
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson('/api/v1/carts', [
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
    ])->assertStatus(422)->assertJsonFragment(['message' => 'The shift for this terminal is not open.']);
});

it('rejects cart creation for a user not attached to the terminal location', function () {
    $warehouse = StockLocation::where('code', 'WH')->firstOrFail();
    $wTerminal = Terminal::create([
        'stock_location_id' => $warehouse->id,
        'code' => 'WH-T2',
        'name' => 'Warehouse Terminal 2',
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson('/api/v1/carts', [
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $wTerminal->id,
    ])->assertStatus(422)->assertJsonFragment(['message' => 'You are not authorized to operate at this terminal.']);
});

it('resumes a suspended cart back to active', function () {
    $shift = openShiftFor($this->cashier, $this->terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $uuid = (string) Str::uuid();

    $cart = Cart::create([
        'client_uuid' => $uuid,
        'terminal_id' => $this->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $location->id,
        'user_id' => $this->cashier->id,
        'sale_type' => 'pos',
        'status' => Cart::STATUS_SUSPENDED,
        'suspended_at' => now(),
        'suspended_by_user_id' => $this->cashier->id,
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->postJson('/api/v1/carts', [
        'client_uuid' => $uuid,
        'terminal_id' => $this->terminal->id,
    ])->assertOk();

    expect($response->json('data.status'))->toBe('active')
        ->and($cart->fresh()->suspended_at)->toBeNull();
});

it('shows a cart with live totals matching CartPricer', function () {
    $shift = openShiftFor($this->cashier, $this->terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $location->id,
        'user_id' => $this->cashier->id,
        'sale_type' => 'pos',
        'status' => Cart::STATUS_ACTIVE,
    ]);

    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $cart->lines()->create([
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $location->id,
        'quantity' => '2',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->getJson("/api/v1/carts/{$cart->id}")->assertOk();

    $expected = app(CartPricer::class)->price($cart->fresh(['lines.item']));
    expect($response->json('data.totals.total'))->toBe((string) $expected->total->getAmount());
});

it('forbids viewing a cart the user neither owns nor can operate at', function () {
    $shift = openShiftFor($this->cashier, $this->terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $location->id,
        'user_id' => $this->cashier->id,
        'sale_type' => 'pos',
        'status' => Cart::STATUS_ACTIVE,
    ]);

    $stranger = User::factory()->create();
    $stranger->assignRole('Cashier');

    Sanctum::actingAs($stranger, ['*']);
    $this->getJson("/api/v1/carts/{$cart->id}")->assertForbidden();
});

it('adds a line, snapshotting the item price at add-time', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->postJson("/api/v1/carts/{$cart->id}/lines", [
        'item_id' => $item->id,
        'quantity' => '2',
    ])->assertCreated();

    expect($response->json('data.unit_price'))->toBe(demoPriceFor($item))
        ->and($response->json('data.quantity'))->toBe('2.000');

    StockLot::where('item_id', $item->id)->fefo()->firstOrFail()->update(['selling_price' => '999.99']);
    $line = $cart->lines()->firstOrFail();
    expect((string) $line->unit_price->getAmount())->not->toBe('999.99');
});

it('merges a repeat scan of the same item into one line', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1'])->assertCreated();
    $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '3'])->assertOk();

    expect($cart->lines()->count())->toBe(1)
        ->and((string) $cart->lines()->firstOrFail()->quantity)->toBe('4.000');
});

it('merges two additions that resolve to the same lot (auto-FEFO both times)', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BAK-BREAD-WHT')->firstOrFail();
    // The demo seeder's own opening lot is this item's only lot -- use it
    // directly rather than adding a second, competing one.
    $lot = StockLot::where('item_id', $item->id)->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1'])->assertCreated();
    $this->postJson("/api/v1/carts/{$cart->id}/lines", [
        'item_id' => $item->id,
        'quantity' => '1',
        'stock_lot_id' => $lot->id,
    ])->assertOk();

    expect($cart->lines()->count())->toBe(1)
        ->and((string) $cart->lines()->firstOrFail()->quantity)->toBe('2.000')
        ->and($cart->lines()->firstOrFail()->stock_lot_id)->toBe($lot->id);
});

it('resets kitchen_sent_at when merging more quantity into a sent-but-not-yet-prepared line', function () {
    // Otherwise the extra units silently never reach the kitchen, and a
    // subsequent "Send to kitchen" call sees nothing to send at all.
    // (Merging into an already-*prepared* line is a different, later
    // scenario -- covered separately below -- where a new line is
    // created instead.)
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    // Matches the lot AddCartLineAction will auto-resolve (FEFO) for the
    // POST below, so the merge lookup finds this line.
    $lot = StockLot::where('item_id', $item->id)->fefo()->firstOrFail();
    $line = $cart->lines()->create([
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_lot_id' => $lot->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
        'kitchen_sent_at' => now(),
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1'])->assertOk();

    $line->refresh();
    expect((string) $line->quantity)->toBe('2.000')
        ->and($line->kitchen_sent_at)->toBeNull()
        ->and($line->kitchen_prepared_at)->toBeNull();
});

it('resets kitchen_sent_at when a quantity change is PATCHed onto a sent-but-not-yet-prepared line', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $line = $cart->lines()->create([
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
        'kitchen_sent_at' => now(),
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->patchJson("/api/v1/carts/{$cart->id}/lines/{$line->id}", ['quantity' => '3'])->assertOk();

    $line->refresh();
    expect($line->kitchen_sent_at)->toBeNull()
        ->and($line->kitchen_prepared_at)->toBeNull();
});

it('does not touch kitchen_sent_at when only price is changed on an already-sent line', function () {
    // unit_price changes require sales.change_price, which the cashier
    // fixture doesn't have -- use the admin (Owner) for this one.
    $admin = User::where('username', 'admin')->firstOrFail();
    $cart = activeCartFor($admin, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $line = $cart->lines()->create([
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
        'kitchen_sent_at' => now(),
    ]);

    Sanctum::actingAs($admin, ['*']);
    $this->patchJson("/api/v1/carts/{$cart->id}/lines/{$line->id}", ['unit_price' => '9.99'])->assertOk();

    expect($line->fresh()->kitchen_sent_at)->not->toBeNull();
});

it('creates a new line instead of merging into one the kitchen already prepared', function () {
    // Otherwise the closed ticket would silently read as a bigger order
    // than what the kitchen actually fired and finished.
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $cart->lines()->create([
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
        'kitchen_sent_at' => now(),
        'kitchen_prepared_at' => now(),
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1'])->assertCreated();

    $lines = $cart->lines()->orderBy('line_number')->get();
    expect($lines)->toHaveCount(2)
        ->and((string) $lines[0]->quantity)->toBe('1.000')
        ->and($lines[0]->kitchen_prepared_at)->not->toBeNull()
        ->and((string) $lines[1]->quantity)->toBe('1.000')
        ->and($lines[1]->kitchen_sent_at)->toBeNull();
});

it('refuses to increase the quantity of an already-prepared line via PATCH', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $line = $cart->lines()->create([
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
        'kitchen_sent_at' => now(),
        'kitchen_prepared_at' => now(),
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->patchJson("/api/v1/carts/{$cart->id}/lines/{$line->id}", ['quantity' => '2'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This item was already prepared by the kitchen. Add it again instead of increasing the quantity here.');

    expect((string) $line->fresh()->quantity)->toBe('1.000');
});

it('allows decreasing the quantity of an already-prepared line, leaving its kitchen state untouched', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $line = $cart->lines()->create([
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => '2',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
        'kitchen_sent_at' => now(),
        'kitchen_prepared_at' => now(),
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->patchJson("/api/v1/carts/{$cart->id}/lines/{$line->id}", ['quantity' => '1'])->assertOk();

    $line->refresh();
    expect((string) $line->quantity)->toBe('1.000')
        ->and($line->kitchen_sent_at)->not->toBeNull()
        ->and($line->kitchen_prepared_at)->not->toBeNull();
});

it('never merges lines assigned to different lots for the same item', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BAK-BREAD-WHT')->firstOrFail();
    $lotA = StockLot::create(['item_id' => $item->id, 'lot_number' => 'LOT-A', 'selling_price' => '2.10', 'cost_price' => '0.80']);
    $lotB = StockLot::create(['item_id' => $item->id, 'lot_number' => 'LOT-B', 'selling_price' => '2.10', 'cost_price' => '0.80']);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1', 'stock_lot_id' => $lotA->id])->assertCreated();
    $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1', 'stock_lot_id' => $lotB->id])->assertCreated();

    expect($cart->lines()->count())->toBe(2);
});

it('rejects a stock_lot_id that belongs to a different item', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BAK-BREAD-WHT')->firstOrFail();
    $otherItem = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $foreignLot = StockLot::create(['item_id' => $otherItem->id, 'lot_number' => 'LOT-FOREIGN']);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/lines", [
        'item_id' => $item->id,
        'quantity' => '1',
        'stock_lot_id' => $foreignLot->id,
    ])->assertUnprocessable();

    expect($cart->lines()->count())->toBe(0);
});

it('auto-assigns the FEFO lot when one exists, and refuses the sale when none does', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BAK-BREAD-WHT')->firstOrFail();
    // Start from a clean slate -- the demo seeder's own opening lot would
    // otherwise compete with the lots this test creates.
    StockLot::where('item_id', $item->id)->delete();
    Sanctum::actingAs($this->cashier, ['*']);

    // No lots at all -- nothing to price this stocked item from, so the
    // sale is refused rather than silently going through unpriced.
    $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1'])
        ->assertUnprocessable();

    $soonToExpire = StockLot::create(['item_id' => $item->id, 'lot_number' => 'LOT-SOON', 'expires_on' => now()->addDays(5), 'selling_price' => '2.10', 'cost_price' => '0.80']);
    StockLot::create(['item_id' => $item->id, 'lot_number' => 'LOT-LATER', 'expires_on' => now()->addDays(30), 'selling_price' => '2.10', 'cost_price' => '0.80']);

    $withLot = $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1'])->assertCreated();
    $newLineId = $withLot->json('data.id');
    expect(CartLine::findOrFail($newLineId)->stock_lot_id)->toBe($soonToExpire->id);
});

it('prices a cart line from the chosen lot, and refuses a lot with no price', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BAK-BREAD-WHT')->firstOrFail();
    $pricedLot = StockLot::create(['item_id' => $item->id, 'lot_number' => 'LOT-PRICED', 'selling_price' => '12.00', 'cost_price' => '6.00']);
    $unpricedLot = StockLot::create(['item_id' => $item->id, 'lot_number' => 'LOT-UNPRICED']);
    Sanctum::actingAs($this->cashier, ['*']);

    $priced = $this->postJson("/api/v1/carts/{$cart->id}/lines", [
        'item_id' => $item->id,
        'quantity' => '1',
        'stock_lot_id' => $pricedLot->id,
    ])->assertCreated();
    expect((string) CartLine::findOrFail($priced->json('data.id'))->unit_price->getAmount())->toBe('12.00');

    // No item-level price left to fall back to -- an unpriced lot blocks
    // the sale instead of silently pricing it as something else.
    $this->postJson("/api/v1/carts/{$cart->id}/lines", [
        'item_id' => $item->id,
        'quantity' => '1',
        'stock_lot_id' => $unpricedLot->id,
    ])->assertUnprocessable();
});

it('lists in-stock lots and their effective prices, only when they differ', function () {
    $item = Item::where('sku', 'BAK-BREAD-WHT')->firstOrFail();
    // Isolate from the demo seeder's own opening lot for this item.
    StockLot::where('item_id', $item->id)->delete();
    $lotA = StockLot::create(['item_id' => $item->id, 'lot_number' => 'LOT-A', 'selling_price' => '10.00']);
    $lotB = StockLot::create(['item_id' => $item->id, 'lot_number' => 'LOT-B', 'selling_price' => '12.00']);
    // Neither lot has any stock movement yet, so neither is "in stock" --
    // the endpoint must not surface a lot nobody can actually sell from.
    Sanctum::actingAs($this->cashier, ['*']);

    $this->getJson("/api/v1/items/{$item->id}/lot-prices")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    app(\App\Domain\Inventory\InventoryService::class)->record(
        item: $item,
        stockLocationId: StockLocation::where('code', 'MAIN')->firstOrFail()->id,
        quantityDelta: '5',
        reason: \App\Domain\Inventory\Models\StockMovement::REASON_ADJUSTMENT,
        source: null,
        userId: $this->cashier->id,
        stockLotId: $lotA->id,
    );
    app(\App\Domain\Inventory\InventoryService::class)->record(
        item: $item,
        stockLocationId: StockLocation::where('code', 'MAIN')->firstOrFail()->id,
        quantityDelta: '5',
        reason: \App\Domain\Inventory\Models\StockMovement::REASON_ADJUSTMENT,
        source: null,
        userId: $this->cashier->id,
        stockLotId: $lotB->id,
    );

    $this->getJson("/api/v1/items/{$item->id}/lot-prices")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.selling_price', '10.00')
        ->assertJsonPath('data.1.selling_price', '12.00');
});

it('updates a line quantity', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $created = $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1'])->assertCreated();
    $lineId = $created->json('data.id');

    $this->patchJson("/api/v1/carts/{$cart->id}/lines/{$lineId}", ['quantity' => '5'])
        ->assertOk()
        ->assertJsonPath('data.quantity', '5.000');
});

it('updates a line description without requiring sales.change_price', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $created = $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1'])->assertCreated();
    $lineId = $created->json('data.id');

    $this->patchJson("/api/v1/carts/{$cart->id}/lines/{$lineId}", ['description' => 'Cut to 2.5m'])
        ->assertOk()
        ->assertJsonPath('data.description', 'Cut to 2.5m');
});

it('lets a Manager override a line price and stamps who overrode it', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $manager->stockLocations()->syncWithoutDetaching([StockLocation::where('code', 'MAIN')->firstOrFail()->id]);

    $cart = activeCartFor($manager, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    Sanctum::actingAs($manager, ['*']);
    $created = $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1'])->assertCreated();
    $lineId = $created->json('data.id');

    $this->patchJson("/api/v1/carts/{$cart->id}/lines/{$lineId}", ['unit_price' => '5.00'])
        ->assertOk()
        ->assertJsonPath('data.unit_price', '5.00')
        ->assertJsonPath('data.price_overridden', true);

    $line = $cart->lines()->firstOrFail();
    expect($line->price_overridden_by_user_id)->toBe($manager->id);

    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));
    expect((string) $totals->subtotal->getAmount())->toBe('5.00');
});

it('denies a Cashier from overriding a line price, but quantity updates on the same cart still work', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $created = $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1'])->assertCreated();
    $lineId = $created->json('data.id');

    $this->patchJson("/api/v1/carts/{$cart->id}/lines/{$lineId}", ['unit_price' => '1.00'])->assertForbidden();

    $line = $cart->lines()->firstOrFail();
    expect((string) $line->unit_price->getAmount())->toBe(demoPriceFor($item))
        ->and($line->price_overridden)->toBeFalse();

    $this->patchJson("/api/v1/carts/{$cart->id}/lines/{$lineId}", ['quantity' => '3'])
        ->assertOk()
        ->assertJsonPath('data.quantity', '3.000');
});

it('denies a Cashier from setting a manual discount', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $created = $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1'])->assertCreated();
    $lineId = $created->json('data.id');

    $this->patchJson("/api/v1/carts/{$cart->id}/lines/{$lineId}", ['discount_value' => '1.00', 'discount_type' => 'fixed'])
        ->assertForbidden();
});

it('returns 404 removing a line that belongs to a different cart', function () {
    $cartA = activeCartFor($this->cashier, $this->terminal);
    $cartB = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $created = $this->postJson("/api/v1/carts/{$cartA->id}/lines", ['item_id' => $item->id, 'quantity' => '1'])->assertCreated();
    $lineId = $created->json('data.id');

    $this->deleteJson("/api/v1/carts/{$cartB->id}/lines/{$lineId}")->assertNotFound();
});

it('removes a line', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $created = $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1'])->assertCreated();
    $lineId = $created->json('data.id');

    $this->deleteJson("/api/v1/carts/{$cart->id}/lines/{$lineId}")->assertNoContent();
    expect($cart->lines()->count())->toBe(0);
});

it('adds a payment', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/payments", [
        'payment_method_id' => $cash->id,
        'amount' => '5.00',
        'tendered' => '10.00',
    ])->assertCreated()->assertJsonPath('data.amount', '5.00');

    expect($cart->payments()->count())->toBe(1);
});

it('rejects a payment against an inactive method', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    $cash->update(['is_active' => false]);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/payments", [
        'payment_method_id' => $cash->id,
        'amount' => '5.00',
    ])->assertStatus(422)->assertJsonFragment(['message' => 'This payment method is not active.']);
});

it('rejects a payment requiring a reference when none is supplied', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $check = PaymentMethod::where('code', 'check')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/payments", [
        'payment_method_id' => $check->id,
        'amount' => '5.00',
    ])->assertStatus(422)->assertJsonFragment(['message' => 'A reference is required for this payment method.']);
});

it('rejects a card payment when no card-machine reference is supplied', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $card = PaymentMethod::where('code', 'card')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/payments", [
        'payment_method_id' => $card->id,
        'amount' => '5.00',
    ])->assertStatus(422)->assertJsonFragment(['message' => 'A reference is required for this payment method.']);
});

it('accepts a card payment carrying the card-machine reference', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $card = PaymentMethod::where('code', 'card')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/payments", [
        'payment_method_id' => $card->id,
        'amount' => '5.00',
        'reference' => 'APPR-778401',
    ])->assertCreated()->assertJsonPath('data.reference', 'APPR-778401');
});

it('removes a payment', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $created = $this->postJson("/api/v1/carts/{$cart->id}/payments", [
        'payment_method_id' => $cash->id,
        'amount' => '5.00',
    ])->assertCreated();

    $this->deleteJson("/api/v1/carts/{$cart->id}/payments/{$created->json('data.id')}")->assertNoContent();
    expect($cart->payments()->count())->toBe(0);
});

it('does not double-book a payment when the same Idempotency-Key is replayed', function () {
    // The offline register's sync engine retries add_payment on a dropped
    // response (see client.js's request timeout) or a cross-tab race --
    // without a key, that retry created a second CartPayment for one real
    // cash tender.
    $cart = activeCartFor($this->cashier, $this->terminal);
    $cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $payload = ['payment_method_id' => $cash->id, 'amount' => '5.00'];

    $first = $this->postJson("/api/v1/carts/{$cart->id}/payments", $payload, ['Idempotency-Key' => 'pay-key-1'])
        ->assertCreated();

    $second = $this->postJson("/api/v1/carts/{$cart->id}/payments", $payload, ['Idempotency-Key' => 'pay-key-1'])
        ->assertCreated();

    expect($cart->payments()->count())->toBe(1)
        ->and($second->json('data.id'))->toBe($first->json('data.id'));
});

it('books two separate payments when no Idempotency-Key is sent, unchanged from before', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $payload = ['payment_method_id' => $cash->id, 'amount' => '5.00'];

    $this->postJson("/api/v1/carts/{$cart->id}/payments", $payload)->assertCreated();
    $this->postJson("/api/v1/carts/{$cart->id}/payments", $payload)->assertCreated();

    expect($cart->payments()->count())->toBe(2);
});

it('attaches and removes a customer on an active cart', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $customer = apiTestCustomer();
    Sanctum::actingAs($this->cashier, ['*']);
    $this->patchJson("/api/v1/carts/{$cart->id}/customer", ['customer_id' => $customer->id])
        ->assertOk()
        ->assertJsonPath('data.customer_id', $customer->id);

    $this->deleteJson("/api/v1/carts/{$cart->id}/customer")
        ->assertOk()
        ->assertJsonPath('data.customer_id', null);
});

it('refuses to attach a customer to a suspended cart', function () {
    $shift = openShiftFor($this->cashier, $this->terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $customer = apiTestCustomer();

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $location->id,
        'user_id' => $this->cashier->id,
        'sale_type' => 'pos',
        'status' => Cart::STATUS_SUSPENDED,
        'suspended_at' => now(),
        'suspended_by_user_id' => $this->cashier->id,
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->patchJson("/api/v1/carts/{$cart->id}/customer", ['customer_id' => $customer->id])
        ->assertStatus(422);
});

it('lists suspended carts at the terminal stock location, excluding its own terminal and other locations', function () {
    $shift = openShiftFor($this->cashier, $this->terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $warehouse = StockLocation::where('code', 'WH')->firstOrFail();
    $secondTerminal = Terminal::where('code', 'T2')->firstOrFail();

    // Parked by a different terminal at the same location -> should appear.
    $otherTerminalSameLocation = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $secondTerminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $location->id,
        'user_id' => $this->cashier->id,
        'sale_type' => 'pos',
        'status' => Cart::STATUS_SUSPENDED,
        'suspended_at' => now(),
        'suspended_by_user_id' => $this->cashier->id,
    ]);

    // Parked by this same terminal -> already visible via the device's own
    // local parked list, must not also show up here.
    Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $location->id,
        'user_id' => $this->cashier->id,
        'sale_type' => 'pos',
        'status' => Cart::STATUS_SUSPENDED,
        'suspended_at' => now(),
        'suspended_by_user_id' => $this->cashier->id,
    ]);

    // Parked at a different stock location entirely -> must not show up.
    Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $secondTerminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $warehouse->id,
        'user_id' => $this->cashier->id,
        'sale_type' => 'pos',
        'status' => Cart::STATUS_SUSPENDED,
        'suspended_at' => now(),
        'suspended_by_user_id' => $this->cashier->id,
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->getJson("/api/v1/carts?terminal_id={$this->terminal->id}")->assertOk();
    $uuids = collect($response->json('data'))->pluck('client_uuid')->all();

    expect($uuids)->toContain($otherTerminalSameLocation->client_uuid)
        ->and(count($uuids))->toBe(1);
});

it('suspends an active cart, marking it discoverable for cross-terminal resume', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/suspend")
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    expect($cart->fresh()->status)->toBe(Cart::STATUS_SUSPENDED)
        ->and($cart->fresh()->suspended_by_user_id)->toBe($this->cashier->id)
        ->and($cart->fresh()->suspended_at)->not->toBeNull();
});

it('refuses to suspend a cart that is not active', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $cart->update(['status' => Cart::STATUS_SUSPENDED]);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/suspend")->assertStatus(422);
});

it('abandons an active cart', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/abandon")
        ->assertOk()
        ->assertJsonPath('data.status', 'abandoned');

    expect($cart->fresh()->status)->toBe(Cart::STATUS_ABANDONED)
        ->and($cart->fresh()->abandoned_by_user_id)->toBe($this->cashier->id)
        ->and($cart->fresh()->abandoned_at)->not->toBeNull();
});

it('abandons a suspended cart', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $cart->update(['status' => Cart::STATUS_SUSPENDED]);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/abandon")
        ->assertOk()
        ->assertJsonPath('data.status', 'abandoned');

    expect($cart->fresh()->status)->toBe(Cart::STATUS_ABANDONED);
});

it('refuses to abandon a completed cart', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $cart->update(['status' => Cart::STATUS_COMPLETED]);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/abandon")->assertStatus(422);
});

it('rejects abandoning a cart without the sales.delete permission', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $accountant = User::factory()->create();
    $accountant->assignRole('Accountant');
    Sanctum::actingAs($accountant, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/abandon")->assertForbidden();
});

it('reassigns terminal, shift and user when a different terminal resumes a suspended cart', function () {
    $originalShift = openShiftFor($this->cashier, $this->terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $uuid = (string) Str::uuid();

    $cart = Cart::create([
        'client_uuid' => $uuid,
        'terminal_id' => $this->terminal->id,
        'shift_id' => $originalShift->id,
        'stock_location_id' => $location->id,
        'user_id' => $this->cashier->id,
        'sale_type' => 'pos',
        'status' => Cart::STATUS_SUSPENDED,
        'suspended_at' => now(),
        'suspended_by_user_id' => $this->cashier->id,
    ]);

    $secondTerminal = Terminal::where('code', 'T2')->firstOrFail();
    $secondCashier = User::factory()->create();
    $secondCashier->assignRole('Cashier');
    $secondCashier->stockLocations()->syncWithoutDetaching([$location->id]);
    $newShift = openShiftFor($secondCashier, $secondTerminal);

    Sanctum::actingAs($secondCashier, ['*']);
    $this->postJson('/api/v1/carts', [
        'client_uuid' => $uuid,
        'terminal_id' => $secondTerminal->id,
    ])->assertOk();

    $cart->refresh();
    expect($cart->terminal_id)->toBe($secondTerminal->id)
        ->and($cart->shift_id)->toBe($newShift->id)
        ->and($cart->user_id)->toBe($secondCashier->id)
        ->and($cart->stock_location_id)->toBe($location->id);
});

it('refuses to resume a suspended cart from a terminal with no open shift', function () {
    $originalShift = openShiftFor($this->cashier, $this->terminal);
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $uuid = (string) Str::uuid();

    Cart::create([
        'client_uuid' => $uuid,
        'terminal_id' => $this->terminal->id,
        'shift_id' => $originalShift->id,
        'stock_location_id' => $location->id,
        'user_id' => $this->cashier->id,
        'sale_type' => 'pos',
        'status' => Cart::STATUS_SUSPENDED,
        'suspended_at' => now(),
        'suspended_by_user_id' => $this->cashier->id,
    ]);

    $secondTerminal = Terminal::where('code', 'T2')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson('/api/v1/carts', [
        'client_uuid' => $uuid,
        'terminal_id' => $secondTerminal->id,
    ])->assertStatus(422)->assertJsonFragment(['message' => 'The shift for this terminal is not open.']);
});

it('applies a valid, redeemable coupon code and reflects it on the cart', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $promotion = Promotion::create([
        'name' => 'Coupon 10% off',
        'reward_type' => Promotion::REWARD_PERCENT_OFF,
        'reward_value' => '10',
        'requires_coupon' => true,
        'is_active' => true,
    ]);
    Coupon::create(['promotion_id' => $promotion->id, 'code' => 'SAVE10', 'max_uses' => 5]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->patchJson("/api/v1/carts/{$cart->id}/coupon", ['code' => 'SAVE10'])
        ->assertOk()
        ->assertJsonPath('data.coupon_code', 'SAVE10');
});

it('rejects a coupon code that does not match any coupon', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->patchJson("/api/v1/carts/{$cart->id}/coupon", ['code' => 'NOPE'])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'This coupon code is invalid, expired, or no longer available.']);

    expect($cart->fresh()->coupon_code)->toBeNull();
});

it('rejects a coupon code whose promotion is inactive', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $promotion = Promotion::create([
        'name' => 'Disabled promo',
        'reward_type' => Promotion::REWARD_PERCENT_OFF,
        'reward_value' => '10',
        'requires_coupon' => true,
        'is_active' => false,
    ]);
    Coupon::create(['promotion_id' => $promotion->id, 'code' => 'OFF', 'max_uses' => 5]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->patchJson("/api/v1/carts/{$cart->id}/coupon", ['code' => 'OFF'])->assertStatus(422);
});

it('rejects a coupon code that has hit its max uses', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $promotion = Promotion::create([
        'name' => 'Exhausted promo',
        'reward_type' => Promotion::REWARD_PERCENT_OFF,
        'reward_value' => '10',
        'requires_coupon' => true,
        'is_active' => true,
    ]);
    Coupon::create(['promotion_id' => $promotion->id, 'code' => 'USED', 'max_uses' => 1, 'use_count' => 1]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->patchJson("/api/v1/carts/{$cart->id}/coupon", ['code' => 'USED'])->assertStatus(422);
});

it('removes a coupon code from a cart', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $cart->update(['coupon_code' => 'SAVE10']);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->deleteJson("/api/v1/carts/{$cart->id}/coupon")
        ->assertOk()
        ->assertJsonPath('data.coupon_code', null);
});

it('accepts a gift card payment when the reference is a valid, sufficiently funded card', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $giftcard = app(IssueGiftcardAction::class)->execute('20.00');
    $method = PaymentMethod::where('code', 'giftcard')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/payments", [
        'payment_method_id' => $method->id,
        'amount' => '5.00',
        'reference' => $giftcard->number,
    ])->assertCreated();
});

it('rejects a gift card payment for an unknown card number', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $method = PaymentMethod::where('code', 'giftcard')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/payments", [
        'payment_method_id' => $method->id,
        'amount' => '5.00',
        'reference' => 'GC-NOPE',
    ])->assertStatus(422)->assertJsonFragment(['message' => 'No gift card matches that number.']);
});

it('rejects a gift card payment exceeding the card balance', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $giftcard = app(IssueGiftcardAction::class)->execute('2.00');
    $method = PaymentMethod::where('code', 'giftcard')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/payments", [
        'payment_method_id' => $method->id,
        'amount' => '5.00',
        'reference' => $giftcard->number,
    ])->assertStatus(422);
});

it('accepts a points payment when the customer has a sufficient balance', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $package = LoyaltyPackage::create([
        'name' => 'API test package', 'points_per_currency_unit' => '0', 'currency_value_per_point' => '0.01', 'is_active' => true,
    ]);
    $customer = apiTestCustomer();
    $customer->update(['loyalty_package_id' => $package->id, 'points_balance' => '1000']);
    $cart->update(['customer_id' => $customer->id]);
    $method = PaymentMethod::where('code', 'points')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/payments", [
        'payment_method_id' => $method->id,
        'amount' => '5.00',
    ])->assertCreated();
});

it('rejects a points payment when the cart has no customer attached', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $method = PaymentMethod::where('code', 'points')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/payments", [
        'payment_method_id' => $method->id,
        'amount' => '5.00',
    ])->assertStatus(422);
});

it('rejects a points payment exceeding the customer points balance', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    $package = LoyaltyPackage::create([
        'name' => 'Low balance package', 'points_per_currency_unit' => '0', 'currency_value_per_point' => '0.01', 'is_active' => true,
    ]);
    $customer = apiTestCustomer();
    $customer->update(['loyalty_package_id' => $package->id, 'points_balance' => '10']);
    $cart->update(['customer_id' => $customer->id]);
    $method = PaymentMethod::where('code', 'points')->firstOrFail();
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/payments", [
        'payment_method_id' => $method->id,
        'amount' => '5.00',
    ])->assertStatus(422)->assertJsonFragment(['message' => 'This customer does not have enough points for that redemption.']);
});

it('sets a tip on the cart, reflected on the cart resource', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->patchJson("/api/v1/carts/{$cart->id}/tip", ['tip_amount' => '3.50'])
        ->assertOk()
        ->assertJsonPath('data.tip_amount', '3.50');

    expect((string) $cart->fresh()->tip_amount->getAmount())->toBe('3.50');
});

it('rejects a negative tip', function () {
    $cart = activeCartFor($this->cashier, $this->terminal);
    Sanctum::actingAs($this->cashier, ['*']);
    $this->patchJson("/api/v1/carts/{$cart->id}/tip", ['tip_amount' => '-1'])
        ->assertStatus(422);
});
