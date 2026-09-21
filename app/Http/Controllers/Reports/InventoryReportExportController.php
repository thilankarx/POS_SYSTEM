<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Reporting\Queries\InventoryReportQuery;
use App\Http\Controllers\Controller;
use App\Support\Csv\CsvSafe;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryReportExportController extends Controller
{
    public function __invoke(Request $request, InventoryReportQuery $query): StreamedResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $itemId = $request->integer('item_id') ?: null;
        $stockLocationId = $request->integer('stock_location_id') ?: null;
        $reason = $request->query('reason') ?: null;

        return response()->streamDownload(function () use ($query, $from, $to, $itemId, $stockLocationId, $reason) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Item', 'SKU', 'Location', 'Quantity delta', 'Reason', 'Unit cost'], escape: '');

            foreach ($query->movementsForExport($from, $to, $itemId, $stockLocationId, $reason) as $movement) {
                fputcsv($handle, CsvSafe::row([
                    $movement->occurred_at->toDateTimeString(),
                    $movement->item?->name,
                    $movement->item?->sku,
                    $movement->stockLocation?->name,
                    (string) $movement->quantity_delta,
                    $movement->reason,
                    $movement->unit_cost ? (string) $movement->unit_cost->getAmount() : '',
                ]), escape: '');
            }

            fclose($handle);
        }, 'inventory-report.csv', ['Content-Type' => 'text/csv']);
    }
}
