<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

it('lists categories, ordered by name', function () {
    $this->seed();

    Sanctum::actingAs(User::where('username', 'cashier')->firstOrFail(), ['*']);
    $response = $this->getJson('/api/v1/categories')->assertOk();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Bakery')
        ->and($names->all())->toBe($names->sort()->values()->all());
});

it('rejects unauthenticated requests', function () {
    $this->seed();

    $this->getJson('/api/v1/categories')->assertUnauthorized();
});

it('rejects a user without items.view', function () {
    $this->seed();
    $user = User::factory()->create();
    $user->assignRole('Accountant');

    Sanctum::actingAs($user, ['*']);
    $this->getJson('/api/v1/categories')->assertForbidden();
});
