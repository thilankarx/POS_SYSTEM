<?php

declare(strict_types=1);

namespace App\Livewire\Sales\Shift;

use App\Domain\Sales\Actions\CloseShiftAction;
use App\Domain\Sales\Actions\OpenShiftAction;
use App\Domain\Sales\Actions\RecordCashMovementAction;
use App\Domain\Sales\Exceptions\ShiftException;
use App\Domain\Sales\Models\CashMovement;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use App\Support\Logging\DomainLog;
use App\Support\Money\Money;
use App\Support\Money\Rules\ValidMoneyAmount;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Manage extends Component
{
    public ?int $terminalId = null;

    public string $openingFloat = '0.00';

    /** @var array<string, int> */
    public array $cashCounts = [];

    public ?string $countedNonCash = null;

    public string $note = '';

    public string $movementDirection = 'out';

    public string $movementAmount = '0.00';

    public string $movementReason = '';

    public ?Shift $lastClosedShift = null;

    public function mount(): void
    {
        $terminal = $this->terminals()->first();
        $this->terminalId = $terminal?->id;
        $this->resetCashCounts();
    }

    public function updatedTerminalId(): void
    {
        $this->resetCashCounts();
        $this->lastClosedShift = null;
    }

    public function resetCashCounts(): void
    {
        $this->cashCounts = collect(config('pos.cash.denominations', []))
            ->mapWithKeys(fn ($denomination) => [(string) $denomination => 0])
            ->all();
    }

    public function open(): void
    {
        $terminal = $this->selectedTerminal();

        Gate::authorize('open', [Shift::class, $terminal]);

        try {
            app(OpenShiftAction::class)->execute($terminal, auth()->user(), $this->openingFloat, $this->note ?: null);
        } catch (ShiftException $e) {
            DomainLog::refused($e, ['terminal_id' => $this->terminalId]);
            $this->addError('openingFloat', $e->getMessage());

            return;
        }

        session()->flash('status', 'Shift opened.');
        $this->reset(['openingFloat', 'note']);
        $this->lastClosedShift = null;
    }

    public function close(): void
    {
        $shift = $this->selectedTerminal()?->openShift();

        if ($shift === null) {
            return;
        }

        Gate::authorize('close', $shift);

        $this->validate([
            'countedNonCash' => ['nullable', new ValidMoneyAmount, 'gte:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        // wire:model on a number input always sends its value over the wire
        // as a string, regardless of this property's `array<string, int>`
        // docblock -- that annotation is not enforced by Livewire, so a
        // count the cashier actually typed into (as opposed to the initial
        // 0 from resetCashCounts()) arrives here as a string and the
        // int-typed closures below threw a TypeError under strict_types.
        $rows = collect($this->cashCounts)
            ->map(fn ($count) => (int) $count)
            ->filter(fn (int $count) => $count > 0)
            ->map(fn (int $count, string $denomination) => ['denomination' => $denomination, 'count' => $count])
            ->values()
            ->all();

        try {
            $this->lastClosedShift = app(CloseShiftAction::class)->execute($shift, auth()->user(), $rows, $this->countedNonCash, $this->note ?: null);
        } catch (ShiftException $e) {
            DomainLog::refused($e, ['terminal_id' => $this->terminalId]);
            $this->addError('note', $e->getMessage());

            return;
        }

        session()->flash('status', 'Shift closed.');
        $this->reset(['countedNonCash', 'note']);
        $this->resetCashCounts();
    }

    public function recordCashMovement(): void
    {
        Gate::authorize('cash.movement');

        $shift = $this->currentShift();

        if ($shift === null) {
            return;
        }

        $validated = $this->validate([
            'movementDirection' => ['required', 'in:in,out'],
            'movementAmount' => ['required', new ValidMoneyAmount, 'gt:0'],
            'movementReason' => ['required', 'string', 'max:255'],
        ]);

        try {
            app(RecordCashMovementAction::class)->execute(
                shift: $shift,
                direction: $validated['movementDirection'],
                amount: $validated['movementAmount'],
                reason: $validated['movementReason'],
                user: auth()->user(),
            );
        } catch (ShiftException $e) {
            DomainLog::refused($e, ['shift_id' => $shift->id]);
            $this->addError('movementAmount', $e->getMessage());

            return;
        }

        session()->flash('status', 'Cash movement recorded.');
        $this->reset(['movementAmount', 'movementReason']);
    }

    public function terminals()
    {
        return Terminal::query()
            ->whereIn('stock_location_id', auth()->user()->stockLocations()->pluck('stock_locations.id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function selectedTerminal(): ?Terminal
    {
        return $this->terminalId ? Terminal::find($this->terminalId) : null;
    }

    public function currentShift(): ?Shift
    {
        return $this->selectedTerminal()?->openShift();
    }

    public function render(): View
    {
        $terminal = $this->selectedTerminal()?->load('stockLocation');
        $shift = $terminal?->openShift()?->load('openedBy');
        $cashMovements = $shift?->cashMovements()->with('user.person')->latest()->get() ?? collect();
        $shiftSales = null;

        if ($shift !== null) {
            $shiftSales = Sale::query()
                ->completed()
                ->revenue()
                ->where('shift_id', $shift->id)
                ->selectRaw('COUNT(*) as sale_count, COALESCE(SUM(total), 0) as total')
                ->first();
        }

        $countedCash = Money::zero();

        foreach ($this->cashCounts as $denomination => $count) {
            $countedCash = $countedCash->plus(
                Money::of($denomination)->multipliedBy(max(0, (int) $count))
            );
        }

        $cashIn = $cashMovements
            ->where('direction', 'in')
            ->reduce(fn ($total, CashMovement $movement) => $total->plus($movement->amount), Money::zero());
        $cashOut = $cashMovements
            ->where('direction', 'out')
            ->reduce(fn ($total, CashMovement $movement) => $total->plus($movement->amount), Money::zero());

        return view('livewire.sales.shift.manage', [
            'terminals' => $this->terminals(),
            'terminal' => $terminal,
            'shift' => $shift,
            'shiftSales' => $shiftSales,
            'countedCash' => $countedCash,
            'cashMovements' => $cashMovements,
            'cashIn' => $cashIn,
            'cashOut' => $cashOut,
        ]);
    }
}
