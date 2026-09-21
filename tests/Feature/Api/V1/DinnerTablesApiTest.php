<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\DinnerTable;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();
});

function makeDinnerTable(StockLocation $location, string $status = DinnerTable::STATUS_AVAILABLE, string $name = 'T1'): DinnerTable
{
    return DinnerTable::create([
        'name' => $name,
        'stock_location_id' => $location->id,
        'seats' => 4,
        'status' => $status,
    ]);
}

it('lists dinner tables scoped by stock_location_id', function () {
    $warehouse = StockLocation::where('code', 'WH')->firstOrFail();
    $inMain = makeDinnerTable($this->location, name: 'Table 1');
    makeDinnerTable($warehouse, name: 'Table 2');

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->getJson("/api/v1/dinner-tables?stock_location_id={$this->location->id}")->assertOk();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Table 1')
        ->and($names)->not->toContain('Table 2')
        ->and($response->json('data.0.status'))->toBe($inMain->status);
});

it('opens a table by creating a cart against it, marking the table occupied', function () {
    $table = makeDinnerTable($this->location);
    openShiftFor($this->cashier, $this->terminal);

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->postJson('/api/v1/carts', [
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'dinner_table_id' => $table->id,
    ])->assertCreated();

    expect($response->json('data.dinner_table.id'))->toBe($table->id)
        ->and($table->fresh()->status)->toBe(DinnerTable::STATUS_OCCUPIED);
});

it('refuses to open a second cart against an already-occupied table', function () {
    $table = makeDinnerTable($this->location, DinnerTable::STATUS_OCCUPIED);
    openShiftFor($this->cashier, $this->terminal);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson('/api/v1/carts', [
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'dinner_table_id' => $table->id,
    ])->assertUnprocessable();
});

it('releases the table when the sale completes', function () {
    $table = makeDinnerTable($this->location, DinnerTable::STATUS_OCCUPIED);
    $shift = openShiftFor($this->cashier, $this->terminal);

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'dinner_table_id' => $table->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);

    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $cart->lines()->create([
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $this->location->id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);
    $cart->payments()->create(['payment_method_id' => $this->cash->id, 'amount' => '1.38']);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/complete", [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated();

    expect($table->fresh()->status)->toBe(DinnerTable::STATUS_AVAILABLE);
});

it('releases the table when the cart is abandoned', function () {
    $table = makeDinnerTable($this->location, DinnerTable::STATUS_OCCUPIED);
    $shift = openShiftFor($this->cashier, $this->terminal);

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'dinner_table_id' => $table->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/abandon")->assertOk();

    expect($table->fresh()->status)->toBe(DinnerTable::STATUS_AVAILABLE);
});

it('does not release the table when the cart is only suspended', function () {
    $table = makeDinnerTable($this->location, DinnerTable::STATUS_OCCUPIED);
    $shift = openShiftFor($this->cashier, $this->terminal);

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'dinner_table_id' => $table->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/carts/{$cart->id}/suspend")->assertOk();

    expect($table->fresh()->status)->toBe(DinnerTable::STATUS_OCCUPIED);
});

it('finds the suspended cart parked against a table', function () {
    $table = makeDinnerTable($this->location, DinnerTable::STATUS_OCCUPIED);
    $shift = openShiftFor($this->cashier, $this->terminal);

    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'dinner_table_id' => $table->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_SUSPENDED,
        'suspended_at' => now(),
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->getJson("/api/v1/dinner-tables/{$table->id}/cart")->assertOk();

    expect($response->json('data.id'))->toBe($cart->id);
});

it('404s looking up a table with no parked cart', function () {
    $table = makeDinnerTable($this->location);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->getJson("/api/v1/dinner-tables/{$table->id}/cart")->assertNotFound();
});

it('does not find an active (not yet parked) cart at a table', function () {
    $table = makeDinnerTable($this->location, DinnerTable::STATUS_OCCUPIED);
    $shift = openShiftFor($this->cashier, $this->terminal);

    Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $this->terminal->id,
        'shift_id' => $shift->id,
        'stock_location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'dinner_table_id' => $table->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->getJson("/api/v1/dinner-tables/{$table->id}/cart")->assertNotFound();
});

it('rejects an unauthenticated request', function () {
    $table = makeDinnerTable($this->location);

    $this->getJson("/api/v1/dinner-tables?stock_location_id={$this->location->id}")->assertUnauthorized();
    $this->getJson("/api/v1/dinner-tables/{$table->id}/cart")->assertUnauthorized();
});
