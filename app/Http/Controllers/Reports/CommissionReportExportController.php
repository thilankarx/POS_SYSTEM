<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Reporting\Queries\CommissionReportQuery;
use App\Http\Controllers\Controller;
use App\Support\Csv\CsvSafe;
use App\Support\Money\Money as MoneySupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommissionReportExportController extends Controller
{
    public function __invoke(Request $request, CommissionReportQuery $query): StreamedResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();

        return response()->streamDownload(function () use ($query, $from, $to) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Waiter', 'Sales', 'Total commission', 'Total tips'], escape: '');

            foreach ($query->summary($from, $to) as $waiter) {
                fputcsv($handle, CsvSafe::row([
                    $waiter->name,
                    $waiter->sale_count,
                    (string) MoneySupport::of($waiter->total_commission)->getAmount(),
                    (string) MoneySupport::of($waiter->total_tips)->getAmount(),
                ]), escape: '');
            }

            fclose($handle);
        }, 'commission-report.csv', ['Content-Type' => 'text/csv']);
    }
}
