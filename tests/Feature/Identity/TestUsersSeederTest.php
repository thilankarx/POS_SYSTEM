<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed();
});

it('creates an active test account for every role', function () {
    $expectedRoles = [
        'admin' => 'Owner',
        'manager' => 'Manager',
        'cashier' => 'Cashier',
        'waiter' => 'Waiter',
        'stockclerk' => 'Stock Clerk',
        'accountant' => 'Accountant',
        'reports' => 'Reports Only',
        'kitchen' => 'Kitchen',
    ];

    foreach ($expectedRoles as $username => $role) {
        $user = User::where('username', $username)->firstOrFail();

        expect($user->is_active)->toBeTrue()
            ->and($user->hasRole($role))->toBeTrue()
            ->and(Hash::check(TestUsersSeeder::PASSWORD, $user->password))->toBeTrue()
            ->and($user->stockLocations)->not->toBeEmpty();
    }
});

it('can be rerun without creating duplicate accounts', function () {
    $this->seed(TestUsersSeeder::class);

    expect(User::whereIn('username', [
        'admin', 'manager', 'cashier', 'waiter',
        'stockclerk', 'accountant', 'reports', 'kitchen',
    ])->count())->toBe(8);
});
