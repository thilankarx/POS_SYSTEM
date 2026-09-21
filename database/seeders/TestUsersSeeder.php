<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestUsersSeeder extends Seeder
{
    public const PASSWORD = 'TestPassword123!';

    /** @var list<array{first_name: string, last_name: string, username: string, email: string, role: string, locations: list<string>}> */
    private const USERS = [
        ['first_name' => 'Ada', 'last_name' => 'Admin', 'username' => 'admin', 'email' => 'admin@example.test', 'role' => 'Owner', 'locations' => ['MAIN', 'WH']],
        ['first_name' => 'Morgan', 'last_name' => 'Manager', 'username' => 'manager', 'email' => 'manager@example.test', 'role' => 'Manager', 'locations' => ['MAIN', 'WH']],
        ['first_name' => 'Casey', 'last_name' => 'Cashier', 'username' => 'cashier', 'email' => 'cashier@example.test', 'role' => 'Cashier', 'locations' => ['MAIN']],
        ['first_name' => 'Willow', 'last_name' => 'Waiter', 'username' => 'waiter', 'email' => 'waiter@example.test', 'role' => 'Waiter', 'locations' => ['MAIN']],
        ['first_name' => 'Sam', 'last_name' => 'Stock', 'username' => 'stockclerk', 'email' => 'stockclerk@example.test', 'role' => 'Stock Clerk', 'locations' => ['MAIN', 'WH']],
        ['first_name' => 'Alex', 'last_name' => 'Accounts', 'username' => 'accountant', 'email' => 'accountant@example.test', 'role' => 'Accountant', 'locations' => ['MAIN', 'WH']],
        ['first_name' => 'Riley', 'last_name' => 'Reports', 'username' => 'reports', 'email' => 'reports@example.test', 'role' => 'Reports Only', 'locations' => ['MAIN', 'WH']],
        ['first_name' => 'Kit', 'last_name' => 'Kitchen', 'username' => 'kitchen', 'email' => 'kitchen@example.test', 'role' => 'Kitchen', 'locations' => ['MAIN']],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('Test users were not seeded in production.');

            return;
        }

        DB::transaction(function (): void {
            foreach (self::USERS as $definition) {
                $person = Person::withTrashed()->firstOrNew(['email' => $definition['email']]);
                $person->fill([
                    'first_name' => $definition['first_name'],
                    'last_name' => $definition['last_name'],
                    'deleted_at' => null,
                ])->save();

                $user = User::withTrashed()->firstOrNew(['username' => $definition['username']]);
                $user->fill([
                    'person_id' => $person->id,
                    'email' => $definition['email'],
                    'password' => self::PASSWORD,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'deleted_at' => null,
                ])->save();

                $user->syncRoles([$definition['role']]);
                $user->stockLocations()->sync(
                    StockLocation::query()->whereIn('code', $definition['locations'])->pluck('id')
                );
            }
        });

        $this->command?->info('Test users ready. Password: '.self::PASSWORD);
    }
}
