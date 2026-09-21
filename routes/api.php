<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CartCouponController;
use App\Http\Controllers\Api\V1\CartCustomerController;
use App\Http\Controllers\Api\V1\CartKitchenTicketController;
use App\Http\Controllers\Api\V1\CartKitLineController;
use App\Http\Controllers\Api\V1\CartLineController;
use App\Http\Controllers\Api\V1\CartPaymentController;
use App\Http\Controllers\Api\V1\CartTipController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DinnerTableController;
use App\Http\Controllers\Api\V1\GiftcardController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\ItemKitController;
use App\Http\Controllers\Api\V1\KitchenController;
use App\Http\Controllers\Api\V1\OpenCashDrawerController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\SalePrintController;
use App\Http\Controllers\Api\V1\TerminalController;
use App\Http\Controllers\Documents\SaleReceiptController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('login', [AuthController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login');

    // Reachability probe for the register PWA's connectivity heartbeat. No
    // auth, no DB, no body -- the register only needs to know whether *this*
    // server answered, which is a different question from "is there any
    // network" (navigator.onLine). Kept deliberately cheap so a shopful of
    // registers polling it every few seconds costs nothing.
    Route::get('ping', fn () => response()->noContent())->name('ping');

    Route::middleware(['auth:sanctum', 'active.user', 'abilities:register'])->group(function () {
        Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

        Route::get('config', [ConfigController::class, 'show'])->name('config.show');

        Route::middleware('permission:items.view')->group(function () {
            Route::get('items', [ItemController::class, 'index'])->name('items.index');
            Route::get('items/barcode/{barcode}', [ItemController::class, 'showByBarcode'])->name('items.barcode');
            Route::get('items/{item}/lot-prices', [ItemController::class, 'lotPrices'])->name('items.lot-prices');
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        });

        Route::middleware('permission:item_kits.view')->group(function () {
            Route::get('item-kits', [ItemKitController::class, 'index'])->name('item-kits.index');
        });

        Route::middleware('permission:tables.view')->group(function () {
            Route::get('dinner-tables', [DinnerTableController::class, 'index'])->name('dinner-tables.index');
            Route::get('dinner-tables/{table}/cart', [DinnerTableController::class, 'cart'])->name('dinner-tables.cart');
        });

        Route::middleware('permission:kitchen.view')->group(function () {
            Route::get('kitchen/tickets', [KitchenController::class, 'index'])->name('kitchen.tickets.index');
            Route::post('kitchen/lines/{line}/prepare', [KitchenController::class, 'prepareLine'])->name('kitchen.lines.prepare');
        });

        Route::middleware('permission:customers.view')->group(function () {
            Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        });

        Route::middleware('permission:giftcards.view')->group(function () {
            Route::get('giftcards/{number}', [GiftcardController::class, 'showByNumber'])
                ->middleware('throttle:giftcard-lookup')
                ->name('giftcards.show');
        });

        Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');

        // Picking a terminal is how any POS-app login (register or a
        // kitchen-only device) tells the client which stock location it's
        // at -- required before the terminal-setup screen can complete, so
        // it can't sit behind a permission not every such login has.
        // Opening the cash drawer is a real sales action and stays gated.
        Route::get('terminals', [TerminalController::class, 'index'])->name('terminals.index');

        Route::middleware('permission:sales.create')->group(function () {
            // Opening the drawer and taking payment are cashier actions --
            // gated by sales.checkout on top of sales.create so a waiter can
            // build and fire an order without ever touching money.
            Route::post('terminals/{terminal}/open-drawer', OpenCashDrawerController::class)->name('terminals.open-drawer')->middleware('permission:sales.checkout');

            Route::get('carts', [CartController::class, 'index'])->name('carts.index');
            Route::post('carts', [CartController::class, 'store'])->name('carts.store');
            Route::get('carts/{cart}', [CartController::class, 'show'])->name('carts.show');
            Route::post('carts/{cart}/complete', [CartController::class, 'complete'])->name('carts.complete')->middleware('permission:sales.checkout');
            Route::post('carts/{cart}/suspend', [CartController::class, 'suspend'])->name('carts.suspend')->middleware('permission:sales.suspend');
            Route::post('carts/{cart}/abandon', [CartController::class, 'abandon'])->name('carts.abandon')->middleware('permission:sales.delete');

            Route::post('carts/{cart}/lines', [CartLineController::class, 'store'])->name('carts.lines.store');
            Route::patch('carts/{cart}/lines/{line}', [CartLineController::class, 'update'])->name('carts.lines.update');
            Route::delete('carts/{cart}/lines/{line}', [CartLineController::class, 'destroy'])->name('carts.lines.destroy');

            Route::post('carts/{cart}/kit-lines', [CartKitLineController::class, 'store'])->name('carts.kit-lines.store');

            Route::post('carts/{cart}/kitchen-ticket', CartKitchenTicketController::class)->name('carts.kitchen-ticket');

            Route::post('carts/{cart}/payments', [CartPaymentController::class, 'store'])->name('carts.payments.store')->middleware('permission:sales.checkout');
            Route::delete('carts/{cart}/payments/{payment}', [CartPaymentController::class, 'destroy'])->name('carts.payments.destroy')->middleware('permission:sales.checkout');

            // Tips are a checkout-time action, same as taking payment --
            // a waiter (sales.create without sales.checkout) never sets one.
            Route::patch('carts/{cart}/tip', [CartTipController::class, 'store'])->name('carts.tip.store')->middleware('permission:sales.checkout');

            Route::patch('carts/{cart}/coupon', [CartCouponController::class, 'store'])->name('carts.coupon.store');
            Route::delete('carts/{cart}/coupon', [CartCouponController::class, 'destroy'])->name('carts.coupon.destroy');

            Route::patch('carts/{cart}/customer', [CartCustomerController::class, 'store'])->name('carts.customer.store');
            Route::delete('carts/{cart}/customer', [CartCustomerController::class, 'destroy'])->name('carts.customer.destroy');
        });

        Route::middleware('permission:sales.view')->group(function () {
            Route::get('sales/{sale}/receipt', SaleReceiptController::class)->name('sales.receipt');
            Route::post('sales/{sale}/print-receipt', SalePrintController::class)->name('sales.print-receipt');
        });
    });
});
