<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemKit;
use App\Domain\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->cola = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

function apiTestKit(string $kitNumber, string $name): ItemKit
{
    $kit = ItemKit::create([
        'kit_number' => $kitNumber,
        'name' => $name,
        'discount_value' => '0',
        'discount_type' => 'percent',
        'price_option' => 'kit',
        'print_option' => 'all',
    ]);
    $kit->items()->sync([
        test()->cola->id => ['quantity' => '1', 'sequence' => 0],
    ]);

    return $kit;
}

it('lists kits with their components', function () {
    $kit = apiTestKit('KIT-API-1', 'Starter Bundle');
    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->getJson('/api/v1/item-kits')->assertOk();

    $row = collect($response->json('data'))->firstWhere('id', $kit->id);
    expect($row)->not->toBeNull()
        ->and($row['items'])->toHaveCount(1)
        ->and($row['items'][0]['item_id'])->toBe($this->cola->id);
});

it('filters kits by name and by kit_number', function () {
    apiTestKit('KIT-API-2', 'Widget Bundle');
    Sanctum::actingAs($this->cashier, ['*']);
    $byName = $this->getJson('/api/v1/item-kits?q=Widget')->json('data');
    expect(collect($byName)->pluck('kit_number'))->toContain('KIT-API-2');

    $byNumber = $this->getJson('/api/v1/item-kits?q=KIT-API-2')->json('data');
    expect(collect($byNumber)->pluck('name'))->toContain('Widget Bundle');
});

it('clamps per_page=0 instead of dividing by zero', function () {
    Sanctum::actingAs($this->cashier, ['*']);
    $this->getJson('/api/v1/item-kits?per_page=0')->assertOk();
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/v1/item-kits')->assertUnauthorized();
});

it('rejects a user without item_kits.view', function () {
    $user = User::factory()->create();
    $user->assignRole('Accountant');

    Sanctum::actingAs($user, ['*']);
    $this->getJson('/api/v1/item-kits')->assertForbidden();
});
