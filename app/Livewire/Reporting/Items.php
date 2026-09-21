<?php

declare(strict_types=1);

namespace App\Livewire\Reporting;

use App\Domain\Catalog\Models\Category;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Reporting\Queries\ItemReportQuery;
use App\Settings\BusinessProfileSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Items extends Component
{
    public string $from;

    public string $to;

    public ?int $stock_location_id = null;

    public ?int $category_id = null;

    /** Defaults to the configured store type; 'all' includes every business type. */
    public string $businessType = '';

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
        $this->businessType = app(BusinessProfileSettings::class)->business_type;
    }

    public function render()
    {
        Gate::authorize('reports.items');

        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();
        $query = app(ItemReportQuery::class);

        return view('livewire.reporting.items', [
            'rows' => $query->byItem(
                $from,
                $to,
                $this->stock_location_id ?: null,
                $this->category_id ?: null,
                $this->businessType === 'all' ? null : ($this->businessType ?: null),
            ),
            'stockLocations' => StockLocation::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'businessTypes' => BusinessProfileSettings::businessTypes(),
        ]);
    }
}
