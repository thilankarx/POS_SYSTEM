<?php

declare(strict_types=1);

namespace App\Livewire\Sales\Shift;

use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use App\Support\Money\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class History extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $terminal = '';

    #[Url(except: '')]
    public string $period = '';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public ?int $expandedShiftId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedTerminal(): void
    {
        $this->resetPage();
    }

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'terminal', 'period', 'sort', 'expandedShiftId']);
        $this->sort = 'newest';
        $this->resetPage();
    }

    public function toggle(int $shiftId): void
    {
        $this->expandedShiftId = $this->expandedShiftId === $shiftId ? null : $shiftId;
    }

    private function filteredQuery(): Builder
    {
        return Shift::query()
            ->when(trim($this->search) !== '', function (Builder $query) {
                $search = trim($this->search);

                $query->where(function (Builder $query) use ($search) {
                    $query->whereHas('terminal', fn (Builder $query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('openedBy', fn (Builder $query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhereHas('person', fn (Builder $query) => $query
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")));
                });
            })
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->terminal !== '', fn (Builder $query) => $query->where('terminal_id', $this->terminal))
            ->when($this->period === 'today', fn (Builder $query) => $query->whereDate('opened_at', today()))
            ->when($this->period === '7_days', fn (Builder $query) => $query->where('opened_at', '>=', now()->subDays(7)->startOfDay()))
            ->when($this->period === '30_days', fn (Builder $query) => $query->where('opened_at', '>=', now()->subDays(30)->startOfDay()));
    }

    public function render(): View
    {
        // Route middleware alone (routes/backoffice.php's
        // permission:shifts.view_all) does not gate a direct Livewire
        // component request -- this needs its own check, same as every
        // other component. shifts.view_all is deliberately cross-location
        // (unlike sales.view/inventory.view elsewhere, there is no separate
        // per-location variant), so unlike those this list is intentionally
        // not scoped to the viewer's own locations.
        Gate::authorize('viewAny', Shift::class);

        $query = $this->filteredQuery()
            ->with(['terminal.stockLocation', 'openedBy.person', 'closedBy.person'])
            ->withSum(['sales as sales_total' => fn ($query) => $query->completed()->revenue()], 'total')
            ->withCount(['sales as sales_count' => fn ($query) => $query->completed()->revenue()]);

        match ($this->sort) {
            'oldest' => $query->oldest('opened_at'),
            'variance' => $query->orderByRaw('ABS(COALESCE(cash_variance, 0)) DESC')->latest('opened_at'),
            default => $query->latest('opened_at'),
        };

        $shifts = $query->paginate(20);
        $netVariance = $this->filteredQuery()
            ->where('status', Shift::STATUS_CLOSED)
            ->selectRaw('COALESCE(SUM(cash_variance), 0) as aggregate')
            ->value('aggregate');

        return view('livewire.sales.shift.history', [
            'shifts' => $shifts,
            'terminals' => Terminal::with('stockLocation')->orderBy('name')->get(),
            'summary' => [
                'open' => Shift::where('status', Shift::STATUS_OPEN)->count(),
                'closed_today' => Shift::where('status', Shift::STATUS_CLOSED)->whereDate('closed_at', today())->count(),
                'results' => $shifts->total(),
                'net_variance' => Money::of((string) $netVariance),
            ],
        ]);
    }
}
