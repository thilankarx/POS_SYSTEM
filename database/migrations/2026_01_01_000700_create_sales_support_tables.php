<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supporting tables for the sales domain.
 *
 * `payment_methods` fixes a real OSPOS defect: payment types were stored as
 * *translated display strings* (`lang('Sales.cash')`), so changing the install
 * language fragmented historical payment data and every report grouping by
 * payment type. Methods now have a stable code; the label is presentation.
 *
 * `document_sequences` replaces the read-modify-write on the
 * `app_config.last_used_invoice_number` string, which raced under concurrency
 * and could issue duplicate invoice numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            // cash | card | voucher | account | external
            $table->string('kind', 16)->default('cash');
            // Which driver settles it: manual, stripe_terminal, adyen, ...
            $table->string('provider', 32)->default('manual');
            $table->boolean('opens_drawer')->default(false);
            $table->boolean('allows_change')->default(false);
            $table->boolean('allows_refund')->default(true);
            $table->boolean('counts_as_cash')->default(false);
            $table->boolean('requires_reference')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            // invoice | quote | work_order | receipt | purchase_order | ...
            $table->string('key', 32);
            $table->string('scope', 32)->default('global'); // e.g. a year, for yearly resets
            $table->string('prefix', 32)->default('');
            $table->unsignedBigInteger('next_value')->default(1);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->timestamps();

            $table->unique(['key', 'scope']);
        });

        Schema::create('return_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->boolean('restocks')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('dinner_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->foreignId('stock_location_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('seats')->default(0);
            $table->string('status', 16)->default('available'); // available | occupied
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinner_tables');
        Schema::dropIfExists('return_reasons');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('payment_methods');
    }
};
