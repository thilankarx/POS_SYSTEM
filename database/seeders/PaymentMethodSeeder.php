<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Sales\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/**
 * Payment methods keyed by a stable `code`.
 *
 * The legacy system stored the translated label ("Cash" / "Efectivo") in the
 * payments table, so switching language fragmented historical data.
 */
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['code' => 'cash', 'name' => 'Cash', 'kind' => 'cash', 'opens_drawer' => true, 'allows_change' => true, 'counts_as_cash' => true, 'sort_order' => 10],
            ['code' => 'card', 'name' => 'Card', 'kind' => 'card', 'provider' => 'manual', 'requires_reference' => false, 'sort_order' => 20],
            ['code' => 'card_terminal', 'name' => 'Card (Terminal)', 'kind' => 'card', 'provider' => 'stripe_terminal', 'is_active' => false, 'sort_order' => 25],
            ['code' => 'check', 'name' => 'Check', 'kind' => 'external', 'requires_reference' => true, 'sort_order' => 30],
            ['code' => 'giftcard', 'name' => 'Gift Card', 'kind' => 'voucher', 'requires_reference' => true, 'sort_order' => 40],
            ['code' => 'points', 'name' => 'Loyalty Points', 'kind' => 'voucher', 'sort_order' => 50],
            ['code' => 'account', 'name' => 'On Account', 'kind' => 'account', 'sort_order' => 60],
            ['code' => 'bank_transfer', 'name' => 'Bank Transfer', 'kind' => 'external', 'requires_reference' => true, 'sort_order' => 70],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(['code' => $method['code']], $method);
        }
    }
}
