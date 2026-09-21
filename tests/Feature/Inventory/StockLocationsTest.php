<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockMovement;
use App\Livewire\Inventory\StockLocations\Form;
use App\Support\Printing\WindowsPrinterDiscovery;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('lists and creates stock locations', function () {
    $this->actingAs($this->owner)
        ->get(route('stock-locations.index'))
        ->assertOk();

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Outlet Store')
        ->set('code', 'outlet')
        ->call('save')
        ->assertRedirect(route('stock-locations.index'));

    expect(StockLocation::where('code', 'OUTLET')->where('name', 'Outlet Store')->exists())->toBeTrue();
});

it('rejects a duplicate code', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Duplicate')
        ->set('code', 'MAIN')
        ->call('save')
        ->assertHasErrors('code');
});

it('clears the previous default when a new default is set', function () {
    $main = StockLocation::where('code', 'MAIN')->firstOrFail();
    expect($main->is_default)->toBeTrue();

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'New Default')
        ->set('code', 'NEWDEF')
        ->set('is_default', true)
        ->call('save')
        ->assertRedirect(route('stock-locations.index'));

    expect($main->fresh()->is_default)->toBeFalse()
        ->and(StockLocation::where('code', 'NEWDEF')->firstOrFail()->is_default)->toBeTrue();
});

it('renders the read-only stock levels view without writing any stock movements', function () {
    $main = StockLocation::where('code', 'MAIN')->firstOrFail();
    $before = StockMovement::count();

    $this->actingAs($this->owner)
        ->get(route('stock-locations.stock', $main))
        ->assertOk();

    expect(StockMovement::count())->toBe($before);
});

it('saves a label printer configuration through the Livewire form', function () {
    $main = StockLocation::where('code', 'MAIN')->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['stockLocation' => $main])
        ->set('label_printer_connector', 'network')
        ->set('label_printer', '192.168.1.60:9100')
        ->call('save')
        ->assertHasNoErrors();

    expect($main->fresh())
        ->label_printer_connector->toBe('network')
        ->label_printer->toBe('192.168.1.60:9100');
});

it('offers shared Windows printers for label selection', function () {
    $this->app->instance(WindowsPrinterDiscovery::class, new class extends WindowsPrinterDiscovery
    {
        public function sharedQueues(): array
        {
            return ['XP365B' => 'Xprinter XP-365B'];
        }
    });

    $main = StockLocation::where('code', 'MAIN')->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['stockLocation' => $main])
        ->set('label_printer_connector', 'windows')
        ->assertSee('Xprinter XP-365B (XP365B)');
});

it('requires a target when a label printer connector is chosen', function () {
    $main = StockLocation::where('code', 'MAIN')->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['stockLocation' => $main])
        ->set('label_printer_connector', 'network')
        ->set('label_printer', '')
        ->call('save')
        ->assertHasErrors('label_printer');
});

it('saves a kitchen printer configuration through the Livewire form', function () {
    $main = StockLocation::where('code', 'MAIN')->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['stockLocation' => $main])
        ->set('kitchen_printer_connector', 'network')
        ->set('kitchen_printer', '192.168.1.70:9100')
        ->call('save')
        ->assertHasNoErrors();

    expect($main->fresh())
        ->kitchen_printer_connector->toBe('network')
        ->kitchen_printer->toBe('192.168.1.70:9100');
});

it('requires a target when a kitchen printer connector is chosen', function () {
    $main = StockLocation::where('code', 'MAIN')->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['stockLocation' => $main])
        ->set('kitchen_printer_connector', 'network')
        ->set('kitchen_printer', '')
        ->call('save')
        ->assertHasErrors('kitchen_printer');
});

it('blocks users without locations.manage from creating stock locations', function () {
    $this->actingAs($this->cashier)
        ->get(route('stock-locations.create'))
        ->assertForbidden();
});

it('blocks users without locations.view from the stock locations section entirely', function () {
    $this->actingAs($this->cashier)
        ->get(route('stock-locations.index'))
        ->assertForbidden();
});
