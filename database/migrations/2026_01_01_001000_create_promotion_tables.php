<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotions.
 *
 * Entirely new: OSPOS supported only a manual per-line discount and a flat
 * per-customer percentage. There was no BOGO, no coupon, no time window, no
 * category promotion. A promotion here is a set of conditions plus a reward,
 * evaluated by the pricing engine alongside tax.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 40)->nullable()->unique();
            $table->text('description')->nullable();

            // percent_off | amount_off | fixed_price | bogo | free_item
            $table->string('reward_type', 24);
            $table->decimal('reward_value', 19, 4)->default(0);
            $table->foreignId('reward_item_id')->nullable()->constrained('items')->nullOnDelete();

            $table->boolean('requires_coupon')->default(false);
            $table->boolean('stackable')->default(false);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('max_redemptions_per_customer')->nullable();
            $table->unsignedInteger('redemption_count')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            // Bitmask or CSV of active weekdays, plus a daily time window.
            $table->string('active_days', 32)->nullable();
            $table->time('active_from')->nullable();
            $table->time('active_to')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('promotion_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            // item | category | customer_group | cart_total | quantity
            $table->string('subject', 24);
            $table->string('operator', 16)->default('in');
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('promotion_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 19, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('promotion_conditions');
        Schema::dropIfExists('promotions');
    }
};
