<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed();
});

it('lists only active terminals at locations the user can operate', function () {
    $warehouse = StockLocation::where('code', 'WH')->firstOrFail();
    $otherTerminal = Terminal::create([
        'stock_location_id' => $warehouse->id,
        'code' => 'WH-T1',
        'name' => 'Warehouse Terminal',
        'is_active' => true,
    ]);

    Terminal::where('code', 'T2')->update(['is_active' => false]);

    Sanctum::actingAs(User::where('username', 'cashier')->firstOrFail(), ['*']);
    $response = $this->getJson('/api/v1/terminals')->assertOk();

    $codes = collect($response->json('data'))->pluck('code');

    expect($codes)->toContain('T1')
        ->and($codes)->not->toContain('T2')
        ->and($codes)->not->toContain($otherTerminal->code);
});

it('reports shift_open reflecting whether the terminal has an open shift', function () {
    $terminal = Terminal::where('code', 'T1')->firstOrFail();
    $cashier = User::where('username', 'cashier')->firstOrFail();
    Sanctum::actingAs($cashier, ['*']);
    $before = $this->getJson('/api/v1/terminals')->assertOk();
    expect(collect($before->json('data'))->firstWhere('code', 'T1')['shift_open'])->toBeFalse();

    Shift::create([
        'terminal_id' => $terminal->id,
        'opened_by_user_id' => $cashier->id,
        'status' => Shift::STATUS_OPEN,
        'opening_float' => '100.00',
        'opened_at' => now(),
    ]);

    $after = $this->getJson('/api/v1/terminals')->assertOk();
    expect(collect($after->json('data'))->firstWhere('code', 'T1')['shift_open'])->toBeTrue();
});

it('rejects an unauthenticated request', function () {
    $this->getJson('/api/v1/terminals')->assertUnauthorized();
});

it('lets a kitchen-only login (no sales.create) list terminals to complete setup', function () {
    // TestUsersSeeder (run via beforeEach's $this->seed()) already seeds a
    // 'kitchen' user with the 'Kitchen' role at the MAIN location -- reuse
    // it instead of creating a second user with the same username, which
    // collides on the users_username_unique constraint.
    $user = User::where('username', 'kitchen')->firstOrFail();

    Sanctum::actingAs($user, ['*']);
    $this->getJson('/api/v1/terminals')->assertOk();
});
