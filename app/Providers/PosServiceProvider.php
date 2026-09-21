<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Documents\DocumentNumberGenerator;
use App\Domain\Inventory\InventoryService;
use App\Domain\Promotions\PromotionEngine;
use App\Domain\Promotions\PromotionSelector;
use App\Domain\Sales\CartPricer;
use App\Domain\Taxation\TaxEngine;
use Illuminate\Support\ServiceProvider;

class PosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The engine takes its pricing mode as a constructor flag rather than
        // reading config internally, which keeps it pure and unit-testable.
        // That means the container needs to be told how to build it.
        $this->app->bind(TaxEngine::class, fn () => TaxEngine::fromSettings());

        $this->app->singleton(InventoryService::class);
        $this->app->singleton(DocumentNumberGenerator::class);

        $this->app->bind(CartPricer::class, fn ($app) => new CartPricer(
            $app->make(TaxEngine::class),
            $app->make(PromotionSelector::class),
            $app->make(PromotionEngine::class),
        ));
    }
}
