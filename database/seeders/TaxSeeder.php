<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Taxation\Models\TaxCategory;
use App\Domain\Taxation\Models\TaxJurisdiction;
use App\Domain\Taxation\Models\TaxRate;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    public function run(): void
    {
        $jurisdiction = TaxJurisdiction::updateOrCreate(
            ['code' => 'default'],
            ['name' => 'Default', 'is_default' => true, 'priority' => 0]
        );

        $categories = [
            ['code' => 'standard', 'name' => 'Standard Rate', 'is_default' => true, 'rate' => '15.0000'],
            ['code' => 'reduced', 'name' => 'Reduced Rate', 'is_default' => false, 'rate' => '8.0000'],
            ['code' => 'zero', 'name' => 'Zero Rated', 'is_default' => false, 'rate' => '0.0000'],
            ['code' => 'exempt', 'name' => 'Exempt', 'is_default' => false, 'rate' => null],
        ];

        foreach ($categories as $definition) {
            $category = TaxCategory::updateOrCreate(
                ['code' => $definition['code']],
                ['name' => $definition['name'], 'is_default' => $definition['is_default']]
            );

            if ($definition['rate'] === null) {
                continue;
            }

            TaxRate::updateOrCreate(
                ['tax_category_id' => $category->id, 'tax_jurisdiction_id' => $jurisdiction->id],
                [
                    'name' => $definition['name'],
                    'rate' => $definition['rate'],
                    'rounding_mode' => 'half_up',
                    'cascade_sequence' => 0,
                    'effective_from' => null,
                    'effective_to' => null,
                ]
            );
        }
    }
}
