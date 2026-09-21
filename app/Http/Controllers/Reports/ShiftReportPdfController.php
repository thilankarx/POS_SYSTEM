<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Documents\ReportPdf;
use App\Domain\Reporting\Queries\ShiftReportQuery;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShiftReportPdfController extends Controller
{
    private const MAX_ROWS = 2000;

    public function __invoke(Request $request, ShiftReportQuery $query, ReportPdf $pdf): Response
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $terminalId = $request->integer('terminal_id') ?: null;
        $status = in_array($request->query('status'), ['open', 'closed'], true) ? $request->query('status') : null;

        if ($query->countForExport($from, $to, $terminalId, $status) > self::MAX_ROWS) {
            return back()->with('error', 'Narrow the date range for a PDF export (over '.self::MAX_ROWS.' rows) — use CSV for bulk data.');
        }

        return $pdf->build('pdf.reports.shift', [
            'from' => $from,
            'to' => $to,
            'rows' => $query->shiftsForExport($from, $to, $terminalId, $status),
        ], 'Shift report')->stream('shift-report.pdf');
    }
}
