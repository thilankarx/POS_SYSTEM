<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Support;

use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Exceptions\KitchenPrintingException;
use App\Support\Printing\WindowsPrinterTarget;
use Exception;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

class KitchenPrinterConnectorFactory
{
    public function resolve(StockLocation $location): PrintConnector
    {
        if (! $location->hasKitchenPrinterConfigured()) {
            throw KitchenPrintingException::printerNotConfigured();
        }

        $target = $location->kitchen_printer;

        try {
            return match ($location->kitchen_printer_connector) {
                StockLocation::CONNECTOR_NETWORK => $this->networkConnector($target),
                StockLocation::CONNECTOR_WINDOWS => new WindowsPrintConnector(WindowsPrinterTarget::normalize($target)),
                StockLocation::CONNECTOR_CUPS => new CupsPrintConnector($target),
                default => throw KitchenPrintingException::printerNotConfigured(),
            };
        } catch (Exception $e) {
            if ($e instanceof KitchenPrintingException) {
                throw $e;
            }

            throw KitchenPrintingException::connectionFailed($e->getMessage());
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
