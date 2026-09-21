<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Sales\Models\ReturnReason;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            PaymentMethodSeeder::class,
            TaxSeeder::class,
        ]);

        foreach ([
            ['code' => 'faulty', 'name' => 'Faulty / damaged', 'restocks' => false],
            ['code' => 'wrong_item', 'name' => 'Wrong item', 'restocks' => true],
            ['code' => 'changed_mind', 'name' => 'Changed mind', 'restocks' => true],
            ['code' => 'expired', 'name' => 'Expired', 'restocks' => false],
            ['code' => 'price_error', 'name' => 'Price error', 'restocks' => true],
        ] as $reason) {
            ReturnReason::updateOrCreate(['code' => $reason['code']], $reason);
        }

        foreach ([
            ['code' => 'rent', 'name' => 'Rent'],
            ['code' => 'utilities', 'name' => 'Utilities'],
            ['code' => 'wages', 'name' => 'Wages'],
            ['code' => 'supplies', 'name' => 'Supplies'],
            ['code' => 'marketing', 'name' => 'Marketing'],
        ] as $category) {
            ExpenseCategory::updateOrCreate(['code' => $category['code']], $category);
        }

        $this->call([
            DemoDataSeeder::class,
            TestUsersSeeder::class,
        ]);
    }
}
