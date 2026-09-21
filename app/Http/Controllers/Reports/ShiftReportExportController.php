<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Reporting\Queries\ShiftReportQuery;
use App\Http\Controllers\Controller;
use App\Support\Csv\CsvSafe;
use App\Support\Money\Money as MoneySupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShiftReportExportController extends Controller
{
    public function __invoke(Request $request, ShiftReportQuery $query): StreamedResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $terminalId = $request->integer('terminal_id') ?: null;
        $status = in_array($request->query('status'), ['open', 'closed'], true) ? $request->query('status') : null;

        return response()->streamDownload(function () use ($query, $from, $to, $terminalId, $status) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Terminal', 'Opened by', 'Opened at', 'Closed at', 'Status', 'Expected cash', 'Counted cash', 'Variance', 'Sales total'], escape: '');

            foreach ($query->shiftsForExport($from, $to, $terminalId, $status) as $shift) {
                fputcsv($handle, CsvSafe::row([
                    $shift->terminal?->name,
                    $shift->openedBy?->name,
                    $shift->opened_at->toDateTimeString(),
                    $shift->closed_at?->toDateTimeString(),
                    $shift->status,
                    $shift->expected_cash ? (string) $shift->expected_cash->getAmount() : null,
                    $shift->counted_cash ? (string) $shift->counted_cash->getAmount() : null,
                    $shift->cash_variance ? (string) $shift->cash_variance->getAmount() : null,
                    (string) MoneySupport::of($shift->sales_total)->getAmount(),
                ]), escape: '');
            }

            fclose($handle);
        }, 'shift-report.csv', ['Content-Type' => 'text/csv']);
    }
}
