<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use App\Livewire\Reporting\Shifts;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
});

it('filters the shift report and manages quick date ranges', function () {
    $terminal = Terminal::where('code', 'T1')->firstOrFail();
    $openShift = openShiftFor($this->admin, $terminal, '100.00');

    Livewire::actingAs($this->admin)
        ->test(Shifts::class)
        ->assertSee('Shift and cash report')
        ->assertSee('Net variance')
        ->set('status', Shift::STATUS_OPEN)
        ->assertViewHas('shifts', fn ($shifts) => $shifts->contains('id', $openShift->id)
            && $shifts->every(fn (Shift $shift) => $shift->status === Shift::STATUS_OPEN))
        ->call('setRange', 'today')
        ->assertSet('from', today()->toDateString())
        ->assertSet('to', today()->toDateString())
        ->call('clearFilters')
        ->assertSet('status', '')
        ->assertSet('terminal_id', null)
        ->assertSet('from', today()->startOfMonth()->toDateString())
        ->assertSet('to', today()->toDateString());
});
