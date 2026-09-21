<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Actions\ApproveStockCountAction;
use App\Domain\Inventory\Actions\CreateStockCountAction;
use App\Domain\Inventory\Actions\GenerateStockCountLinesAction;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Inventory\Models\StockLocation;
use App\Livewire\Inventory\StockCounts\Form as StockCountForm;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->stockClerk = User::factory()->create();
    $this->stockClerk->assignRole('Stock Clerk');
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->cola = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

it('lets a Cashier view the stock counts list but denies creating one (inventory.view without inventory.count)', function () {
    $this->actingAs($this->cashier)->get(route('stock-counts.index'))->assertOk();
    $this->actingAs($this->cashier)->get(route('stock-counts.create'))->assertForbidden();
});

it('lets a Stock Clerk create a count, generate lines, enter counts and submit for review', function () {
    $this->actingAs($this->stockClerk)->get(route('stock-counts.create'))->assertOk();

    $component = Livewire::actingAs($this->stockClerk)
        ->test(StockCountForm::class)
        ->set('stock_location_id', $this->location->id)
        ->set('is_blind', false)
        ->call('createDraft')
        ->assertHasNoErrors();

    $count = StockCount::where('stock_location_id', $this->location->id)->latest()->firstOrFail();
    expect($count->status)->toBe(StockCount::STATUS_DRAFT);

    $component = Livewire::actingAs($this->stockClerk)
        ->test(StockCountForm::class, ['stockCount' => $count])
        ->set('countAllItems', false)
        ->set('selectedItemIds', [$this->cola->id])
        ->call('generateLines')
        ->assertHasNoErrors();

    $count = $count->fresh('lines');
    expect($count->status)->toBe(StockCount::STATUS_COUNTING)
        ->and($count->lines)->toHaveCount(1);

    $line = $count->lines->first();

    $component = Livewire::actingAs($this->stockClerk)
        ->test(StockCountForm::class, ['stockCount' => $count])
        ->set("counts.{$line->id}", (string) $line->expected_quantity)
        ->call('saveCounts')
        ->assertHasNoErrors()
        ->call('submit')
        ->assertHasNoErrors();

    expect($count->fresh()->status)->toBe(StockCount::STATUS_REVIEW);
});

it('denies approval to a Stock Clerk but allows it for an admin', function () {
    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);
    $count = app(GenerateStockCountLinesAction::class)->execute($count, [$this->cola->id]);
    $line = $count->lines->first();

    Livewire::actingAs($this->stockClerk)
        ->test(StockCountForm::class, ['stockCount' => $count->fresh('lines')])
        ->set("counts.{$line->id}", (string) $line->expected_quantity)
        ->call('saveCounts')
        ->call('submit');

    $count = $count->fresh('lines');

    Livewire::actingAs($this->stockClerk)
        ->test(StockCountForm::class, ['stockCount' => $count])
        ->call('approve')
        ->assertForbidden();

    Livewire::actingAs($this->admin)
        ->test(StockCountForm::class, ['stockCount' => $count])
        ->call('approve')
        ->assertHasNoErrors();

    expect($count->fresh()->status)->toBe(StockCount::STATUS_APPROVED);
});

it('denies unapproval to a Stock Clerk but allows it for an admin', function () {
    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);
    $count = app(GenerateStockCountLinesAction::class)->execute($count, [$this->cola->id]);
    $line = $count->lines->first();

    Livewire::actingAs($this->stockClerk)
        ->test(StockCountForm::class, ['stockCount' => $count->fresh('lines')])
        ->set("counts.{$line->id}", (string) $line->expected_quantity)
        ->call('saveCounts')
        ->call('submit');

    $count = app(ApproveStockCountAction::class)->execute($count->fresh('lines'), $this->admin);

    Livewire::actingAs($this->stockClerk)
        ->test(StockCountForm::class, ['stockCount' => $count])
        ->call('unapprove')
        ->assertForbidden();

    Livewire::actingAs($this->admin)
        ->test(StockCountForm::class, ['stockCount' => $count])
        ->call('unapprove')
        ->assertHasNoErrors();

    expect($count->fresh()->status)->toBe(StockCount::STATUS_REVIEW);
});
