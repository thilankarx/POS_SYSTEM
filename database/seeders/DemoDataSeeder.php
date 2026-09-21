<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBarcode;
use App\Domain\Crm\Models\Customer;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Sales\Models\Terminal;
use App\Domain\Taxation\Models\TaxCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('Demo catalogue and users were not seeded in production.');

            return;
        }

        $inventory = app(InventoryService::class);

        $main = StockLocation::updateOrCreate(['code' => 'MAIN'], [
            'name' => 'Main Store',
            'is_default' => true,
            'sells' => true,
            'receives' => true,
        ]);

        $warehouse = StockLocation::updateOrCreate(['code' => 'WH'], [
            'name' => 'Warehouse',
            'is_default' => false,
            'sells' => false,
            'receives' => true,
        ]);

        $terminal = Terminal::updateOrCreate(['code' => 'T1'], [
            'name' => 'Front Register',
            'stock_location_id' => $main->id,
            'is_active' => true,
        ]);

        Terminal::updateOrCreate(['code' => 'T2'], [
            'name' => 'Second Register',
            'stock_location_id' => $main->id,
            'is_active' => true,
        ]);

        $admin = $this->makeUser('Ada', 'Admin', 'admin', 'admin@example.test', 'Owner');
        $cashier = $this->makeUser('Casey', 'Clerk', 'cashier', 'cashier@example.test', 'Cashier');

        $admin->stockLocations()->syncWithoutDetaching([$main->id, $warehouse->id]);
        $cashier->stockLocations()->syncWithoutDetaching([$main->id]);

        $standard = TaxCategory::where('code', 'standard')->first();
        $zero = TaxCategory::where('code', 'zero')->first();

        $supplierPerson = Person::updateOrCreate(
            ['email' => 'sales@acme.test'],
            ['first_name' => 'Sam', 'last_name' => 'Supplier', 'phone' => '555-0100']
        );

        $supplier = Supplier::updateOrCreate(
            ['person_id' => $supplierPerson->id],
            ['company_name' => 'Acme Wholesale', 'supplier_type' => 'goods', 'lead_time_days' => 3]
        );

        $customerPerson = Person::updateOrCreate(
            ['email' => 'jane@example.test'],
            ['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '555-0111']
        );

        Customer::updateOrCreate(
            ['person_id' => $customerPerson->id],
            ['account_number' => 'CUST-0001', 'discount_value' => 0, 'discount_type' => 'percent']
        );

        $beverages = Category::updateOrCreate(['slug' => 'beverages'], ['name' => 'Beverages']);
        $bakery = Category::updateOrCreate(['slug' => 'bakery'], ['name' => 'Bakery']);
        $services = Category::updateOrCreate(['slug' => 'services'], ['name' => 'Services']);

        $catalog = [
            ['sku' => 'BEV-COLA-330', 'name' => 'Cola 330ml', 'category' => $beverages, 'cost' => '0.45', 'price' => '1.20', 'tax' => $standard, 'qty' => '240', 'barcode' => '5012345678900'],
            ['sku' => 'BEV-WATER-500', 'name' => 'Spring Water 500ml', 'category' => $beverages, 'cost' => '0.20', 'price' => '0.90', 'tax' => $zero, 'qty' => '300', 'barcode' => '5012345678917'],
            ['sku' => 'BAK-BREAD-WHT', 'name' => 'White Loaf', 'category' => $bakery, 'cost' => '0.80', 'price' => '2.10', 'tax' => $zero, 'qty' => '40', 'barcode' => '5012345678924', 'expiry' => true],
            ['sku' => 'BAK-CROIS', 'name' => 'Butter Croissant', 'category' => $bakery, 'cost' => '0.55', 'price' => '1.75', 'tax' => $standard, 'qty' => '60', 'barcode' => '5012345678931', 'expiry' => true],
            ['sku' => 'SRV-DELIVERY', 'name' => 'Local Delivery', 'category' => $services, 'cost' => '0.00', 'price' => '5.00', 'tax' => $standard, 'qty' => null, 'service' => true],
        ];

        foreach ($catalog as $definition) {
            $isService = $definition['service'] ?? false;

            $item = Item::updateOrCreate(['sku' => $definition['sku']], [
                'name' => $definition['name'],
                'category_id' => $definition['category']->id,
                'supplier_id' => $supplier->id,
                'tax_category_id' => $definition['tax']?->id,
                // A stocked item has no price of its own any more -- only a
                // service (which never gets a stock lot) prices from here.
                'unit_price' => $isService ? $definition['price'] : null,
                'reorder_level' => $isService ? 0 : 24,
                'stock_type' => $isService ? Item::STOCK_TYPE_SERVICE : Item::STOCK_TYPE_STOCKED,
                'has_expiry' => $definition['expiry'] ?? false,
                'is_active' => true,
            ]);

            if (isset($definition['barcode'])) {
                ItemBarcode::updateOrCreate(
                    ['barcode' => $definition['barcode']],
                    ['item_id' => $item->id, 'type' => 'ean13', 'is_primary' => true, 'pack_quantity' => 1]
                );
            }

            if ($definition['qty'] !== null) {
                // Every stocked item's opening stock is a real priced lot
                // now -- cost and selling price both live there, not on
                // the item.
                $stockLot = StockLot::updateOrCreate(
                    ['item_id' => $item->id, 'lot_number' => 'DEMO-OPENING'],
                    ['cost_price' => $definition['cost'], 'selling_price' => $definition['price']],
                );

                // Opening stock goes in as a ledger movement, not a direct write,
                // so the projection and the ledger agree from the very first row.
                $inventory->record(
                    item: $item,
                    stockLocationId: $main->id,
                    quantityDelta: $definition['qty'],
                    reason: StockMovement::REASON_ADJUSTMENT,
                    userId: $admin->id,
                    stockLotId: $stockLot->id,
                    unitCost: $definition['cost'],
                    note: 'Opening stock',
                );
            }
        }

        $this->command?->info("Demo data ready. Terminal [{$terminal->code}] at [{$main->name}].");
    }

    private function makeUser(string $first, string $last, string $username, string $email, string $role): User
    {
        $person = Person::updateOrCreate(
            ['email' => $email],
            ['first_name' => $first, 'last_name' => $last]
        );

        $user = User::updateOrCreate(
            ['username' => $username],
            [
                'person_id' => $person->id,
                'email' => $email,
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        $user->syncRoles([$role]);

        return $user;
    }
}
