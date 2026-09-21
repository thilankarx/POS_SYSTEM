<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Inventory\Exceptions\LabelPrintingException;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Support\LabelPrinterConnectorFactory;
use Mike42\Escpos\PrintConnectors\PrintConnector;

/** Test double: swaps the real network/OS connector for an in-memory buffer. */
class FakeLabelPrinterConnectorFactory extends LabelPrinterConnectorFactory
{
    public RetainedMemoryPrintConnector $connector;

    public function __construct()
    {
        $this->connector = new RetainedMemoryPrintConnector;
    }

    public function resolve(StockLocation $location): PrintConnector
    {
        if (! $location->hasLabelPrinterConfigured()) {
            throw LabelPrintingException::printerNotConfigured();
        }

        return $this->connector;
    }
}
