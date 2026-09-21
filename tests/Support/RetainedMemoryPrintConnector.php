<?php

declare(strict_types=1);

namespace Tests\Support;

use Mike42\Escpos\PrintConnectors\MemoryPrintConnector;

/**
 * MemoryPrintConnector nulls its buffer on finalize(), which the Printer
 * calls internally on close(). This keeps a copy of the captured bytes so
 * tests can assert on them after the action under test has finished.
 */
class RetainedMemoryPrintConnector extends MemoryPrintConnector
{
    public string $captured = '';

    public function finalize(): void
    {
        $this->captured = $this->getData();
        parent::finalize();
    }
}
