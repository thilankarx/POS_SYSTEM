<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales, sale lines, tax breakdown and payments.
 *
 * Totals are stored denormalised on the sale because a receipt must be
 * reproducible exactly as printed, even if a tax rate or a price changes later.
 * All money is decimal(19,4) and never touched by PHP floats; OSPOS summed
 * payments with floatval() addition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('client_uuid')->nullable()->unique();

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('terminal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_location_id')->constrained();
            $table->foreignId('dinner_table_id')->nullable()->constrained()->nullOnDelete();

            // pos | invoice | quote | work_order | return
            $table->string('sale_type', 16)->default('pos')->index();
            // completed | voided | refunded | partially_refunded
            $table->string('status', 24)->default('completed')->index();

            $table->string('invoice_number', 40)->nullable()->unique();
            $table->string('quote_number', 40)->nullable()->unique();
            $table->string('work_order_number', 40)->nullable()->unique();

            // A return points back at what it reverses.
            $table->foreignId('returns_sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('return_reason_id')->nullable()->constrained()->nullOnDelete();

            // A void undoes the sale in place -- no linked row, unlike a return.
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();

            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('discount_total', 19, 4)->default(0);
            $table->decimal('tax_total', 19, 4)->default(0);
            $table->decimal('rounding_adjustment', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->decimal('paid_total', 19, 4)->default(0);
            $table->decimal('change_given', 19, 4)->default(0);
            $table->decimal('cost_total', 19, 4)->default(0);
            $table->string('currency', 3)->default('USD');

            $table->text('comment')->nullable();
            $table->timestamp('sold_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['sold_at', 'sale_type']);
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->foreignId('item_id')->constrained();
            $table->foreignId('item_kit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_location_id')->constrained();

            // Snapshot of what was sold, independent of later catalog edits.
            $table->string('item_name');
            $table->string('sku');
            $table->string('description')->nullable();
            $table->string('serial')->nullable();

            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('cost_price', 19, 4)->default(0);
            $table->decimal('discount_value', 15, 4)->default(0);
            $table->string('discount_type', 12)->default('percent');
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('line_subtotal', 19, 4)->default(0);
            $table->decimal('line_tax', 19, 4)->default(0);
            $table->decimal('line_total', 19, 4)->default(0);
            $table->boolean('price_overridden')->default(false);
            $table->decimal('quantity_returned', 15, 3)->default(0);
            $table->timestamps();

            $table->unique(['sale_id', 'line_number']);
        });

        Schema::create('sale_line_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('rate', 9, 4);
            $table->decimal('taxable_amount', 19, 4);
            $table->decimal('tax_amount', 19, 4);
            $table->boolean('is_inclusive')->default(false);
            $table->timestamps();
        });

        // Per-sale tax summary, as printed on the receipt.
        Schema::create('sale_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('rate', 9, 4);
            $table->decimal('taxable_amount', 19, 4);
            $table->decimal('tax_amount', 19, 4);
            $table->unsignedTinyInteger('print_sequence')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 19, 4);
            $table->decimal('tendered', 19, 4)->nullable();
            $table->decimal('change_given', 19, 4)->default(0);
            $table->string('currency', 3)->default('USD');

            // Gateway lifecycle. OSPOS had none of this: a payment was a
            // translated label and a number, with no authorisation, capture,
            // void or settlement reconciliation.
            $table->string('provider', 32)->default('manual');
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_status', 32)->nullable();
            // pending | authorized | captured | failed | voided | refunded
            $table->string('status', 24)->default('captured')->index();
            $table->string('card_brand', 24)->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('auth_code', 32)->nullable();
            $table->string('reference')->nullable();
            $table->json('provider_payload')->nullable();

            $table->foreignId('refunds_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
        });

        // Idempotency for sale completion: a replayed request returns the
        // original sale instead of creating a second one.
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('endpoint', 64);
            $table->string('request_hash', 64)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('resource');
            $table->json('response')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_taxes');
        Schema::dropIfExists('sale_line_taxes');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
    }
};
