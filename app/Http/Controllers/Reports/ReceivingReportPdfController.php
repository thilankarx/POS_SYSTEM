<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Documents\ReportPdf;
use App\Domain\Reporting\Queries\ReceivingReportQuery;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReceivingReportPdfController extends Controller
{
    private const MAX_ROWS = 2000;

    public function __invoke(Request $request, ReceivingReportQuery $query, ReportPdf $pdf): Response
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $supplierId = $request->integer('supplier_id') ?: null;
        $stockLocationId = $request->integer('stock_location_id') ?: null;
        $type = $request->query('type') ?: null;

        if ($query->countForExport($from, $to, $supplierId, $stockLocationId, $type) > self::MAX_ROWS) {
            return back()->with('error', 'Narrow the date range for a PDF export (over '.self::MAX_ROWS.' rows) — use CSV for bulk data.');
        }

        return $pdf->build('pdf.reports.receivings', [
            'from' => $from,
            'to' => $to,
            'rows' => $query->receivingsForExport($from, $to, $supplierId, $stockLocationId, $type),
        ], 'Receivings report')->stream('receivings-report.pdf');
    }
}
