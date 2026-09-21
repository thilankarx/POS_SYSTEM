<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Actions\CloseShiftAction;
use App\Domain\Sales\Actions\RecordCashMovementAction;
use App\Domain\Sales\Exceptions\ShiftException;
use App\Domain\Sales\Models\CashMovement;
use App\Domain\Sales\Models\Terminal;

beforeEach(function () {
    $this->seed();

    $this->user = User::where('username', 'cashier')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();

    $this->shift = openShiftFor($this->user, $this->terminal, '100.00');
});

it('records an out movement against an open shift', function () {
    $movement = app(RecordCashMovementAction::class)->execute(
        shift: $this->shift,
        direction: 'out',
        amount: '50.00',
        reason: 'Safe drop',
        user: $this->user,
    );

    expect($movement)->toBeInstanceOf(CashMovement::class)
        ->and($movement->shift_id)->toBe($this->shift->id)
        ->and($movement->user_id)->toBe($this->user->id)
        ->and($movement->direction)->toBe('out')
        ->and((string) $movement->amount->getAmount())->toBe('50.00')
        ->and($movement->reason)->toBe('Safe drop');
});

it('records an in movement against an open shift', function () {
    $movement = app(RecordCashMovementAction::class)->execute(
        shift: $this->shift,
        direction: 'in',
        amount: '20.00',
        reason: 'Petty cash return',
        user: $this->user,
    );

    expect($movement->direction)->toBe('in')
        ->and((string) $movement->amount->getAmount())->toBe('20.00');
});

it('refuses to record a movement against a closed shift', function () {
    app(CloseShiftAction::class)->execute($this->shift, $this->user, [['denomination' => '100', 'count' => 1]]);

    app(RecordCashMovementAction::class)->execute(
        shift: $this->shift->fresh(),
        direction: 'out',
        amount: '10.00',
        reason: 'Too late',
        user: $this->user,
    );
})->throws(ShiftException::class, 'not open');

it('sets cash_dropped to the sum of out movements at close time', function () {
    app(RecordCashMovementAction::class)->execute($this->shift, 'out', '30.00', 'Safe drop 1', $this->user);
    app(RecordCashMovementAction::class)->execute($this->shift, 'out', '15.00', 'Safe drop 2', $this->user);
    app(RecordCashMovementAction::class)->execute($this->shift, 'in', '5.00', 'Petty cash return', $this->user);

    // expected cash: 100.00 opening + 5.00 in - 45.00 out = 60.00
    $shift = app(CloseShiftAction::class)->execute($this->shift->fresh(), $this->user, [
        ['denomination' => '50', 'count' => 1],
        ['denomination' => '10', 'count' => 1],
    ]);

    expect((string) $shift->cash_dropped->getAmount())->toBe('45.00')
        ->and((string) $shift->expected_cash->getAmount())->toBe('60.00');
});
