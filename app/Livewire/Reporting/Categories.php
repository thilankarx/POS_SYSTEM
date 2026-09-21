<?php

declare(strict_types=1);

namespace App\Livewire\Reporting;

use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Reporting\Queries\CategoryReportQuery;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Categories extends Component
{
    public string $from;

    public string $to;

    public ?int $stock_location_id = null;

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function render()
    {
        Gate::authorize('reports.categories');

        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();
        $query = app(CategoryReportQuery::class);

        return view('livewire.reporting.categories', [
            'rows' => $query->byCategory($from, $to, $this->stock_location_id ?: null),
            'stockLocations' => StockLocation::orderBy('name')->get(),
        ]);
    }
}
