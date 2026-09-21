<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Support;

use App\Domain\Inventory\Exceptions\LabelPrintingException;
use App\Domain\Inventory\Models\StockLocation;
use Exception;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

class LabelPrinterConnectorFactory
{
    public function resolve(StockLocation $location): PrintConnector
    {
        if (! $location->hasLabelPrinterConfigured()) {
            throw LabelPrintingException::printerNotConfigured();
        }

        $target = $location->label_printer;

        try {
            return match ($location->label_printer_connector) {
                StockLocation::CONNECTOR_NETWORK => $this->networkConnector($target),
                StockLocation::CONNECTOR_WINDOWS => new WindowsPrintConnector($target),
                StockLocation::CONNECTOR_CUPS => new CupsPrintConnector($target),
                default => throw LabelPrintingException::printerNotConfigured(),
            };
        } catch (Exception $e) {
            if ($e instanceof LabelPrintingException) {
                throw $e;
            }

            throw LabelPrintingException::connectionFailed($e->getMessage());
        }
    }

    private function networkConnector(string $target): NetworkPrintConnector
    {
        [$ip, $port] = str_contains($target, ':')
            ? explode(':', $target, 2)
            : [$target, '9100'];

        return new NetworkPrintConnector($ip, (int) $port, 5);
    }
}
