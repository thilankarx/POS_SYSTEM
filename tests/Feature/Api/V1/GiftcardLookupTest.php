<?php

declare(strict_types=1);

use App\Domain\Giftcards\Actions\IssueGiftcardAction;
use App\Domain\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('returns balance and status for a real gift card number', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('50.00');

    Sanctum::actingAs($this->cashier, ['*']);
    $this->getJson("/api/v1/giftcards/{$giftcard->number}")
        ->assertOk()
        ->assertJsonPath('data.number', $giftcard->number)
        ->assertJsonPath('data.balance', '50.00')
        ->assertJsonPath('data.is_active', true);
});

it('returns 404 json for an unknown gift card number', function () {
    Sanctum::actingAs($this->cashier, ['*']);
    $this->getJson('/api/v1/giftcards/does-not-exist')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('reports an inactive card without 404ing', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('50.00');
    $giftcard->update(['is_active' => false]);

    Sanctum::actingAs($this->cashier, ['*']);
    $this->getJson("/api/v1/giftcards/{$giftcard->number}")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

it('rejects unauthenticated requests', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('50.00');

    $this->getJson("/api/v1/giftcards/{$giftcard->number}")->assertUnauthorized();
});

it('rejects a user without giftcards.view', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('50.00');
    $user = User::factory()->create();
    $user->assignRole('Stock Clerk');

    Sanctum::actingAs($user, ['*']);
    $this->getJson("/api/v1/giftcards/{$giftcard->number}")->assertForbidden();
});
