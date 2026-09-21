<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use Database\Seeders\TestUsersSeeder;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->seed();
});

it('issues a token on successful login', function () {
    $response = $this->postJson('/api/v1/login', [
        'username' => 'cashier',
        'password' => TestUsersSeeder::PASSWORD,
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'user' => ['id', 'username', 'name']]);

    $user = User::where('username', 'cashier')->firstOrFail();
    expect(PersonalAccessToken::where('tokenable_id', $user->id)->count())->toBe(1);
});

it('reports can_operate_register true for a role with sales.create', function () {
    $response = $this->postJson('/api/v1/login', [
        'username' => 'cashier',
        'password' => TestUsersSeeder::PASSWORD,
    ])->assertCreated();

    expect($response->json('user.can_operate_register'))->toBeTrue();
});

it('reports can_operate_register false for a kitchen-only role', function () {
    // TestUsersSeeder (run via beforeEach's $this->seed()) already seeds a
    // 'kitchen' user with the 'Kitchen' role -- reuse it instead of creating
    // a second user with the same username, which collides on the
    // users_username_unique constraint.
    $response = $this->postJson('/api/v1/login', [
        'username' => 'kitchen',
        'password' => TestUsersSeeder::PASSWORD,
    ])->assertCreated();

    expect($response->json('user.can_operate_register'))->toBeFalse();
});

it('reports can_checkout true for a role with sales.checkout', function () {
    $response = $this->postJson('/api/v1/login', [
        'username' => 'cashier',
        'password' => TestUsersSeeder::PASSWORD,
    ])->assertCreated();

    expect($response->json('user.can_checkout'))->toBeTrue();
});

it('reports can_operate_register true but can_checkout false for a waiter', function () {
    // TestUsersSeeder already seeds a 'waiter' user with the 'Waiter' role --
    // see the 'kitchen' test above for why this reuses it rather than
    // creating a second user with the same username.
    $response = $this->postJson('/api/v1/login', [
        'username' => 'waiter',
        'password' => TestUsersSeeder::PASSWORD,
    ])->assertCreated();

    expect($response->json('user.can_operate_register'))->toBeTrue()
        ->and($response->json('user.can_checkout'))->toBeFalse();
});

it('records last_login_at and last_login_ip on successful login', function () {
    $user = User::where('username', 'cashier')->firstOrFail();
    expect($user->last_login_at)->toBeNull();

    $this->postJson('/api/v1/login', [
        'username' => 'cashier',
        'password' => TestUsersSeeder::PASSWORD,
    ])->assertCreated();

    $user->refresh();
    expect($user->last_login_at)->not->toBeNull()
        ->and($user->last_login_ip)->not->toBeNull();
});

it('rejects an incorrect password', function () {
    $this->postJson('/api/v1/login', [
        'username' => 'cashier',
        'password' => 'wrong-password',
    ])->assertUnprocessable();
});

it('rejects an inactive user', function () {
    $user = User::where('username', 'cashier')->firstOrFail();
    $user->update(['is_active' => false]);

    $this->postJson('/api/v1/login', [
        'username' => 'cashier',
        'password' => TestUsersSeeder::PASSWORD,
    ])->assertUnprocessable();
});

it('revokes the token on logout', function () {
    $login = $this->postJson('/api/v1/login', ['username' => 'cashier', 'password' => TestUsersSeeder::PASSWORD]);
    $token = $login->json('token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/logout')
        ->assertNoContent();

    expect(PersonalAccessToken::query()->count())->toBe(0);

    // Laravel's auth guard caches the resolved user on the guard instance for
    // the lifetime of the test's application container; a real HTTP request
    // never reuses a guard instance across requests, but a second call within
    // one test method does, so the cached guard must be forgotten to prove
    // the *next* request re-resolves against the (now-deleted) token.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/payment-methods')
        ->assertUnauthorized();
});

it('rejects a protected route with no token', function () {
    $this->getJson('/api/v1/payment-methods')->assertUnauthorized();
});

it('issues a token scoped to the register ability, not a wildcard', function () {
    $login = $this->postJson('/api/v1/login', [
        'username' => 'cashier',
        'password' => TestUsersSeeder::PASSWORD,
    ])->assertCreated();

    $token = PersonalAccessToken::where('tokenable_id', User::where('username', 'cashier')->firstOrFail()->id)->firstOrFail();

    expect($token->abilities)->toBe(['register']);

    // The token this endpoint just issued can still reach a protected route --
    // scoping it did not accidentally lock out its own intended use.
    $this->withHeader('Authorization', "Bearer {$login->json('token')}")
        ->getJson('/api/v1/payment-methods')
        ->assertOk();
});

it('rejects a token that lacks the register ability', function () {
    $user = User::where('username', 'cashier')->firstOrFail();
    $token = $user->createToken('test', ['some-other-ability'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/payment-methods')
        ->assertForbidden();
});
