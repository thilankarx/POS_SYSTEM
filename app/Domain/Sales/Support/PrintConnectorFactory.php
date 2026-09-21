<?php

declare(strict_types=1);

namespace App\Domain\Sales\Support;

use App\Domain\Sales\Exceptions\PrintingException;
use App\Domain\Sales\Models\Terminal;
use Exception;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

class PrintConnectorFactory
{
    public function resolve(Terminal $terminal): PrintConnector
    {
        if (! $terminal->hasPrinterConfigured()) {
            throw PrintingException::printerNotConfigured();
        }

        $target = $terminal->receipt_printer;

        try {
            return match ($terminal->printer_connector) {
                Terminal::CONNECTOR_NETWORK => $this->networkConnector($target),
                Terminal::CONNECTOR_WINDOWS => new WindowsPrintConnector($target),
                Terminal::CONNECTOR_CUPS => new CupsPrintConnector($target),
                default => throw PrintingException::printerNotConfigured(),
            };
        } catch (Exception $e) {
            if ($e instanceof PrintingException) {
                throw $e;
            }

            throw PrintingException::connectionFailed($e->getMessage());
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
