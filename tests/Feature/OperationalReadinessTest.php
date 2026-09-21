<?php

declare(strict_types=1);

use App\Support\Idempotency\IdempotencyKey;
use Illuminate\Support\Facades\Artisan;

it('schedules stock reconciliation, idempotency-key pruning, and loyalty point expiry', function () {
    // withSchedule()'s callback only wires up via an Artisan::starting hook
    // (see ApplicationBuilder::withSchedule()), so it must be inspected
    // through a real console command -- resolving Schedule::class directly
    // in a test that never boots the console kernel sees no events at all.
    Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($output)->toContain('stock:reconcile')
        ->and($output)->toContain('model:prune')
        ->and($output)->toContain('IdempotencyKey')
        ->and($output)->toContain('loyalty:expire-points');
});

it('auto-discovers a listener for each domain event that needs one', function () {
    // Listeners under app/Listeners/ are wired by Laravel's auto-discovery
    // (no EventServiceProvider in this app) purely from the `handle()`
    // type-hint -- this proves the binding actually exists, not just that
    // the listener class works in isolation when called directly.
    Artisan::call('event:list');
    $output = Artisan::output();

    expect($output)->toContain('SaleCompleted')
        ->and($output)->toContain('LogLowStockOnSaleCompleted')
        ->and($output)->toContain('ShiftOpened')
        ->and($output)->toContain('LogShiftOpened')
        ->and($output)->toContain('ShiftClosed')
        ->and($output)->toContain('LogShiftClosed')
        ->and($output)->toContain('Login')
        ->and($output)->toContain('RecordLastLogin');
});

it('prunes only expired idempotency keys', function () {
    $expired = IdempotencyKey::create([
        'key' => 'expired-key',
        'endpoint' => 'test',
        'request_hash' => 'hash',
        'response' => ['status' => 200, 'body' => []],
        'expires_at' => now()->subHour(),
    ]);
    $fresh = IdempotencyKey::create([
        'key' => 'fresh-key',
        'endpoint' => 'test',
        'request_hash' => 'hash',
        'response' => ['status' => 200, 'body' => []],
        'expires_at' => now()->addHour(),
    ]);

    Artisan::call('model:prune', ['--model' => [IdempotencyKey::class]]);

    expect(IdempotencyKey::find($expired->id))->toBeNull()
        ->and(IdempotencyKey::find($fresh->id))->not->toBeNull();
});
