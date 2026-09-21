<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Livewire\Catalog\Items\Import;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->stockClerk = User::factory()->create();
    $this->stockClerk->assignRole('Stock Clerk');
});

function csvFile(string $content): TestingFile
{
    return UploadedFile::fake()->createWithContent('items.csv', $content);
}

it('creates new items and updates an existing one by SKU', function () {
    $existing = Item::where('sku', 'BEV-COLA-330')->firstOrFail();

    $csv = "sku,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        ."HW-NEW-1,New Hammer,,,,,9.99,stocked,3,12,1\n"
        ."BEV-COLA-330,Cola 330ml Updated,,,,,1.30,,,,\n";

    Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    // Both rows are stocked -- the CSV's unit_price column is ignored for
    // them (a stocked item's price only ever comes from a stock lot).
    $created = Item::where('sku', 'HW-NEW-1')->firstOrFail();
    expect($created->name)->toBe('New Hammer')
        ->and($created->unit_price)->toBeNull();

    expect($existing->fresh()->name)->toBe('Cola 330ml Updated')
        ->and($existing->fresh()->unit_price)->toBeNull();
});

it('re-importing the same file is idempotent', function () {
    $csv = "sku,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        ."HW-IDEMP-1,Idempotent Widget,,,,,9.99,stocked,3,12,1\n";

    Livewire::actingAs($this->owner)->test(Import::class)->set('csv', csvFile($csv))->call('import');
    Livewire::actingAs($this->owner)->test(Import::class)->set('csv', csvFile($csv))->call('import');

    expect(Item::where('sku', 'HW-IDEMP-1')->count())->toBe(1);
});

it('re-importing the same blank-sku file matches by name instead of creating a duplicate', function () {
    // Regression: a row with no sku had nothing to match an existing item
    // by, so it always created a fresh item with a new auto-generated sku
    // -- re-importing a supplier price list with no internal sku column (the
    // common case) duplicated every single item on every re-import.
    $csv = "sku,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        .",Hex Bolt 10mm,,,,,,stocked,,,\n";

    Livewire::actingAs($this->owner)->test(Import::class)->set('csv', csvFile($csv))->call('import');
    Livewire::actingAs($this->owner)->test(Import::class)->set('csv', csvFile($csv))->call('import');

    expect(Item::where('name', 'Hex Bolt 10mm')->count())->toBe(1);
});

it('matches a blank-sku row to an existing item by name case-insensitively, updating it instead of duplicating', function () {
    $existing = Item::create([
        'name' => 'Hex Bolt 10mm',
        'sku' => 'HW-EXISTING-1',
        'stock_type' => Item::STOCK_TYPE_STOCKED,
    ]);

    $csv = "sku,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        .",hex bolt 10mm,Updated description,,,,,,,,\n";

    Livewire::actingAs($this->owner)->test(Import::class)->set('csv', csvFile($csv))->call('import');

    expect(Item::where('name', 'hex bolt 10mm')->count())->toBe(1)
        ->and($existing->fresh()->sku)->toBe('HW-EXISTING-1')
        ->and($existing->fresh()->description)->toBe('Updated description');
});

it('skips a row with a non-numeric price and still imports the valid rows', function () {
    // unit_price is only ever validated for a non-stocked row (a stocked
    // item's price never lives on the item itself).
    $csv = "sku,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        ."HW-BAD-1,Bad Widget,,,,,expensive,service,,,\n"
        ."HW-GOOD-1,Good Widget,,,,,9.99,service,,,\n";

    $component = Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    expect(Item::where('sku', 'HW-BAD-1')->exists())->toBeFalse()
        ->and(Item::where('sku', 'HW-GOOD-1')->exists())->toBeTrue()
        ->and($component->get('results'))->toHaveCount(1)
        ->and($component->get('results')[0]['level'])->toBe('error');
});

it('blocks the whole import when a row references an unknown category, and imports nothing', function () {
    $csv = "sku,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        ."HW-CAT-1,Category Widget,,Nonexistent Category,,,9.99,stocked,,,\n"
        ."HW-CAT-2,Another Widget,,,,,9.99,stocked,,,\n";

    $component = Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    expect(Item::where('sku', 'HW-CAT-1')->exists())->toBeFalse()
        ->and(Item::where('sku', 'HW-CAT-2')->exists())->toBeFalse()
        ->and($component->get('missingReferences'))->toBe(['category' => ['Nonexistent Category']]);
});

it('blocks the whole import when a row references an unknown supplier or tax category', function () {
    $csv = "sku,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        ."HW-SUP-1,Supplier Widget,,,Nonexistent Supplier,Nonexistent Tax,9.99,stocked,,,\n";

    $component = Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    expect(Item::where('sku', 'HW-SUP-1')->exists())->toBeFalse()
        ->and($component->get('missingReferences'))->toBe([
            'supplier' => ['Nonexistent Supplier'],
            'tax_category' => ['Nonexistent Tax'],
        ]);
});

it('resolves a category by name when it exists', function () {
    $category = Category::create(['name' => 'Hand Tools', 'slug' => 'hand-tools']);

    $csv = "sku,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        ."HW-CAT-2,Category Match Widget,,Hand Tools,,,9.99,stocked,,,\n";

    Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    expect(Item::where('sku', 'HW-CAT-2')->firstOrFail()->category_id)->toBe($category->id);
});

