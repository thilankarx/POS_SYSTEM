<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Reporting\Queries\SupplierReportQuery;
use App\Http\Controllers\Controller;
use App\Support\Csv\CsvSafe;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierReportExportController extends Controller
{
    public function __invoke(Request $request, SupplierReportQuery $query): StreamedResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $stockLocationId = $request->integer('stock_location_id') ?: null;

        return response()->streamDownload(function () use ($query, $from, $to, $stockLocationId) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Supplier', 'Receivings', 'Total spend', 'Avg receiving'], escape: '');

            foreach ($query->forExport($from, $to, $stockLocationId) as $row) {
                fputcsv($handle, CsvSafe::row([
                    $row->company_name,
                    (string) $row->receiving_count,
                    (string) $row->total->getAmount(),
                    number_format((float) $row->avg_receiving, 2, '.', ''),
                ]), escape: '');
            }

            fclose($handle);
        }, 'suppliers-report.csv', ['Content-Type' => 'text/csv']);
    }
}
