<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Documents\ReportPdf;
use App\Domain\Reporting\Queries\CustomerReportQuery;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerReportPdfController extends Controller
{
    public function __invoke(Request $request, CustomerReportQuery $query, ReportPdf $pdf): Response
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $stockLocationId = $request->integer('stock_location_id') ?: null;

        return $pdf->build('pdf.reports.customers', [
            'from' => $from,
            'to' => $to,
            'rows' => $query->forExport($from, $to, $stockLocationId),
        ], 'Customers report')->stream('customers-report.pdf');
    }
}
