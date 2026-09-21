<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemImportTemplateController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'sku', 'barcode', 'name', 'description', 'category', 'supplier', 'tax_category',
                'unit_price', 'stock_type', 'reorder_level', 'reorder_quantity', 'is_active',
            ], escape: '');
            fputcsv($handle, [
                'HW-EXAMPLE-1', '', 'Example Hammer', '16oz claw hammer', 'Hand Tools', 'Acme Supply Co', 'Standard',
                '', 'stocked', '3', '12', '1',
            ], escape: '');
            fclose($handle);
        }, 'items-import-template.csv', ['Content-Type' => 'text/csv']);
    }
}
