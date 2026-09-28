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

it('stores a Windows receipt printer on another register PC as an SMB target', function () {
    Livewire::actingAs($this->admin)
        ->test(TerminalForm::class)
        ->set('name', 'Second Counter')
        ->set('code', 'T6')
        ->set('stock_location_id', $this->location->id)
        ->set('printer_connector', 'windows')
        ->set('printer_host', 'REGISTER-2')
        ->set('receipt_printer', 'XP80')
        ->call('save')
        ->assertHasNoErrors();

    expect(Terminal::where('code', 'T6')->value('receipt_printer'))->toBe('smb://REGISTER-2/XP80');
});

it('accepts a full UNC path typed into the share field', function () {
    Livewire::actingAs($this->admin)
        ->test(TerminalForm::class)
        ->set('name', 'Third Counter')
        ->set('code', 'T5')
        ->set('stock_location_id', $this->location->id)
        ->set('printer_connector', 'windows')
        ->set('receipt_printer', '\\\\REGISTER-3\\XP80')
        ->call('save')
        ->assertHasNoErrors();

    expect(Terminal::where('code', 'T5')->value('receipt_printer'))->toBe('smb://REGISTER-3/XP80');
});

it('keeps a server-local Windows queue as a bare share name', function () {
    Livewire::actingAs($this->admin)
        ->test(TerminalForm::class)
        ->set('name', 'Server Counter')
        ->set('code', 'T4')
        ->set('stock_location_id', $this->location->id)
        ->set('printer_connector', 'windows')
        ->set('receipt_printer', 'XP80')
        ->call('save')
        ->assertHasNoErrors();

    expect(Terminal::where('code', 'T4')->value('receipt_printer'))->toBe('XP80');
});

it('splits a stored SMB target back into host and share when editing', function () {
    $terminal = Terminal::create([
        'name' => 'Second Counter',
        'code' => 'T3X',
        'stock_location_id' => $this->location->id,
        'printer_connector' => 'windows',
        'receipt_printer' => 'smb://REGISTER-2/XP80',
        'printer_paper_width' => 80,
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(TerminalForm::class, ['terminal' => $terminal])
        ->assertSet('printer_host', 'REGISTER-2')
        ->assertSet('receipt_printer', 'XP80')
        ->call('save')
        ->assertHasNoErrors();

    expect($terminal->fresh()->receipt_printer)->toBe('smb://REGISTER-2/XP80');
});

it('rejects Windows printer targets with embedded credentials', function () {
    Livewire::actingAs($this->admin)
        ->test(TerminalForm::class)
        ->set('name', 'Bad Counter')
        ->set('code', 'T2X')
        ->set('stock_location_id', $this->location->id)
        ->set('printer_connector', 'windows')
        ->set('receipt_printer', 'smb://admin:secret@REGISTER-2/XP80')
        ->call('save')
        ->assertHasErrors(['receipt_printer']);
});
