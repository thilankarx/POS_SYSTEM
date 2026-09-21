<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Documents\ReportPdf;
use App\Domain\Reporting\Queries\SalesReportQuery;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SalesReportPdfController extends Controller
{
    private const MAX_ROWS = 2000;

    public function __invoke(Request $request, SalesReportQuery $query, ReportPdf $pdf): Response
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $stockLocationId = $request->integer('stock_location_id') ?: null;

        if ($query->countForExport($from, $to, $stockLocationId) > self::MAX_ROWS) {
            return back()->with('error', 'Narrow the date range for a PDF export (over '.self::MAX_ROWS.' rows) — use CSV for bulk data.');
        }

        return $pdf->build('pdf.reports.sales', [
            'from' => $from,
            'to' => $to,
            'summary' => $query->summary($from, $to, $stockLocationId),
            'rows' => $query->salesForExport($from, $to, $stockLocationId),
        ], 'Sales report')->stream('sales-report.pdf');
    }
}
