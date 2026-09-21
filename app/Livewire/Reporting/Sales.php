<?php

declare(strict_types=1);

namespace App\Livewire\Reporting;

use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Reporting\Queries\SalesReportQuery;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Sales extends Component
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
        Gate::authorize('reports.sales');

        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();
        $locationId = $this->stock_location_id ?: null;
        $query = app(SalesReportQuery::class);

        return view('livewire.reporting.sales', [
            'summary' => $query->summary($from, $to, $locationId),
            'byDay' => $query->byDay($from, $to, $locationId),
            'byCategory' => $query->byCategory($from, $to, $locationId),
            'byPaymentMethod' => $query->byPaymentMethod($from, $to, $locationId),
            'topItems' => $query->topItems($from, $to, $locationId),
            'stockLocations' => StockLocation::orderBy('name')->get(),
        ]);
    }
}
