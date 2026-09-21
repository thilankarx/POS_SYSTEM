<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Reporting\Queries\PaymentReportQuery;
use App\Http\Controllers\Controller;
use App\Support\Csv\CsvSafe;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReportExportController extends Controller
{
    public function __invoke(Request $request, PaymentReportQuery $query): StreamedResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $paymentMethodId = $request->integer('payment_method_id') ?: null;
        $stockLocationId = $request->integer('stock_location_id') ?: null;

        return response()->streamDownload(function () use ($query, $from, $to, $paymentMethodId, $stockLocationId) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Sale #', 'Method', 'Amount', 'Status'], escape: '');

            foreach ($query->paymentsForExport($from, $to, $paymentMethodId, $stockLocationId) as $payment) {
                fputcsv($handle, CsvSafe::row([
                    $payment->created_at->toDateTimeString(),
                    $payment->sale?->number,
                    $payment->method?->name,
                    (string) $payment->amount->getAmount(),
                    $payment->status,
                ]), escape: '');
            }

            fclose($handle);
        }, 'payments-report.csv', ['Content-Type' => 'text/csv']);
    }
}
