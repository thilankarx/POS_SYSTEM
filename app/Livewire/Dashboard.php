<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Domain\Purchasing\Queries\ReorderSuggestionsQuery;
use App\Domain\Reporting\Queries\SalesReportQuery;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Shift;
use App\Support\Money\Money;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        $locationIds = $user->stockLocations()->pluck('stock_locations.id');
        $primaryLocation = $user->stockLocations()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();

        $todaySales = $user->can('reports.sales')
            ? app(SalesReportQuery::class)->summary(now()->startOfDay(), now()->endOfDay())
            : null;

        $openShift = null;
        $shiftSales = null;

        if ($user->canAny(['shifts.open', 'shifts.close', 'shifts.view_all'])) {
            $openShift = Shift::query()
                ->with('terminal.stockLocation')
                ->where('status', Shift::STATUS_OPEN)
                ->whereHas('terminal', fn ($query) => $query->whereIn('stock_location_id', $locationIds))
                ->when(! $user->can('shifts.view_all'), fn ($query) => $query->where('opened_by_user_id', $user->id))
                ->latest('opened_at')
                ->first();

            if ($openShift !== null) {
                $shiftSales = Sale::query()
                    ->completed()
                    ->revenue()
                    ->where('shift_id', $openShift->id)
                    ->selectRaw('COUNT(*) as sale_count, COALESCE(SUM(total), 0) as total')
                    ->first();
            }
        }

        $recentSales = collect();

        if ($user->can('sales.view')) {
            $recentSales = Sale::query()
                ->completed()
                ->revenue()
                ->with(['customer.person', 'user.person'])
                ->whereIn('stock_location_id', $locationIds)
                ->latest('sold_at')
                ->limit(6)
                ->get();
        }

        $lowStockItems = collect();

        if ($user->can('inventory.view') && $primaryLocation !== null) {
            $lowStockItems = app(ReorderSuggestionsQuery::class)
                ->forLocation($primaryLocation)
                ->sortBy(fn ($item) => (float) $item->on_hand)
                ->values();
        }

        $purchaseSummary = null;
        $purchaseOrders = collect();
        $invoiceSummary = null;

        if ($user->can('purchasing.view')) {
            $receivableStatuses = [
                PurchaseOrder::STATUS_APPROVED,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ];

            $purchaseSummary = [
                'awaiting_approval' => PurchaseOrder::where('status', PurchaseOrder::STATUS_SUBMITTED)->count(),
                'to_receive' => PurchaseOrder::whereIn('status', $receivableStatuses)->count(),
                'overdue' => PurchaseOrder::query()
                    ->whereIn('status', [
                        PurchaseOrder::STATUS_DRAFT,
                        PurchaseOrder::STATUS_SUBMITTED,
                        ...$receivableStatuses,
                    ])
                    ->whereDate('expected_on', '<', today())
                    ->count(),
            ];

            $purchaseOrders = PurchaseOrder::query()
                ->with(['supplier', 'stockLocation'])
                ->whereIn('status', $receivableStatuses)
                ->orderByRaw('expected_on IS NULL, expected_on ASC')
                ->latest('id')
                ->limit(6)
                ->get();

            $outstanding = SupplierInvoice::query()
                ->where('status', '!=', SupplierInvoice::STATUS_PAID)
                ->selectRaw('COALESCE(SUM(total - paid_total), 0) as aggregate')
                ->value('aggregate');

            $invoiceSummary = [
                'outstanding' => Money::of((string) $outstanding),
                'overdue' => SupplierInvoice::query()
                    ->where('status', '!=', SupplierInvoice::STATUS_PAID)
                    ->whereDate('due_date', '<', today())
                    ->count(),
                'disputed' => SupplierInvoice::where('status', SupplierInvoice::STATUS_DISPUTED)->count(),
            ];
        }

        return view('livewire.dashboard', [
            'todaySales' => $todaySales,
            'openShift' => $openShift,
            'shiftSales' => $shiftSales,
            'recentSales' => $recentSales,
            'primaryLocation' => $primaryLocation,
            'lowStockItems' => $lowStockItems,
            'purchaseSummary' => $purchaseSummary,
            'purchaseOrders' => $purchaseOrders,
            'invoiceSummary' => $invoiceSummary,
        ]);
    }
}
