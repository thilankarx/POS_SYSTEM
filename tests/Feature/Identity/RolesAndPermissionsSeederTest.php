<?php

declare(strict_types=1);

use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed();
});

it('grants locations abilities to Owner, Manager and Stock Clerk', function () {
    expect(Role::findByName('Owner', 'web')->hasPermissionTo('locations.view'))->toBeTrue()
        ->and(Role::findByName('Owner', 'web')->hasPermissionTo('locations.manage'))->toBeTrue()
        ->and(Role::findByName('Manager', 'web')->hasPermissionTo('locations.view'))->toBeTrue()
        ->and(Role::findByName('Manager', 'web')->hasPermissionTo('locations.manage'))->toBeTrue()
        ->and(Role::findByName('Stock Clerk', 'web')->hasPermissionTo('locations.view'))->toBeTrue()
        ->and(Role::findByName('Stock Clerk', 'web')->hasPermissionTo('locations.manage'))->toBeFalse();
});

it('does not grant locations abilities to Cashier', function () {
    expect(Role::findByName('Cashier', 'web')->hasPermissionTo('locations.view'))->toBeFalse();
});
