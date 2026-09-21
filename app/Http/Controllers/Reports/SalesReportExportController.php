<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Reporting\Queries\SalesReportQuery;
use App\Http\Controllers\Controller;
use App\Support\Csv\CsvSafe;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportExportController extends Controller
{
    public function __invoke(Request $request, SalesReportQuery $query): StreamedResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $stockLocationId = $request->integer('stock_location_id') ?: null;

        return response()->streamDownload(function () use ($query, $from, $to, $stockLocationId) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Number', 'Date', 'Type', 'Customer', 'Subtotal', 'Discount', 'Tax', 'Total', 'Cost'], escape: '');

            foreach ($query->salesForExport($from, $to, $stockLocationId) as $sale) {
                fputcsv($handle, CsvSafe::row([
                    $sale->number,
                    $sale->sold_at->toDateTimeString(),
                    $sale->sale_type,
                    $sale->customer?->company_name,
                    (string) $sale->subtotal->getAmount(),
                    (string) $sale->discount_total->getAmount(),
                    (string) $sale->tax_total->getAmount(),
                    (string) $sale->total->getAmount(),
                    (string) $sale->cost_total->getAmount(),
                ]), escape: '');
            }

            fclose($handle);
        }, 'sales-report.csv', ['Content-Type' => 'text/csv']);
    }
}
