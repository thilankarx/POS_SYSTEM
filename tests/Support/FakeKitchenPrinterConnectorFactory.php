<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Support\KitchenPrinterConnectorFactory;
use App\Domain\Sales\Exceptions\KitchenPrintingException;
use Mike42\Escpos\PrintConnectors\PrintConnector;

/** Test double: swaps the real network/OS connector for an in-memory buffer. */
class FakeKitchenPrinterConnectorFactory extends KitchenPrinterConnectorFactory
{
    public RetainedMemoryPrintConnector $connector;

    public function __construct()
    {
        $this->connector = new RetainedMemoryPrintConnector;
    }

    public function resolve(StockLocation $location): PrintConnector
    {
        if (! $location->hasKitchenPrinterConfigured()) {
            throw KitchenPrintingException::printerNotConfigured();
        }

        return $this->connector;
    }
}
