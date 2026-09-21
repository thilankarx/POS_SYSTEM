<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The cart, persisted.
 *
 * OSPOS kept the in-progress basket in `$_SESSION` (Sale_lib), so a browser
 * crash, session expiry or shift handover destroyed it, and "suspending" a sale
 * meant writing a half-real row into the `sales` table with
 * `sale_status = SUSPENDED`. Here a cart is its own entity with its own
 * lifecycle, and only a completed cart becomes a sale.
 *
 * `client_uuid` is what makes the offline register work: the Vue PWA generates
 * it locally, queues the cart in IndexedDB, and replays it when the connection
 * returns without risking a duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('terminal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_location_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dinner_table_id')->nullable()->constrained()->nullOnDelete();

            // pos | invoice | quote | work_order | return
            $table->string('sale_type', 16)->default('pos');
            // active | suspended | completed | abandoned
            $table->string('status', 16)->default('active')->index();

            $table->string('reference')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('suspended_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cart_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->foreignId('item_id')->constrained();
            $table->foreignId('item_kit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_location_id')->constrained();

            $table->string('description')->nullable();
            $table->string('serial')->nullable();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('unit_price', 19, 4)->default(0);
            $table->decimal('cost_price', 19, 4)->default(0);
            $table->decimal('discount_value', 15, 4)->default(0);
            $table->string('discount_type', 12)->default('percent');
            $table->boolean('price_overridden')->default(false);
            $table->foreignId('price_overridden_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cart_id', 'line_number']);
        });

        Schema::create('cart_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained();
            $table->decimal('amount', 19, 4);
            $table->decimal('tendered', 19, 4)->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_payments');
        Schema::dropIfExists('cart_lines');
        Schema::dropIfExists('carts');
    }
};
