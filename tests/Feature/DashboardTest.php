<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;

beforeEach(function () {
    $this->seed();
});

it('redirects the site root to the dashboard instead of the Laravel welcome page', function () {
    $owner = User::where('username', 'admin')->firstOrFail();

    $this->actingAs($owner)
        ->get('/')
        ->assertRedirect(route('dashboard'));
});

it('sends a guest hitting the site root on to login, via the dashboard route\'s own auth check', function () {
    // Route::redirect('/', '/dashboard') only performs the first hop; the
    // guest-to-login bounce comes from following it into /dashboard's own
    // `auth` middleware, not from any auth check on '/' itself.
    $this->followingRedirects()->get('/')->assertOk()->assertSee('Sign in');
});

it('shows owners a complete operational overview', function () {
    $owner = User::where('username', 'admin')->firstOrFail();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Sales today')
        ->assertSee('Recent sales')
        ->assertSee('To receive')
        ->assertSee('Needs attention')
        ->assertSee('Quick actions');
});

it('keeps purchasing and payables out of the cashier dashboard', function () {
    $cashier = User::where('username', 'cashier')->firstOrFail();

    $this->actingAs($cashier)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Recent sales')
        ->assertSee('Low stock')
        ->assertSee('Open register')
        ->assertDontSee('Outstanding')
        ->assertDontSee('Orders ready to receive');
});

it('gives stock staff a receiving and inventory workspace', function () {
    $stockClerk = User::where('username', 'stockclerk')->firstOrFail();

    $this->actingAs($stockClerk)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Orders ready to receive')
        ->assertSee('Low stock')
        ->assertSee('Receive stock')
        ->assertDontSee('Sales today');
});

it('renders useful focused dashboards for reporting and kitchen roles', function () {
    $reports = User::where('username', 'reports')->firstOrFail();
    $kitchen = User::where('username', 'kitchen')->firstOrFail();

    $this->actingAs($reports)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Sales today')
        ->assertSee('Sales report')
        ->assertDontSee('Quick actions');

    $this->actingAs($kitchen)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Kitchen display')
        ->assertSee('Your workspace')
        ->assertDontSee('Sales today')
        ->assertDontSee('Quick actions');
});
