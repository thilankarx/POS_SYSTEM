<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registers, shifts and cash accountability.
 *
 * OSPOS had no terminal entity at all: `cash_up` recorded an employee and some
 * amounts, but nothing tied a sale to a physical drawer, so a two-till store
 * could not answer "which drawer is short?". A sale now belongs to a terminal
 * and a shift, and a shift close is counted by denomination.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->foreignId('stock_location_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_printer', 64)->nullable();
            $table->string('payment_terminal_id', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by_user_id')->constrained('users');
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('opening_float', 19, 4)->default(0);
            $table->decimal('expected_cash', 19, 4)->nullable();
            $table->decimal('counted_cash', 19, 4)->nullable();
            $table->decimal('cash_variance', 19, 4)->nullable();
            $table->decimal('expected_non_cash', 19, 4)->nullable();
            $table->decimal('counted_non_cash', 19, 4)->nullable();
            $table->decimal('cash_dropped', 19, 4)->default(0);

            $table->string('status', 16)->default('open')->index();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Denomination breakdown at shift close. OSPOS stored a single total.
        Schema::create('shift_cash_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->decimal('denomination', 19, 4);
            $table->unsignedInteger('count')->default(0);
            $table->decimal('subtotal', 19, 4);
            $table->timestamps();
        });

        // Cash paid in/out of the drawer outside of sales (petty cash, drops).
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('direction', 8); // in | out
            $table->decimal('amount', 19, 4);
            $table->string('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('shift_cash_counts');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('terminals');
    }
};
