<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Models\Terminal;
use App\Livewire\Sales\Terminals\Form as TerminalForm;
use App\Livewire\Sales\Terminals\Index as TerminalsIndex;
use App\Support\Printing\WindowsPrinterDiscovery;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
});

it('forbids a Cashier from managing terminals', function () {
    $this->actingAs($this->cashier)->get(route('terminals.index'))->assertForbidden();
    $this->actingAs($this->cashier)->get(route('terminals.create'))->assertForbidden();
});

it('lets an admin create a terminal with printer configuration', function () {
    Livewire::actingAs($this->admin)
        ->test(TerminalForm::class)
        ->set('name', 'Back Counter')
        ->set('code', 'T9')
        ->set('stock_location_id', $this->location->id)
        ->set('printer_connector', 'network')
        ->set('receipt_printer', '192.168.1.50:9100')
        ->set('printer_paper_width', '58')
        ->call('save')
        ->assertHasNoErrors();

    $terminal = Terminal::where('code', 'T9')->firstOrFail();
    expect($terminal->printer_connector)->toBe('network')
        ->and($terminal->receipt_printer)->toBe('192.168.1.50:9100')
        ->and($terminal->printer_paper_width)->toBe(58)
        ->and($terminal->hasPrinterConfigured())->toBeTrue();
});

it('offers shared Windows printers for receipt selection', function () {
    $this->app->instance(WindowsPrinterDiscovery::class, new class extends WindowsPrinterDiscovery
    {
        public function sharedQueues(): array
        {
            return ['XP80' => 'Xprinter XP-80'];
        }
    });

    Livewire::actingAs($this->admin)
        ->test(TerminalForm::class)
        ->set('printer_connector', 'windows')
        ->assertSee('Xprinter XP-80 (XP80)');
});

it('lets an admin edit a terminal and clear its printer configuration', function () {
    $terminal = Terminal::create([
        'name' => 'Front Counter',
        'code' => 'T8',
        'stock_location_id' => $this->location->id,
        'printer_connector' => 'network',
        'receipt_printer' => '192.168.1.60:9100',
        'printer_paper_width' => 80,
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(TerminalForm::class, ['terminal' => $terminal])
        ->set('printer_connector', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($terminal->fresh()->hasPrinterConfigured())->toBeFalse();
});

it('lets an admin delete a terminal', function () {
    $terminal = Terminal::create([
        'name' => 'To Delete',
        'code' => 'T7',
        'stock_location_id' => $this->location->id,
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(TerminalsIndex::class)
        ->call('delete', $terminal->id);

    expect(Terminal::find($terminal->id))->toBeNull()
        ->and(Terminal::withTrashed()->find($terminal->id))->not->toBeNull();
});
