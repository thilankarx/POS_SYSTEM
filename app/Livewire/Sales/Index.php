<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Sales\Models\Sale;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $location = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function updatingLocation(): void
    {
        $this->resetPage();
    }

    public function updatingFrom(): void
    {
        $this->resetPage();
    }

    public function updatingTo(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function setRange(string $range): void
    {
        [$from, $to] = match ($range) {
            'today' => [today(), today()],
            '7_days' => [today()->subDays(6), today()],
            default => [null, null],
        };

        $this->from = $from?->toDateString() ?? '';
        $this->to = $to?->toDateString() ?? '';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'type', 'location', 'from', 'to', 'sort']);
        $this->sort = 'newest';
        $this->resetPage();
    }

    private function filteredQuery(): Builder
    {
        $locationIds = auth()->user()->stockLocations()->pluck('stock_locations.id');

        return Sale::query()
            ->whereIn('stock_location_id', $locationIds)
            ->when(trim($this->search) !== '', function (Builder $query) {
                $search = trim($this->search);

                $query->where(function (Builder $query) use ($search) {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%")
                        ->orWhere('quote_number', 'like', "%{$search}%")
                        ->orWhere('work_order_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $query) => $query
                            ->where('company_name', 'like', "%{$search}%")
                            ->orWhereHas('person', fn (Builder $query) => $query
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")));
                });
            })
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->type !== '', fn (Builder $query) => $query->where('sale_type', $this->type))
            ->when($this->location !== '', fn (Builder $query) => $query->where('stock_location_id', $this->location))
            ->when($this->from !== '', fn (Builder $query) => $query->where('sold_at', '>=', Carbon::parse($this->from)->startOfDay()))
            ->when($this->to !== '', fn (Builder $query) => $query->where('sold_at', '<=', Carbon::parse($this->to)->endOfDay()));
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Sale::class);

        $query = $this->filteredQuery()
            ->with(['customer.person', 'user.person', 'stockLocation', 'payments.method']);

        match ($this->sort) {
            'oldest' => $query->oldest('sold_at'),
            'total_desc' => $query->orderByDesc('total')->latest('sold_at'),
            default => $query->latest('sold_at'),
        };

        $sales = $query->paginate(20);
        $completed = $this->filteredQuery()
            ->completed()
            ->revenue()
            ->selectRaw('COUNT(*) as sale_count, COALESCE(SUM(total), 0) as total')
            ->first();
        $refundActivity = $this->filteredQuery()
            ->where(function (Builder $query) {
                $query->whereIn('status', [Sale::STATUS_REFUNDED, Sale::STATUS_PARTIALLY_REFUNDED])
                    ->orWhere('sale_type', Sale::TYPE_RETURN);
            })
            ->count();

        return view('livewire.sales.index', [
            'sales' => $sales,
            'stockLocations' => auth()->user()->stockLocations()->orderBy('name')->get(),
            'summary' => [
                'total' => $completed->total,
                'completed' => (int) $completed->sale_count,
                'refunds' => $refundActivity,
                'voided' => $this->filteredQuery()->where('status', Sale::STATUS_VOIDED)->count(),
            ],
        ]);
    }
}
