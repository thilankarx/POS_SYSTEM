<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Sales\Exceptions\PrintingException;
use App\Domain\Sales\Models\Terminal;
use App\Domain\Sales\Support\PrintConnectorFactory;
use Mike42\Escpos\PrintConnectors\PrintConnector;

/** Test double: swaps the real network/OS connector for an in-memory buffer. */
class FakePrintConnectorFactory extends PrintConnectorFactory
{
    public RetainedMemoryPrintConnector $connector;

    public function __construct()
    {
        $this->connector = new RetainedMemoryPrintConnector;
    }

    public function resolve(Terminal $terminal): PrintConnector
    {
        if (! $terminal->hasPrinterConfigured()) {
            throw PrintingException::printerNotConfigured();
        }

        return $this->connector;
    }
}
