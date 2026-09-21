<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use Database\Seeders\DemoDataSeeder;

it('does not create demo data in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    try {
        app(DemoDataSeeder::class)->run();

        expect(Item::query()->count())->toBe(0)
            ->and(User::query()->count())->toBe(0);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});
