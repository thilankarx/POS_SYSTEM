<?php

declare(strict_types=1);

namespace App\Support\Printing;

use Symfony\Component\Process\Process;
use Throwable;

class WindowsPrinterDiscovery
{
    /** @return array<string, string> Share name keyed to the Windows queue name. */
    public function sharedQueues(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        try {
            $process = new Process([
                'powershell.exe',
                '-NoProfile',
                '-NonInteractive',
                '-Command',
                'Get-Printer | Where-Object { $_.Shared -and $_.ShareName } | Select-Object Name,ShareName | ConvertTo-Json -Compress',
            ]);
            $process->setTimeout(5)->mustRun();

            $decoded = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            if (isset($decoded['ShareName'])) {
                $decoded = [$decoded];
            }

            $queues = [];
            foreach ($decoded as $printer) {
                $name = trim((string) ($printer['Name'] ?? ''));
                $shareName = trim((string) ($printer['ShareName'] ?? ''));

                if ($name !== '' && $shareName !== '') {
                    $queues[$shareName] = $name;
                }
            }

            asort($queues, SORT_NATURAL | SORT_FLAG_CASE);

            return $queues;
        } catch (Throwable) {
            return [];
        }
    }
}
