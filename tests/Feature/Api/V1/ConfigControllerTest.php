<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Settings\BusinessProfileSettings;
use Laravel\Sanctum\Sanctum;

it('returns the configured business type to any authenticated user', function () {
    $this->seed();
    $settings = app(BusinessProfileSettings::class);
    $settings->business_type = 'hardware';
    $settings->save();

    Sanctum::actingAs(User::where('username', 'cashier')->firstOrFail(), ['*']);
    $this->getJson('/api/v1/config')
        ->assertOk()
        ->assertJson(['business_type' => 'hardware']);
});

it('defaults to retail when unconfigured', function () {
    $this->seed();

    Sanctum::actingAs(User::where('username', 'cashier')->firstOrFail(), ['*']);
    $this->getJson('/api/v1/config')
        ->assertOk()
        ->assertJson(['business_type' => 'retail']);
});

it('requires authentication', function () {
    $this->seed();

    $this->getJson('/api/v1/config')->assertUnauthorized();
});
