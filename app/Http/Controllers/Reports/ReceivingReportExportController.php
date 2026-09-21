<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Reporting\Queries\ReceivingReportQuery;
use App\Http\Controllers\Controller;
use App\Support\Csv\CsvSafe;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivingReportExportController extends Controller
{
    public function __invoke(Request $request, ReceivingReportQuery $query): StreamedResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $supplierId = $request->integer('supplier_id') ?: null;
        $stockLocationId = $request->integer('stock_location_id') ?: null;
        $type = $request->query('type') ?: null;

        return response()->streamDownload(function () use ($query, $from, $to, $supplierId, $stockLocationId, $type) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Number', 'Supplier', 'Location', 'Type', 'Total', 'Received at'], escape: '');

            foreach ($query->receivingsForExport($from, $to, $supplierId, $stockLocationId, $type) as $receiving) {
                fputcsv($handle, CsvSafe::row([
                    $receiving->number,
                    $receiving->supplier?->company_name,
                    $receiving->stockLocation?->name,
                    $receiving->type,
                    (string) $receiving->total->getAmount(),
                    $receiving->received_at->toDateTimeString(),
                ]), escape: '');
            }

            fclose($handle);
        }, 'receivings-report.csv', ['Content-Type' => 'text/csv']);
    }
}
