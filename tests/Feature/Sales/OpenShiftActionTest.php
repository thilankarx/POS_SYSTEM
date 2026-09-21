<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Actions\OpenShiftAction;
use App\Domain\Sales\Events\ShiftOpened;
use App\Domain\Sales\Exceptions\ShiftException;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed();

    $this->user = User::where('username', 'cashier')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
});

it('opens a shift', function () {
    Event::fake([ShiftOpened::class]);

    $shift = app(OpenShiftAction::class)->execute($this->terminal, $this->user, '100.00');

    expect($shift->status)->toBe(Shift::STATUS_OPEN)
        ->and($shift->opened_by_user_id)->toBe($this->user->id)
        ->and($shift->terminal_id)->toBe($this->terminal->id)
        ->and((string) $shift->opening_float->getAmount())->toBe('100.00');

    Event::assertDispatched(ShiftOpened::class, fn (ShiftOpened $e) => $e->shift->is($shift));
});

it('refuses to open a second shift on a terminal that already has one open', function () {
    app(OpenShiftAction::class)->execute($this->terminal, $this->user, '100.00');

    app(OpenShiftAction::class)->execute($this->terminal, $this->user, '50.00');
})->throws(ShiftException::class, 'already');

it('refuses to open a shift on an inactive terminal', function () {
    $this->terminal->update(['is_active' => false]);

    app(OpenShiftAction::class)->execute($this->terminal, $this->user, '100.00');
})->throws(ShiftException::class, 'not active');

it('refuses to open a shift for a user who cannot operate at the terminal', function () {
    $warehouse = StockLocation::where('code', 'WH')->firstOrFail();
    $wOnlyTerminal = Terminal::create([
        'stock_location_id' => $warehouse->id,
        'code' => 'WH-T1',
        'name' => 'Warehouse Terminal',
        'is_active' => true,
    ]);

    // The cashier is only attached to MAIN, not WH.
    app(OpenShiftAction::class)->execute($wOnlyTerminal, $this->user, '100.00');
})->throws(ShiftException::class, 'not authorized');
