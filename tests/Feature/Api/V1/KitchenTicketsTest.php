<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\DinnerTable;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->shift = openShiftFor($this->cashier, $this->terminal);
    $this->table = DinnerTable::create([
        'name' => 'Patio A',
        'stock_location_id' => $this->location->id,
        'seats' => 4,
        'status' => DinnerTable::STATUS_OCCUPIED,
    ]);
});

function kitchenTicketCart(array $overrides = []): Cart
{
    return Cart::create(array_merge([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => test()->terminal->id,
        'shift_id' => test()->shift->id,
        'stock_location_id' => test()->location->id,
        'user_id' => test()->cashier->id,
        'dinner_table_id' => test()->table->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ], $overrides));
}

function kitchenTicketLine(Cart $cart, string $sku, bool $sent = true): CartLine
{
    $item = Item::where('sku', $sku)->firstOrFail();

    return $cart->lines()->create([
        'line_number' => $cart->nextLineNumber(),
        'item_id' => $item->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
        'kitchen_sent_at' => $sent ? now() : null,
    ]);
}

it('lists sent-but-unprepared lines scoped by stock_location_id, grouped by cart', function () {
    $other = StockLocation::where('code', 'WH')->firstOrFail();
    $cart = kitchenTicketCart();
    kitchenTicketLine($cart, 'BEV-COLA-330');
    kitchenTicketLine($cart, 'BAK-BREAD-WHT');

    $otherCart = kitchenTicketCart(['stock_location_id' => $other->id, 'dinner_table_id' => null]);
    kitchenTicketLine($otherCart, 'BEV-COLA-330');

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->getJson("/api/v1/kitchen/tickets?stock_location_id={$this->location->id}")->assertOk();

    $tickets = $response->json('data');
    expect($tickets)->toHaveCount(1)
        ->and($tickets[0]['cart_id'])->toBe($cart->id)
        ->and($tickets[0]['table']['name'])->toBe('Patio A')
        ->and($tickets[0]['lines'])->toHaveCount(2);
});

it('excludes lines never sent to the kitchen', function () {
    $cart = kitchenTicketCart();
    kitchenTicketLine($cart, 'BEV-COLA-330', sent: false);

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->getJson("/api/v1/kitchen/tickets?stock_location_id={$this->location->id}")->assertOk();

    expect($response->json('data'))->toBeEmpty();
});

it('excludes lines whose cart has been abandoned', function () {
    $cart = kitchenTicketCart(['status' => Cart::STATUS_ABANDONED]);
    kitchenTicketLine($cart, 'BEV-COLA-330');

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->getJson("/api/v1/kitchen/tickets?stock_location_id={$this->location->id}")->assertOk();

    expect($response->json('data'))->toBeEmpty();
});

it('marks a line prepared, removing it from the ticket', function () {
    $cart = kitchenTicketCart();
    $line = kitchenTicketLine($cart, 'BEV-COLA-330');

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/kitchen/lines/{$line->id}/prepare")->assertOk();

    expect($line->fresh()->kitchen_prepared_at)->not->toBeNull();

    $response = $this->getJson("/api/v1/kitchen/tickets?stock_location_id={$this->location->id}")->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('keeps a ticket visible while only some of its lines are prepared', function () {
    $cart = kitchenTicketCart();
    $line1 = kitchenTicketLine($cart, 'BEV-COLA-330');
    kitchenTicketLine($cart, 'BAK-BREAD-WHT');

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/kitchen/lines/{$line1->id}/prepare")->assertOk();

    $response = $this->getJson("/api/v1/kitchen/tickets?stock_location_id={$this->location->id}")->assertOk();
    $tickets = $response->json('data');
    expect($tickets)->toHaveCount(1)
        ->and($tickets[0]['lines'])->toHaveCount(1);
});

it('refuses to mark a never-sent line prepared', function () {
    $cart = kitchenTicketCart();
    $line = kitchenTicketLine($cart, 'BEV-COLA-330', sent: false);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/kitchen/lines/{$line->id}/prepare")->assertUnprocessable();
});

it('rejects a user who cannot view the cart', function () {
    $cart = kitchenTicketCart();
    $line = kitchenTicketLine($cart, 'BEV-COLA-330');

    $other = User::factory()->create(['is_active' => true]);
    $other->assignRole('Kitchen');
    Sanctum::actingAs($other, ['*']);
    $this->postJson("/api/v1/kitchen/lines/{$line->id}/prepare")->assertForbidden();
});

it('rejects an unauthenticated request', function () {
    $this->getJson("/api/v1/kitchen/tickets?stock_location_id={$this->location->id}")->assertUnauthorized();
});

it('shows only the additional quantity, not the total, after re-ordering a prepared item', function () {
    $cart = kitchenTicketCart();
    $line = kitchenTicketLine($cart, 'BEV-COLA-330');

    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/v1/kitchen/lines/{$line->id}/prepare")->assertOk();
    expect($this->getJson("/api/v1/kitchen/tickets?stock_location_id={$this->location->id}")->json('data'))->toBeEmpty();

    // Order one more of the same dish -- must not resurrect the closed
    // ticket with a cumulative total.
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $this->postJson("/api/v1/carts/{$cart->id}/lines", ['item_id' => $item->id, 'quantity' => '1'])->assertCreated();
    $this->postJson("/api/v1/carts/{$cart->id}/kitchen-ticket")->assertOk();

    $tickets = $this->getJson("/api/v1/kitchen/tickets?stock_location_id={$this->location->id}")->json('data');
    expect($tickets)->toHaveCount(1)
        ->and($tickets[0]['lines'])->toHaveCount(1)
        ->and($tickets[0]['lines'][0]['quantity'])->toBe('1.000');
});
