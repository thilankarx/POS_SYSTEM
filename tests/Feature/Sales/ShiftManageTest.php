<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Models\CashMovement;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use App\Livewire\Sales\Shift\Manage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed();

    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
});

it('allows a cashier to open a shift for an assigned terminal', function () {
    Livewire::actingAs($this->cashier)
        ->test(Manage::class)
        ->set('terminalId', $this->terminal->id)
        ->set('openingFloat', '25.00')
        ->call('open')
        ->assertHasNoErrors();

    expect(Shift::where('terminal_id', $this->terminal->id)->where('status', Shift::STATUS_OPEN)->exists())
        ->toBeTrue();
});

it('denies recording a cash movement to a Cashier but allows it for an admin', function () {
    $shift = openShiftFor($this->cashier, $this->terminal);

    Livewire::actingAs($this->cashier)
        ->test(Manage::class)
        ->set('terminalId', $this->terminal->id)
        ->set('movementDirection', 'out')
        ->set('movementAmount', '20.00')
        ->set('movementReason', 'Safe drop')
        ->call('recordCashMovement')
        ->assertForbidden();

    expect(CashMovement::where('shift_id', $shift->id)->count())->toBe(0);

    Livewire::actingAs($this->admin)
        ->test(Manage::class)
        ->set('terminalId', $this->terminal->id)
        ->set('movementDirection', 'out')
        ->set('movementAmount', '20.00')
        ->set('movementReason', 'Safe drop')
        ->call('recordCashMovement')
        ->assertHasNoErrors();

    expect(CashMovement::where('shift_id', $shift->id)->count())->toBe(1);
});

it('logs a cash movement to the activity log -- there is no other audit trail for a manual drawer in/out', function () {
    $shift = openShiftFor($this->cashier, $this->terminal);

    Livewire::actingAs($this->admin)
        ->test(Manage::class)
        ->set('terminalId', $this->terminal->id)
        ->set('movementDirection', 'out')
        ->set('movementAmount', '20.00')
        ->set('movementReason', 'Safe drop')
        ->call('recordCashMovement')
        ->assertHasNoErrors();

    $movement = CashMovement::where('shift_id', $shift->id)->firstOrFail();
    $activity = Activity::where('subject_type', CashMovement::class)
        ->where('subject_id', $movement->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes->get('attributes')['direction'])->toBe('out')
        ->and($activity->attribute_changes->get('attributes')['reason'])->toBe('Safe drop');
});

it('exposes the closed shift with its cash reconciliation after closing', function () {
    $shift = openShiftFor($this->cashier, $this->terminal, '100.00');

    $component = Livewire::actingAs($this->cashier)
        ->test(Manage::class)
        ->set('terminalId', $this->terminal->id)
        ->set('cashCounts.1000', 1)
        ->call('close')
        ->assertHasNoErrors();

    $lastClosedShift = $component->get('lastClosedShift');

    expect($lastClosedShift)->not->toBeNull()
        ->and($lastClosedShift->id)->toBe($shift->id)
        ->and($lastClosedShift->status)->toBe(Shift::STATUS_CLOSED)
        ->and((string) $lastClosedShift->counted_cash)->toBe('LKR 1000.00')
        ->and((string) $lastClosedShift->cash_variance)->toBe('LKR 900.00');
});

it('closes a shift when a cash-count field was left blank', function () {
    // manage.blade.php's cash-count fields are wire:model-bound <input
    // type="number"> elements: a field the cashier never touched, or
    // cleared, sends an empty string over the wire for that denomination
    // -- unlike resetCashCounts()'s initial native-int 0, or the sibling
    // test above which only ever exercises a filled-in field. PHP's
    // implicit int coercion (used when close()'s int-typed closures are
    // invoked from inside Collection/Arr, which don't declare
    // strict_types) accepts a numeric string like "1" but throws a
    // TypeError on a non-numeric one -- and an empty string is
    // non-numeric.
    $shift = openShiftFor($this->cashier, $this->terminal, '100.00');

    Livewire::actingAs($this->cashier)
        ->test(Manage::class)
        ->set('terminalId', $this->terminal->id)
        ->set('cashCounts.1000', '5')
        ->set('cashCounts.500', '')
        ->call('close')
        ->assertHasNoErrors();

    expect($shift->fresh()->status)->toBe(Shift::STATUS_CLOSED);
});

it('shows the live drawer total and validates the non-cash count', function () {
    openShiftFor($this->cashier, $this->terminal, '100.00');

    Livewire::actingAs($this->cashier)
        ->test(Manage::class)
        ->set('terminalId', $this->terminal->id)
        ->set('cashCounts.1000', 2)
        ->set('cashCounts.500', 1)
        ->assertSee('2,500.00')
        ->set('countedNonCash', 'not-money')
        ->call('close')
        ->assertHasErrors(['countedNonCash']);
});
