<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\Terminal;
use App\Domain\Sales\Support\PrintConnectorFactory;
use Mike42\Escpos\Printer;

final class OpenCashDrawerAction
{
    public function __construct(private readonly PrintConnectorFactory $connectors) {}

    public function execute(Terminal $terminal): void
    {
        $printer = new Printer($this->connectors->resolve($terminal));

        try {
            $printer->pulse();
        } finally {
            $printer->close();
        }
    }
}
