<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Inventory\Models\StockMovement;
use App\Settings\BusinessProfileSettings;
use Laravel\Sanctum\Sanctum;

function setBusinessType(string $type): void
{
    $settings = app(BusinessProfileSettings::class);
    $settings->business_type = $type;
    $settings->save();
}

beforeEach(function () {
    $this->seed();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('returns only active items, paginated', function () {
    $inactive = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $inactive->update(['is_active' => false]);

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->getJson('/api/v1/items')->assertOk();

    $skus = collect($response->json('data'))->pluck('sku');
    expect($skus)->not->toContain('BEV-COLA-330');
});

it('filters by name and by sku', function () {
    Sanctum::actingAs($this->cashier, ['*']);
    $byName = $this->getJson('/api/v1/items?q=Cola')->json('data');
    expect(collect($byName)->pluck('sku'))->toContain('BEV-COLA-330');

    $bySku = $this->getJson('/api/v1/items?q=BEV-WATER')->json('data');
    expect(collect($bySku)->pluck('sku'))->toContain('BEV-WATER-500');
});

it('matches every word of a multi-word search in any order', function () {
    Item::where('sku', 'BEV-COLA-330')->firstOrFail()->update(['name' => 'CPVC Pipe 1/2"']);

    Sanctum::actingAs($this->cashier, ['*']);
    $skus = fn (string $q) => collect($this->getJson('/api/v1/items?q='.urlencode($q))->assertOk()->json('data'))->pluck('sku');

    expect($skus('cpvc 1/2'))->toContain('BEV-COLA-330')
        ->and($skus('1/2 cpvc'))->toContain('BEV-COLA-330')
        ->and($skus('cpvc 3/4'))->not->toContain('BEV-COLA-330');
});

it('filters by barcode when a scanner submits through the search field', function () {
    $item = Item::where('sku', 'BAK-CROIS')->firstOrFail();
    $barcode = $item->barcodes()->firstOrFail();

    Sanctum::actingAs($this->cashier, ['*']);
    $matches = $this->getJson('/api/v1/items?q='.$barcode->barcode)
        ->assertOk()
        ->json('data');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['sku'])->toBe('BAK-CROIS');
});

it('omits stock_at_location when no stock_location_id is given', function () {
    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->getJson('/api/v1/items?q=Cola')->assertOk();

    expect(collect($response->json('data'))->first())->not->toHaveKey('stock_at_location');
});

it('includes stock_at_location when stock_location_id is given', function () {
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $before = (string) $item->quantityAt($location);
    app(InventoryService::class)->record($item, $location->id, '15', StockMovement::REASON_ADJUSTMENT);

    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->getJson("/api/v1/items?q=Cola&stock_location_id={$location->id}")->assertOk();

    $match = collect($response->json('data'))->firstWhere('sku', 'BEV-COLA-330');
    expect(bcsub($match['stock_at_location'], $before, 3))->toBe('15.000');
});

it('flags has_multiple_prices only when 2+ in-stock lots resolve to different prices', function () {
    $item = Item::where('sku', 'BAK-BREAD-WHT')->firstOrFail();
    $location = StockLocation::where('code', 'MAIN')->firstOrFail();

    Sanctum::actingAs($this->cashier, ['*']);
    $before = collect($this->getJson('/api/v1/items?q=Bread')->assertOk()->json('data'))->firstWhere('sku', 'BAK-BREAD-WHT');
    expect($before['has_multiple_prices'])->toBeFalse();

    $lotA = StockLot::create(['item_id' => $item->id, 'lot_number' => 'LOT-A', 'selling_price' => '10.00']);
    $lotB = StockLot::create(['item_id' => $item->id, 'lot_number' => 'LOT-B', 'selling_price' => '12.00']);
    app(InventoryService::class)->record($item, $location->id, '5', StockMovement::REASON_ADJUSTMENT, stockLotId: $lotA->id);
    app(InventoryService::class)->record($item, $location->id, '5', StockMovement::REASON_ADJUSTMENT, stockLotId: $lotB->id);

    $after = collect($this->getJson('/api/v1/items?q=Bread')->assertOk()->json('data'))->firstWhere('sku', 'BAK-BREAD-WHT');
    expect($after['has_multiple_prices'])->toBeTrue();

    $barcode = $item->barcodes()->first();
    if ($barcode) {
        $this->getJson("/api/v1/items/barcode/{$barcode->barcode}")
            ->assertOk()
            ->assertJsonPath('data.has_multiple_prices', true);
    }
});

it('resolves an item by barcode', function () {
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $barcode = $item->barcodes()->first();

    Sanctum::actingAs($this->cashier, ['*']);
    $this->getJson("/api/v1/items/barcode/{$barcode->barcode}")
        ->assertOk()
        ->assertJsonPath('data.sku', 'BEV-COLA-330');
});

it('returns 404 json for an unknown barcode', function () {
    Sanctum::actingAs($this->cashier, ['*']);
    $this->getJson('/api/v1/items/barcode/does-not-exist')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('hides items tagged for another business type, keeping untagged and matching ones', function () {
    setBusinessType(BusinessProfileSettings::BUSINESS_TYPE_RETAIL);

    Item::where('sku', 'BEV-COLA-330')->firstOrFail()->update(['business_types' => ['restaurant']]);
    Item::where('sku', 'BEV-WATER-500')->firstOrFail()->update(['business_types' => ['retail', 'restaurant']]);

    Sanctum::actingAs($this->cashier, ['*']);
    $skus = collect($this->getJson('/api/v1/items?per_page=100')->assertOk()->json('data'))->pluck('sku');

    expect($skus)->not->toContain('BEV-COLA-330')   // restaurant-only
        ->and($skus)->toContain('BEV-WATER-500')     // tagged retail too
        ->and($skus)->toContain('BAK-CROIS');        // untagged -> everywhere
});

it('404s a barcode lookup for an item tagged for another business type', function () {
    setBusinessType(BusinessProfileSettings::BUSINESS_TYPE_RETAIL);

    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $item->update(['business_types' => ['restaurant']]);
    $barcode = $item->barcodes()->first();

    Sanctum::actingAs($this->cashier, ['*']);
    $this->getJson("/api/v1/items/barcode/{$barcode->barcode}")->assertNotFound();
});

it('clamps per_page=0 instead of dividing by zero', function () {
    Sanctum::actingAs($this->cashier, ['*']);
    $this->getJson('/api/v1/items?per_page=0')->assertOk();
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/v1/items')->assertUnauthorized();
});

it('rejects a user without items.view', function () {
    $user = User::factory()->create();
    $user->assignRole('Accountant');

    Sanctum::actingAs($user, ['*']);
    $this->getJson('/api/v1/items')->assertForbidden();
});
