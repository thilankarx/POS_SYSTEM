<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Actions\OpenCashDrawerAction;
use App\Domain\Sales\Exceptions\PrintingException;
use App\Domain\Sales\Models\Terminal;
use App\Domain\Sales\Support\PrintConnectorFactory;
use Mike42\Escpos\Printer;
use Tests\Support\FakePrintConnectorFactory;

beforeEach(function () {
    $this->seed();
    $this->user = User::where('username', 'cashier')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
});

it('sends a pulse to a configured terminal printer', function () {
    $this->terminal->update(['printer_connector' => Terminal::CONNECTOR_NETWORK, 'receipt_printer' => '127.0.0.1:9100']);

    $fake = new FakePrintConnectorFactory;
    $this->app->instance(PrintConnectorFactory::class, $fake);

    app(OpenCashDrawerAction::class)->execute($this->terminal->fresh());

    expect($fake->connector->captured)->toContain(Printer::ESC.'p');
});

it('refuses to open the drawer when the terminal has no printer configured', function () {
    expect(fn () => app(OpenCashDrawerAction::class)->execute($this->terminal))
        ->toThrow(PrintingException::class, 'no receipt printer configured');
});
