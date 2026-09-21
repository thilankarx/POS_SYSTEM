<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Reporting\Queries\ItemReportQuery;
use App\Http\Controllers\Controller;
use App\Settings\BusinessProfileSettings;
use App\Support\Csv\CsvSafe;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemReportExportController extends Controller
{
    public function __invoke(Request $request, ItemReportQuery $query): StreamedResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $stockLocationId = $request->integer('stock_location_id') ?: null;
        $categoryId = $request->integer('category_id') ?: null;
        $businessType = $this->businessType($request);

        return response()->streamDownload(function () use ($query, $from, $to, $stockLocationId, $categoryId, $businessType) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Item', 'Category', 'Qty sold', 'Revenue', 'Cost', 'Margin'], escape: '');

            foreach ($query->forExport($from, $to, $stockLocationId, $categoryId, $businessType) as $row) {
                fputcsv($handle, CsvSafe::row([
                    $row->sku,
                    $row->item_name,
                    $row->category_name,
                    (string) $row->quantity,
                    (string) $row->line_total->getAmount(),
                    (string) $row->cost_price->getAmount(),
                    (string) $row->line_total->minus($row->cost_price)->getAmount(),
                ]), escape: '');
            }

            fclose($handle);
        }, 'items-report.csv', ['Content-Type' => 'text/csv']);
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
