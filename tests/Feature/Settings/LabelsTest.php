<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Support\TsplLabelBuilder;
use App\Livewire\Settings\Labels;
use App\Settings\BusinessProfileSettings;
use App\Settings\LabelSettings;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('denies the label settings page to a user without config.manage', function () {
    Livewire::actingAs($this->cashier)
        ->test(Labels::class)
        ->assertForbidden();
});

it('loads the current label dimensions', function () {
    Livewire::actingAs($this->owner)
        ->test(Labels::class)
        ->assertSet('width_mm', 50)
        ->assertSet('height_mm', 30)
        ->assertSet('gap_mm', 2)
        ->assertSet('density', 8);
});

it('saves new dimensions and TsplLabelBuilder output reflects them', function () {
    config()->set('pos.currency', 'LKR');
    $business = app(BusinessProfileSettings::class);
    $business->store_name = 'Test Shop';
    $business->save();

    Livewire::actingAs($this->owner)
        ->test(Labels::class)
        ->set('width_mm', 40)
        ->set('height_mm', 20)
        ->set('gap_mm', 3)
        ->set('density', 10)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(LabelSettings::class)->width_mm)->toBe(40);

    $job = app(TsplLabelBuilder::class)->build('SKU1', '9.99', '123456', 'Test Widget');

    expect($job)->toContain('SIZE 40 mm,20 mm')
        ->and($job)->toContain('GAP 3 mm,0 mm')
        ->and($job)->toContain('DENSITY 10')
        ->and($job)->toContain('REFERENCE 0,0')
        ->and($job)->toContain('TEXT 108,16,"1",0,1,2,"Test Shop"')
        ->and($job)->toContain('TEXT 82,50,"2",0,1,1,"Test Widget"')
        ->and($job)->toContain('BARCODE 92,78,"128",26,2,0,2,2,"123456"')
        ->and($job)->toContain('TEXT 96,126,"3",0,1,1,"LKR 9.99"')
        ->and($job)->not->toContain('"SKU1"');
});

it('narrows and centers a long alphanumeric Code 128 barcode to fit the label', function () {
    $settings = app(LabelSettings::class);
    $settings->width_mm = 38;
    $settings->save();

    $job = app(TsplLabelBuilder::class)->build('BEV-COLA-330', '1.20', 'BEV-COLA-330');

    expect($job)->toContain('BARCODE 69,86,"128",98,2,0,1,1,"BEV-COLA-330"');
});

it('centers an odd-length numeric barcode using the printer Code Set C width', function () {
    config()->set('pos.currency', 'LKR');

    $settings = app(LabelSettings::class);
    $settings->width_mm = 38;
    $settings->save();

    $job = app(TsplLabelBuilder::class)->build('BAK-CROIS', '1.75', '5012345678931');

    expect($job)->toContain('TEXT 88,206,"3",0,1,1,"LKR 1.75"')
        ->and($job)->toContain('BARCODE 91,86,"128",98,2,0,1,1,"5012345678931"');
});

it('keeps long titles inside a centered printable block', function () {
    $settings = app(LabelSettings::class);
    $settings->width_mm = 35;
    $settings->height_mm = 25;
    $settings->save();

    $business = app(BusinessProfileSettings::class);
    $business->store_name = 'A Store Name That Is Much Too Long For The Label';
    $business->save();

    $job = app(TsplLabelBuilder::class)->build('SKU', '9.99', '123456', 'An Extremely Long Product Name That Must Not Clip');

    expect($job)->toContain('TEXT 8,20,"1",0,1,2,"A Store Name That Is Much Too..."')
        ->and($job)->toContain('TEXT 26,48,"2",0,1,1,"An Extremely Long"')
        ->and($job)->toContain('TEXT 8,70,"2",0,1,1,"Product Name That M..."')
        ->and($job)->toContain('BARCODE 72,98,"128",46,2,0,2,2,"123456"')
        ->and($job)->toContain('TEXT 76,166,"3",0,1,1,"LKR 9.99"')
        ->and($job)->not->toContain('"SKU"');
});

it('requires a positive width', function () {
    Livewire::actingAs($this->owner)
        ->test(Labels::class)
        ->set('width_mm', 0)
        ->call('save')
        ->assertHasErrors(['width_mm' => 'min']);
});
