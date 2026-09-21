<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Events\ShiftClosed;
use App\Domain\Sales\Events\ShiftOpened;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use App\Listeners\LogShiftClosed;
use App\Listeners\LogShiftOpened;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
});

it('logs a shift-opened activity with the opening user as causer', function () {
    $shift = openShiftFor($this->owner, $this->terminal, '150.00');

    (new LogShiftOpened)->handle(new ShiftOpened($shift));

    $activity = Activity::where('log_name', 'shift')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($shift->id)
        ->and($activity->causer_id)->toBe($this->owner->id)
        ->and($activity->description)->toContain('opened')
        ->and($activity->properties->get('terminal_id'))->toBe($this->terminal->id);
});

it('logs a shift-closed activity with the closing user as causer', function () {
    $shift = openShiftFor($this->owner, $this->terminal, '150.00');
    $shift->update([
        'status' => Shift::STATUS_CLOSED,
        'closed_by_user_id' => $this->owner->id,
        'closed_at' => now(),
        'expected_cash' => '150.00',
        'counted_cash' => '150.00',
        'cash_variance' => '0.00',
    ]);

    (new LogShiftClosed)->handle(new ShiftClosed($shift->fresh()));

    $activity = Activity::where('log_name', 'shift')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($shift->id)
        ->and($activity->causer_id)->toBe($this->owner->id)
        ->and($activity->description)->toContain('closed')
        ->and($activity->properties->get('cash_variance'))->toBe('LKR 0.00');
});
