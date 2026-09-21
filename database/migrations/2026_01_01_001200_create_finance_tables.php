<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gift cards, loyalty points and expenses.
 *
 * Gift cards and points are ledgers here, not a single mutable balance column.
 * OSPOS did a read-then-write on `giftcards.value` and `customers.points`
 * inside sale completion with no locking, which loses updates under
 * concurrency and leaves no history of how a balance was reached.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('giftcards', function (Blueprint $table) {
            $table->id();
            $table->string('number', 64)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('initial_value', 19, 4);
            $table->decimal('balance', 19, 4);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('giftcard_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('giftcard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // issue | redeem | topup | refund | adjustment
            $table->string('type', 16);
            $table->decimal('amount', 19, 4);
            $table->decimal('balance_after', 19, 4);
            $table->timestamps();
        });

        Schema::create('points_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loyalty_package_id')->nullable()->constrained()->nullOnDelete();
            // earn | redeem | expire | adjustment
            $table->string('type', 16);
            $table->decimal('points', 15, 3);
            $table->decimal('balance_after', 15, 3);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('stock_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 19, 4);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->date('spent_on')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('points_transactions');
        Schema::dropIfExists('giftcard_transactions');
        Schema::dropIfExists('giftcards');
    }
};
