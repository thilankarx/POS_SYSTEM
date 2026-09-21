<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

it('has no public registration route', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Intruder',
        'email' => 'intruder@example.test',
        'password' => 'a-very-long-password',
        'password_confirmation' => 'a-very-long-password',
    ])->assertNotFound();

    expect(User::where('email', 'intruder@example.test')->exists())->toBeFalse();
});

it('has no password reset routes', function () {
    $this->get('/forgot-password')->assertNotFound();
});

it('refuses a web login for a deactivated user', function () {
    $user = User::factory()->create(['is_active' => false, 'password' => Hash::make('password')]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors();

    $this->assertGuest();
});

it('lets an active user log in through the web guard', function () {
    $user = User::factory()->create(['is_active' => true, 'password' => Hash::make('password')]);
    expect($user->last_login_at)->toBeNull();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect($user->last_login_at)->not->toBeNull()
        ->and($user->last_login_ip)->not->toBeNull();
});
