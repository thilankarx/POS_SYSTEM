<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Documents\ReportPdf;
use App\Domain\Reporting\Queries\InventoryReportQuery;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InventoryReportPdfController extends Controller
{
    private const MAX_ROWS = 2000;

    public function __invoke(Request $request, InventoryReportQuery $query, ReportPdf $pdf): Response
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $itemId = $request->integer('item_id') ?: null;
        $stockLocationId = $request->integer('stock_location_id') ?: null;
        $reason = $request->query('reason') ?: null;

        if ($query->countForExport($from, $to, $itemId, $stockLocationId, $reason) > self::MAX_ROWS) {
            return back()->with('error', 'Narrow the date range for a PDF export (over '.self::MAX_ROWS.' rows) — use CSV for bulk data.');
        }

        return $pdf->build('pdf.reports.inventory', [
            'from' => $from,
            'to' => $to,
            'rows' => $query->movementsForExport($from, $to, $itemId, $stockLocationId, $reason),
        ], 'Inventory report')->stream('inventory-report.pdf');
    }
}
