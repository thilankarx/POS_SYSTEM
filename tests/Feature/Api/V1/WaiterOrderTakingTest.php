<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();

    $this->waiter = User::factory()->create(['is_active' => true]);
    $this->waiter->assignRole('Waiter');
    $this->waiter->stockLocations()->syncWithoutDetaching([$this->location->id]);

    // A waiter never opens their own shift (no shifts.open) -- they always
    // work a shift a cashier already started on the terminal.
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    openShiftFor($this->cashier, $this->terminal);
});

it('lets a waiter build and fire an order: create a cart, add a line, send to kitchen, park it', function () {
    Sanctum::actingAs($this->waiter, ['*']);
    $uuid = (string) Str::uuid();
    $cart = $this->postJson('/api/v1/carts', [
        'client_uuid' => $uuid,
        'terminal_id' => $this->terminal->id,
    ])->assertCreated()->json('data');

    expect($cart['status'])->toBe('active');

    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $this->postJson("/api/v1/carts/{$cart['id']}/lines", [
        'item_id' => $item->id,
        'quantity' => '1',
    ])->assertCreated();

    $this->postJson("/api/v1/carts/{$cart['id']}/suspend")->assertOk();
});

it('forbids a waiter from taking payment, completing the sale, or opening the drawer', function () {
    Sanctum::actingAs($this->waiter, ['*']);
    $uuid = (string) Str::uuid();
    $cart = $this->postJson('/api/v1/carts', [
        'client_uuid' => $uuid,
        'terminal_id' => $this->terminal->id,
    ])->assertCreated()->json('data');

    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $this->postJson("/api/v1/carts/{$cart['id']}/lines", [
        'item_id' => $item->id,
        'quantity' => '1',
    ])->assertCreated();

    $this->postJson("/api/v1/carts/{$cart['id']}/payments", [
        'payment_method_id' => $this->cash->id,
        'amount' => '1.38',
    ])->assertForbidden();

    $this->postJson("/api/v1/carts/{$cart['id']}/complete")->assertForbidden();

    $this->postJson("/api/v1/terminals/{$this->terminal->id}/open-drawer")->assertForbidden();

    $this->patchJson("/api/v1/carts/{$cart['id']}/tip", ['tip_amount' => '2.00'])->assertForbidden();
});

it('lets a cashier resume and complete an order a waiter parked, crediting the waiter with the sale', function () {
    Sanctum::actingAs($this->waiter, ['*']);
    $uuid = (string) Str::uuid();
    $cart = $this->postJson('/api/v1/carts', [
        'client_uuid' => $uuid,
        'terminal_id' => $this->terminal->id,
    ])->assertCreated()->json('data');

    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $this->postJson("/api/v1/carts/{$cart['id']}/lines", [
        'item_id' => $item->id,
        'quantity' => '1',
    ])->assertCreated();

    $this->postJson("/api/v1/carts/{$cart['id']}/suspend")->assertOk();

    Sanctum::actingAs($this->cashier, ['*']);
    $resumed = $this->postJson('/api/v1/carts', [
        'client_uuid' => $uuid,
        'terminal_id' => $this->terminal->id,
    ])->assertOk()->json('data');

    $this->postJson("/api/v1/carts/{$resumed['id']}/payments", [
        'payment_method_id' => $this->cash->id,
        'amount' => '1.38',
    ])->assertCreated();

    $saleId = $this->postJson("/api/v1/carts/{$resumed['id']}/complete", [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated()->json('data.id');

    $sale = Sale::findOrFail($saleId);
    expect($sale->waiter_id)->toBe($this->waiter->id)
        ->and($sale->user_id)->toBe($this->cashier->id);
});
