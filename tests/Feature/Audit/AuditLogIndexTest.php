<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Models\Sale;
use App\Livewire\Audit\Index;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
});

function auditSale(): Sale
{
    return Sale::create([
        'number' => 'S-AUDIT-001',
        'user_id' => test()->owner->id,
        'stock_location_id' => test()->location->id,
    ]);
}

it('allows a user with audit.view to see the log', function () {
    $this->actingAs($this->owner)
        ->get(route('audit.index'))
        ->assertOk();
});

it('blocks a user without audit.view', function () {
    $this->actingAs($this->cashier)
        ->get(route('audit.index'))
        ->assertForbidden();
});

it('shows an activity row with the resolved subject label and causer', function () {
    $sale = auditSale();

    Activity::create([
        'log_name' => 'default',
        'description' => 'updated',
        'event' => 'updated',
        'subject_type' => Sale::class,
        'subject_id' => $sale->id,
        'causer_type' => User::class,
        'causer_id' => $this->owner->id,
        'attribute_changes' => ['attributes' => ['status' => 'voided'], 'old' => ['status' => 'completed']],
        'created_at' => now(),
    ]);

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->assertSee('Sale #S-AUDIT-001')
        ->assertSee($this->owner->name)
        ->assertSee('Updated');
});

it('filters activity by date range', function () {
    $sale = auditSale();

    Activity::create([
        'description' => 'in range',
        'event' => 'updated',
        'subject_type' => Sale::class,
        'subject_id' => $sale->id,
        'created_at' => now(),
    ]);

    Activity::create([
        'description' => 'out of range',
        'event' => 'updated',
        'subject_type' => Sale::class,
        'subject_id' => $sale->id,
        'created_at' => now()->subDays(60),
    ]);

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->assertSee('in range')
        ->assertDontSee('out of range');
});

it('filters activity by causer', function () {
    $sale = auditSale();
    $otherUser = User::factory()->create();

    Activity::create([
        'description' => 'by owner',
        'event' => 'updated',
        'subject_type' => Sale::class,
        'subject_id' => $sale->id,
        'causer_type' => User::class,
        'causer_id' => $this->owner->id,
        'created_at' => now(),
    ]);

    Activity::create([
        'description' => 'by other user',
        'event' => 'updated',
        'subject_type' => Sale::class,
        'subject_id' => $sale->id,
        'causer_type' => User::class,
        'causer_id' => $otherUser->id,
        'created_at' => now(),
    ]);

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('causerId', $this->owner->id)
        ->assertSee('by owner')
        ->assertDontSee('by other user');
});

it('filters activity by event type', function () {
    $sale = auditSale();

    Activity::create([
        'description' => 'created-event-marker',
        'event' => 'created',
        'subject_type' => Sale::class,
        'subject_id' => $sale->id,
        'created_at' => now(),
    ]);

    Activity::create([
        'description' => 'updated-event-marker',
        'event' => 'updated',
        'subject_type' => Sale::class,
        'subject_id' => $sale->id,
        'created_at' => now(),
    ]);

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('event', 'created')
        ->assertSee('created-event-marker')
        ->assertDontSee('updated-event-marker');
});
