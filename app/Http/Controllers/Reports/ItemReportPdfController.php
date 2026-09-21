<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Documents\ReportPdf;
use App\Domain\Reporting\Queries\ItemReportQuery;
use App\Http\Controllers\Controller;
use App\Settings\BusinessProfileSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ItemReportPdfController extends Controller
{
    public function __invoke(Request $request, ItemReportQuery $query, ReportPdf $pdf): Response
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $stockLocationId = $request->integer('stock_location_id') ?: null;
        $categoryId = $request->integer('category_id') ?: null;
        $businessType = $this->businessType($request);

        return $pdf->build('pdf.reports.items', [
            'from' => $from,
            'to' => $to,
            'rows' => $query->forExport($from, $to, $stockLocationId, $categoryId, $businessType),
        ], 'Items report')->stream('items-report.pdf');
    }

    /** 'all' clears the filter; a blank param falls back to the configured store type. */
    private function businessType(Request $request): ?string
    {
        $value = (string) $request->query('business_type', '');

        if ($value === 'all') {
            return null;
        }

        return $value !== '' ? $value : app(BusinessProfileSettings::class)->business_type;
    }
}
