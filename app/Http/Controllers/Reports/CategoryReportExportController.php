<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Reporting\Queries\CategoryReportQuery;
use App\Http\Controllers\Controller;
use App\Support\Csv\CsvSafe;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryReportExportController extends Controller
{
    public function __invoke(Request $request, CategoryReportQuery $query): StreamedResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $stockLocationId = $request->integer('stock_location_id') ?: null;

        return response()->streamDownload(function () use ($query, $from, $to, $stockLocationId) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Category', 'Qty sold', 'Revenue', 'Cost', 'Margin'], escape: '');

            foreach ($query->forExport($from, $to, $stockLocationId) as $row) {
                fputcsv($handle, CsvSafe::row([
                    $row->category_name,
                    (string) $row->quantity,
                    (string) $row->line_total->getAmount(),
                    (string) $row->cost_price->getAmount(),
                    (string) $row->line_total->minus($row->cost_price)->getAmount(),
                ]), escape: '');
            }

            fclose($handle);
        }, 'categories-report.csv', ['Content-Type' => 'text/csv']);
    }
}
