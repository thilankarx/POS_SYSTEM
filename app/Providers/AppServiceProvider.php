<?php

namespace App\Providers;

use App\Domain\Catalog\Models\AttributeDefinition;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemKit;
use App\Domain\Crm\Models\Customer;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Giftcards\Models\Giftcard;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Loyalty\Models\LoyaltyPackage;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Receiving;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\DinnerTable;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use App\Domain\Taxation\Models\TaxCategory;
use App\Policies\AttributeDefinitionPolicy;
use App\Policies\CartPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DinnerTablePolicy;
use App\Policies\ExpenseCategoryPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\GiftcardPolicy;
use App\Policies\ItemKitPolicy;
use App\Policies\ItemPolicy;
use App\Policies\LoyaltyPackagePolicy;
use App\Policies\PromotionPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\ReceivingPolicy;
use App\Policies\SalePolicy;
use App\Policies\ShiftPolicy;
use App\Policies\StockCountPolicy;
use App\Policies\StockLocationPolicy;
use App\Policies\SupplierInvoicePolicy;
use App\Policies\SupplierPolicy;
use App\Policies\TaxCategoryPolicy;
use App\Policies\TerminalPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fortify's own limiter covers /login; this is everything else --
        // routes/api.php previously had no rate limiter at all beyond that
        // one route, so a single valid token (a shared terminal, a stolen
        // token, ...) could call anything at whatever rate it liked.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('pos.api_rate_limit', 300))
                ->by($request->user()?->id ?: $request->ip());
        });

        // A tighter, dedicated limit for the gift-card lookup endpoint:
        // card number entropy is the only thing standing between a caller
        // and an oracle for "is this number valid, and what's on it" --
        // the general API limit alone is loose enough to grind through a
        // meaningful chunk of the number space.
        RateLimiter::for('giftcard-lookup', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(ItemKit::class, ItemKitPolicy::class);
        Gate::policy(AttributeDefinition::class, AttributeDefinitionPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(StockLocation::class, StockLocationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Shift::class, ShiftPolicy::class);
        Gate::policy(Cart::class, CartPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(Receiving::class, ReceivingPolicy::class);
        Gate::policy(SupplierInvoice::class, SupplierInvoicePolicy::class);
        Gate::policy(Promotion::class, PromotionPolicy::class);
        Gate::policy(StockCount::class, StockCountPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(LoyaltyPackage::class, LoyaltyPackagePolicy::class);
        Gate::policy(Giftcard::class, GiftcardPolicy::class);
        Gate::policy(Terminal::class, TerminalPolicy::class);
        Gate::policy(DinnerTable::class, DinnerTablePolicy::class);
        Gate::policy(TaxCategory::class, TaxCategoryPolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(ExpenseCategory::class, ExpenseCategoryPolicy::class);
    }
}
