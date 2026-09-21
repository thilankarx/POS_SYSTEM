<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles and abilities.
 *
 * OSPOS had no roles at all: 36 flat permissions granted person by person, and
 * the check was a `LIKE 'sales%'` prefix match, so a grant of
 * `sales_stock_Warehouse` silently satisfied a check for `sales`. Abilities are
 * exact strings here and roles group them.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private array $abilities = [
        'sales' => ['view', 'create', 'checkout', 'suspend', 'refund', 'void', 'change_price', 'delete'],
        'items' => ['view', 'manage', 'delete', 'bulk_edit', 'import'],
        'item_kits' => ['view', 'manage'],
        'attributes' => ['manage'],
        'customers' => ['view', 'manage', 'delete', 'export'],
        'suppliers' => ['view', 'manage'],
        'users' => ['view', 'manage'],
        'giftcards' => ['view', 'manage'],
        'loyalty' => ['view', 'manage'],
        'expenses' => ['view', 'manage'],
        'taxes' => ['manage'],
        'shifts' => ['open', 'close', 'view_all'],
        'cash' => ['movement'],
        'inventory' => ['view', 'adjust', 'transfer', 'count', 'count_approve'],
        'purchasing' => ['view', 'manage', 'approve'],
        'receivings' => ['view', 'manage'],
        'promotions' => ['view', 'manage'],
        'terminals' => ['manage'],
        'tables' => ['view', 'manage'],
        'kitchen' => ['view'],
        'locations' => ['view', 'manage'],
        'audit' => ['view'],
        'config' => ['manage'],
        // Report abilities mirror the granularity OSPOS already had.
        'reports' => [
            'view', 'sales', 'items', 'categories', 'customers', 'employees',
            'suppliers', 'discounts', 'taxes', 'payments', 'inventory',
            'receivings', 'expenses', 'shifts',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = [];

        foreach ($this->abilities as $group => $actions) {
            foreach ($actions as $action) {
                $name = "{$group}.{$action}";
                Permission::findOrCreate($name, 'web');
                $all[] = $name;
            }
        }

        // Owner gets everything, including abilities added in future seeds.
        Role::findOrCreate('Owner', 'web')->syncPermissions($all);

        Role::findOrCreate('Manager', 'web')->syncPermissions(
            array_values(array_filter($all, fn (string $p) => ! in_array($p, [
                'config.manage', 'users.manage',
            ], true)))
        );

        Role::findOrCreate('Cashier', 'web')->syncPermissions([
            'sales.view', 'sales.create', 'sales.checkout', 'sales.suspend', 'sales.delete',
            'customers.view', 'customers.manage',
            'items.view', 'item_kits.view',
            'giftcards.view',
            'shifts.open', 'shifts.close',
            'inventory.view',
            'promotions.view',
            'loyalty.view',
            'tables.view',
            'kitchen.view',
        ]);

        // Waiters take orders -- build a cart, attach a customer, fire it to
        // the kitchen, park it -- but never touch money: no sales.checkout,
        // so they can't take payment or open the drawer, and no shifts.open,
        // so they always work an already-open shift a cashier started.
        Role::findOrCreate('Waiter', 'web')->syncPermissions([
            'sales.create', 'sales.suspend', 'sales.delete',
            'customers.view',
            'items.view', 'item_kits.view',
            'tables.view',
        ]);

        Role::findOrCreate('Stock Clerk', 'web')->syncPermissions([
            'items.view', 'items.manage', 'items.bulk_edit',
            'item_kits.view', 'item_kits.manage',
            'attributes.manage',
            'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.count',
            'locations.view',
            'purchasing.view', 'purchasing.manage',
            'receivings.view', 'receivings.manage',
            'suppliers.view', 'suppliers.manage',
            'reports.view', 'reports.inventory', 'reports.items', 'reports.receivings',
        ]);

        Role::findOrCreate('Accountant', 'web')->syncPermissions([
            'reports.view', 'reports.sales', 'reports.taxes', 'reports.payments',
            'reports.expenses', 'reports.shifts', 'reports.customers', 'reports.suppliers',
            'reports.employees',
            'sales.view',
            'expenses.view', 'expenses.manage',
            'purchasing.view', 'purchasing.approve',
            'shifts.view_all',
            'audit.view',
        ]);

        Role::findOrCreate('Reports Only', 'web')->syncPermissions(
            array_values(array_filter($all, fn (string $p) => str_starts_with($p, 'reports.')))
        );

        // A shared kitchen-display device logs in as this role: it can see
        // and work the fired-ticket queue and nothing else -- no POS,
        // sales, or reporting access at all.
        Role::findOrCreate('Kitchen', 'web')->syncPermissions(['kitchen.view']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
