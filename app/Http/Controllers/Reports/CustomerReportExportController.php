<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Reporting\Queries\CustomerReportQuery;
use App\Http\Controllers\Controller;
use App\Support\Csv\CsvSafe;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerReportExportController extends Controller
{
    public function __invoke(Request $request, CustomerReportQuery $query): StreamedResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $stockLocationId = $request->integer('stock_location_id') ?: null;

        return response()->streamDownload(function () use ($query, $from, $to, $stockLocationId) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Customer', 'Sales', 'Total spend', 'Last purchase'], escape: '');

            foreach ($query->forExport($from, $to, $stockLocationId) as $row) {
                fputcsv($handle, CsvSafe::row([
                    $row->customer_name,
                    (string) $row->sale_count,
                    (string) $row->total->getAmount(),
                    Carbon::parse($row->last_purchase_at)->toDateString(),
                ]), escape: '');
            }

            fclose($handle);
        }, 'customers-report.csv', ['Content-Type' => 'text/csv']);
    }
}
