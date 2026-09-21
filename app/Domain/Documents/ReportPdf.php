<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

final class ReportPdf
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function build(string $view, array $data, string $title): DomPdf
    {
        return Pdf::loadView($view, [...$data, 'reportTitle' => $title])
            ->setPaper('a4', 'landscape');
    }
}
