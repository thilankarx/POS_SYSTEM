<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
});

it('renders the primary navigation and account menu', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('aria-label="Main navigation"', false)
        ->assertSee('Collapse navigation')
        ->assertSee('Dashboard')
        ->assertSee('Point of sale')
        ->assertSee('Log out');
});

it('keeps an index navigation link active on related resource pages', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('purchase-orders.create'))
        ->assertOk()
        ->assertSee('Purchase Orders');

    expect(substr_count($response->getContent(), 'aria-current="page"'))->toBe(1);
});
