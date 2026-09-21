<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use App\Livewire\Sales\Shift\History;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->firstTerminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->secondTerminal = Terminal::where('code', 'T2')->firstOrFail();
});

it('presents searchable shift reconciliation history', function () {
    $openShift = openShiftFor($this->cashier, $this->firstTerminal, '100.00');
    $closedShift = Shift::create([
        'terminal_id' => $this->secondTerminal->id,
        'opened_by_user_id' => $this->cashier->id,
        'closed_by_user_id' => $this->admin->id,
        'opening_float' => '200.00',
        'expected_cash' => '250.00',
        'counted_cash' => '245.00',
        'cash_variance' => '-5.00',
        'cash_dropped' => '20.00',
        'status' => Shift::STATUS_CLOSED,
        'opened_at' => now()->subHours(3),
        'closed_at' => now()->subHour(),
        'note' => 'End of day count',
    ]);

    Livewire::actingAs($this->admin)
        ->test(History::class)
        ->assertSee('Open now')
        ->assertSee('Net variance')
        ->assertViewHas('shifts', fn ($shifts) => $shifts->contains('id', $openShift->id)
            && $shifts->contains('id', $closedShift->id))
        ->set('status', Shift::STATUS_CLOSED)
        ->assertViewHas('shifts', fn ($shifts) => ! $shifts->contains('id', $openShift->id)
            && $shifts->contains('id', $closedShift->id))
        ->set('search', $this->secondTerminal->code)
        ->assertViewHas('shifts', fn ($shifts) => $shifts->count() === 1
            && $shifts->first()->is($closedShift))
        ->call('toggle', $closedShift->id)
        ->assertSee('End of day count')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('status', '')
        ->assertSet('sort', 'newest');
});

it('denies shift history to users without cross-shift access', function () {
    Livewire::actingAs($this->cashier)
        ->test(History::class)
        ->assertForbidden();
});
