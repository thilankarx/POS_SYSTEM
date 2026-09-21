<?php

declare(strict_types=1);

namespace App\Livewire\Reporting;

use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Reporting\Queries\PaymentReportQuery;
use App\Domain\Sales\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Payments extends Component
{
    use WithPagination;

    public string $from;

    public string $to;

    public ?int $payment_method_id = null;

    public ?int $stock_location_id = null;

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

    public function updatingPaymentMethodId(): void
    {
        $this->resetPage();
    }

    public function updatingStockLocationId(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        Gate::authorize('reports.payments');

        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();
        $query = app(PaymentReportQuery::class);

        return view('livewire.reporting.payments', [
            'payments' => $query->payments($from, $to, $this->payment_method_id ?: null, $this->stock_location_id ?: null),
            'summaryByMethod' => $query->summaryByMethod($from, $to, $this->stock_location_id ?: null),
            'paymentMethods' => PaymentMethod::orderBy('name')->get(),
            'stockLocations' => StockLocation::orderBy('name')->get(),
        ]);
    }
}
