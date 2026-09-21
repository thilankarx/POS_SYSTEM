<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

it('sizes the api rate limit from config, keyed per user', function () {
    config()->set('pos.api_rate_limit', 250);

    $request = Request::create('/api/v1/items', 'GET');
    $request->setUserResolver(fn () => (object) ['id' => 7]);

    $limit = RateLimiter::limiter('api')($request);

    expect($limit->maxAttempts)->toBe(250)
        ->and((string) $limit->key)->toBe('7');
});

it('defaults the api rate limit well above peak scanning', function () {
    // A fast lane bursts past the old value of 120; the default must leave
    // real headroom over a barcode-lookup + queued-add-line drain per scan.
    expect((int) config('pos.api_rate_limit'))->toBeGreaterThanOrEqual(300);
});
