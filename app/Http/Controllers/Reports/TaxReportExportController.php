<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Reporting\Queries\TaxReportQuery;
use App\Http\Controllers\Controller;
use App\Support\Csv\CsvSafe;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxReportExportController extends Controller
{
    public function __invoke(Request $request, TaxReportQuery $query): StreamedResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $stockLocationId = $request->integer('stock_location_id') ?: null;

        return response()->streamDownload(function () use ($query, $from, $to, $stockLocationId) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Tax', 'Rate', 'Taxable amount', 'Tax collected'], escape: '');

            foreach ($query->forExport($from, $to, $stockLocationId) as $row) {
                fputcsv($handle, CsvSafe::row([
                    $row->name,
                    (string) $row->rate,
                    (string) $row->taxable_amount->getAmount(),
                    (string) $row->tax_amount->getAmount(),
                ]), escape: '');
            }

            fclose($handle);
        }, 'taxes-report.csv', ['Content-Type' => 'text/csv']);
    }
}
