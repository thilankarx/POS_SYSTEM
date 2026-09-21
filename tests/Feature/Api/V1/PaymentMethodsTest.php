<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Models\PaymentMethod;
use Laravel\Sanctum\Sanctum;

it('returns only active payment methods, ordered by sort_order', function () {
    $this->seed();

    PaymentMethod::where('code', 'check')->update(['is_active' => false]);

    Sanctum::actingAs(User::where('username', 'cashier')->firstOrFail(), ['*']);
    $response = $this->getJson('/api/v1/payment-methods')->assertOk();

    $codes = collect($response->json('data'))->pluck('code');
    expect($codes)->not->toContain('check')
        ->and($codes->first())->toBe('cash');
});
