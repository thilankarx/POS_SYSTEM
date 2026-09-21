<?php

declare(strict_types=1);

namespace App\Livewire\Reporting;

use App\Domain\Reporting\Queries\ShiftReportQuery;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Shifts extends Component
{
    use WithPagination;

    #[Url]
    public string $from;

    #[Url]
    public string $to;

    #[Url(except: '')]
    public ?int $terminal_id = null;

    #[Url(except: '')]
    public string $status = '';

    public ?int $expandedShiftId = null;

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function updatingFrom(): void
    {
        $this->resetPage();
    }

    public function updatingTo(): void
    {
        $this->resetPage();
    }

    public function updatingTerminalId(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function setRange(string $range): void
    {
        [$from, $to] = match ($range) {
            'today' => [today(), today()],
            '7_days' => [today()->subDays(6), today()],
            default => [today()->startOfMonth(), today()],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->terminal_id = null;
        $this->status = '';
        $this->expandedShiftId = null;
        $this->setRange('month');
    }

    public function toggle(int $shiftId): void
    {
        $this->expandedShiftId = $this->expandedShiftId === $shiftId ? null : $shiftId;
    }

    public function render(): View
    {
        Gate::authorize('reports.shifts');

        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();
        $query = app(ShiftReportQuery::class);

        $expandedShift = $this->expandedShiftId ? Shift::find($this->expandedShiftId) : null;

        return view('livewire.reporting.shifts', [
            'shifts' => $query->list($from, $to, $this->terminal_id ?: null, $this->status ?: null),
            'summary' => $query->summary($from, $to, $this->terminal_id ?: null, $this->status ?: null),
            'denominationBreakdown' => $expandedShift ? $query->denominationBreakdown($expandedShift) : null,
            'cashMovements' => $expandedShift ? $query->cashMovements($expandedShift) : null,
            'terminals' => Terminal::with('stockLocation')->orderBy('name')->get(),
        ]);
    }
}