it('auto-generates a sku for a row with a blank sku column, prefixed by its category', function () {
    $category = Category::create(['name' => 'Fasteners', 'slug' => 'fasteners', 'code' => 'FAST']);

    $csv = "sku,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        .",Screws Box,,Fasteners,,,9.99,stocked,,,\n";

    Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    $item = Item::where('name', 'Screws Box')->firstOrFail();
    expect($item->sku)->toMatch('/^FAST-\d{6}$/');
});

it('auto-generates a "GEN-" sku for a row with a blank sku and no category', function () {
    $csv = "sku,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        .",No Category Widget,,,,,9.99,stocked,,,\n";

    Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    $item = Item::where('name', 'No Category Widget')->firstOrFail();
    expect($item->sku)->toMatch('/^GEN-\d{6}$/');
});

it('assigns a primary barcode from the sku when a new item has no barcode column value', function () {
    $csv = "sku,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        ."HW-BC-1,Barcode Widget,,,,,9.99,stocked,,,\n";

    Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    $item = Item::where('sku', 'HW-BC-1')->firstOrFail();
    $barcode = $item->barcodes()->where('is_primary', true)->first();

    expect($barcode)->not->toBeNull()
        ->and($barcode->barcode)->toBe('HW-BC-1');
});

it('uses a supplied barcode column for a new item instead of the sku', function () {
    $csv = "sku,barcode,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        ."HW-BC-2,0123456789012,Barcode Widget Two,,,,,9.99,stocked,,,\n";

    Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    $item = Item::where('sku', 'HW-BC-2')->firstOrFail();
    $barcode = $item->barcodes()->where('is_primary', true)->first();

    expect($barcode->barcode)->toBe('0123456789012');
});

it('rejects a row whose barcode is already used by another item', function () {
    $existing = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $existing->barcodes()->create(['barcode' => '9999999999999', 'type' => 'code128', 'is_primary' => true]);

    $csv = "sku,barcode,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        ."HW-BC-3,9999999999999,Duplicate Barcode Widget,,,,,9.99,stocked,,,\n";

    $component = Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    expect(Item::where('sku', 'HW-BC-3')->exists())->toBeFalse()
        ->and($component->get('results')[0]['level'])->toBe('error');
});

it('fills in a missing primary barcode on a re-imported existing item when the CSV supplies one', function () {
    $existing = Item::where('sku', 'SRV-DELIVERY')->firstOrFail();
    expect($existing->barcodes()->where('is_primary', true)->exists())->toBeFalse();

    $csv = "sku,barcode,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        ."SRV-DELIVERY,1111111111111,Local Delivery,,,,,5.00,,,,\n";

    Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    $barcode = $existing->barcodes()->where('is_primary', true)->first();
    expect($barcode)->not->toBeNull()
        ->and($barcode->barcode)->toBe('1111111111111');
});

it('does not overwrite an existing primary barcode when a re-imported row supplies a different value', function () {
    $existing = Item::where('sku', 'SRV-DELIVERY')->firstOrFail();
    $existing->barcodes()->create(['barcode' => '2222222222222', 'type' => 'code128', 'is_primary' => true]);

    $csv = "sku,barcode,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        ."SRV-DELIVERY,3333333333333,Local Delivery,,,,,5.00,,,,\n";

    $component = Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    $barcode = $existing->barcodes()->where('is_primary', true)->first();
    expect($barcode->barcode)->toBe('2222222222222')
        ->and($component->get('results')[0]['level'])->toBe('warning')
        ->and($component->get('results')[0]['message'])->toContain('left unchanged');
});

it('leaves an existing primary barcode alone when the CSV value matches it', function () {
    $existing = Item::where('sku', 'SRV-DELIVERY')->firstOrFail();
    $existing->barcodes()->create(['barcode' => '4444444444444', 'type' => 'code128', 'is_primary' => true]);

    $csv = "sku,barcode,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        ."SRV-DELIVERY,4444444444444,Local Delivery,,,,,5.00,,,,\n";

    $component = Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    expect($existing->barcodes()->where('is_primary', true)->count())->toBe(1)
        ->and($component->get('results'))->toHaveCount(0);
});

it('reports a warning and does not assign a barcode already used by another item on update', function () {
    // BEV-COLA-330 already carries the seeded barcode 5012345678900 (see DemoDataSeeder).
    $existing = Item::where('sku', 'SRV-DELIVERY')->firstOrFail();

    $csv = "sku,barcode,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        ."SRV-DELIVERY,5012345678900,Local Delivery,,,,,5.00,,,,\n";

    $component = Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    expect($existing->barcodes()->where('is_primary', true)->exists())->toBeFalse()
        ->and(Item::where('sku', 'SRV-DELIVERY')->exists())->toBeTrue()
        ->and($component->get('results')[0]['level'])->toBe('warning')
        ->and($component->get('results')[0]['message'])->toContain('already in use by another item');
});

it('allows a Manager but denies a Stock Clerk and a Cashier from importing', function () {
    $csv = "sku,name,description,category,supplier,tax_category,unit_price,stock_type,reorder_level,reorder_quantity,is_active\n"
        ."HW-PERM-1,Perm Widget,,,,,9.99,stocked,,,\n";

    $this->actingAs($this->stockClerk)->get(route('items.import'))->assertForbidden();
    $this->actingAs($this->cashier)->get(route('items.import'))->assertForbidden();

    Livewire::actingAs($this->owner)
        ->test(Import::class)
        ->set('csv', csvFile($csv))
        ->call('import');

    expect(Item::where('sku', 'HW-PERM-1')->exists())->toBeTrue();
});
