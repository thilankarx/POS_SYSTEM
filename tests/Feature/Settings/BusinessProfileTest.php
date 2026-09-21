<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Livewire\Settings\BusinessProfile;
use App\Settings\BusinessProfileSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('denies the business profile page to a user without config.manage', function () {
    Livewire::actingAs($this->cashier)
        ->test(BusinessProfile::class)
        ->assertForbidden();
});

it('loads the current business profile values', function () {
    $settings = app(BusinessProfileSettings::class);
    $settings->store_name = 'Existing Store';
    $settings->address = 'Old Address';
    $settings->phone = '111-1111';
    $settings->save();

    Livewire::actingAs($this->owner)
        ->test(BusinessProfile::class)
        ->assertSet('store_name', 'Existing Store')
        ->assertSet('address', 'Old Address')
        ->assertSet('phone', '111-1111');
});

it('saves new business profile values and persists them', function () {
    Livewire::actingAs($this->owner)
        ->test(BusinessProfile::class)
        ->set('store_name', 'New Hardware Co')
        ->set('address', '456 Oak Ave')
        ->set('phone', '555-9999')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(BusinessProfileSettings::class)->store_name)->toBe('New Hardware Co');

    $storeNameRow = DB::table('settings')
        ->where('group', 'business_profile')
        ->where('name', 'store_name')
        ->firstOrFail();

    expect(json_decode((string) $storeNameRow->payload))->toBe('New Hardware Co');
});

it('requires a store name', function () {
    Livewire::actingAs($this->owner)
        ->test(BusinessProfile::class)
        ->set('store_name', '')
        ->call('save')
        ->assertHasErrors(['store_name' => 'required']);
});

it('saves receipt header and footer text', function () {
    Livewire::actingAs($this->owner)
        ->test(BusinessProfile::class)
        ->set('receipt_header', 'Open 7 days a week')
        ->set('receipt_footer', 'Returns within 30 days')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(BusinessProfileSettings::class)->receipt_header)->toBe('Open 7 days a week')
        ->and(app(BusinessProfileSettings::class)->receipt_footer)->toBe('Returns within 30 days');
});

it('uploads a logo, stores it on the public disk, and persists its path', function () {
    Storage::fake('public');

    Livewire::actingAs($this->owner)
        ->test(BusinessProfile::class)
        ->set('logo', UploadedFile::fake()->image('logo.png'))
        ->call('save')
        ->assertHasNoErrors();

    $logoPath = app(BusinessProfileSettings::class)->logo_path;
    expect($logoPath)->not->toBeNull();
    Storage::disk('public')->assertExists($logoPath);
});

it('rejects a non-image logo upload', function () {
    Storage::fake('public');

    Livewire::actingAs($this->owner)
        ->test(BusinessProfile::class)
        ->set('logo', UploadedFile::fake()->create('logo.pdf', 100))
        ->call('save')
        ->assertHasErrors(['logo' => 'image']);
});

it('defaults business_type to retail', function () {
    Livewire::actingAs($this->owner)
        ->test(BusinessProfile::class)
        ->assertSet('business_type', 'retail');
});

it('saves business_type as hardware', function () {
    Livewire::actingAs($this->owner)
        ->test(BusinessProfile::class)
        ->set('business_type', 'hardware')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(BusinessProfileSettings::class)->business_type)->toBe('hardware');
});

it('rejects an invalid business_type', function () {
    Livewire::actingAs($this->owner)
        ->test(BusinessProfile::class)
        ->set('business_type', 'salon')
        ->call('save')
        ->assertHasErrors(['business_type']);
});

it('accepts restaurant as a business_type', function () {
    Livewire::actingAs($this->owner)
        ->test(BusinessProfile::class)
        ->set('business_type', 'restaurant')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(BusinessProfileSettings::class)->business_type)->toBe('restaurant');
});

it('lets an owner remove an existing logo', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('logo.png')->store('branding', 'public');
    $settings = app(BusinessProfileSettings::class);
    $settings->logo_path = $path;
    $settings->save();

    Livewire::actingAs($this->owner)
        ->test(BusinessProfile::class)
        ->call('removeLogo');

    expect(app(BusinessProfileSettings::class)->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});
