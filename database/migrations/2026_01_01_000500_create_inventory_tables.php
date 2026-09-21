<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory.
 *
 * `stock_movements` is the single source of truth: append-only, one row per
 * physical movement, with a polymorphic `source` naming what caused it. OSPOS
 * encoded the cause in a free-text comment ("POS 12") and mutated
 * `item_quantities` in place from four different call sites, so the two drifted
 * and `reset_quantity()` existed to repair them.
 *
 * `stock_levels` is a maintained projection, updated atomically in the same
 * transaction and rebuildable from the ledger at any time.
 *
 * Lots, serials and stock counts are new: OSPOS had a free-text `serialnumber`
 * column and a one-item-at-a-time manual adjustment, which cannot support
 * recalls, FEFO picking or a counted stock take.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_location_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('reserved_quantity', 15, 3)->default(0);
            $table->timestamps();

            $table->unique(['item_id', 'stock_location_id']);
        });

        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('lot_number');
            $table->date('manufactured_on')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->decimal('cost_price', 19, 4)->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'lot_number']);
        });

        Schema::create('serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('serial')->unique();
            $table->foreignId('stock_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            // in_stock | sold | returned | scrapped
            $table->string('status', 16)->default('in_stock')->index();
            $table->foreignId('sold_on_sale_id')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();

            // Signed: negative removes stock, positive adds it.
            $table->decimal('quantity_delta', 15, 3);
            $table->decimal('balance_after', 15, 3)->nullable();
            $table->decimal('unit_cost', 19, 4)->nullable();

            // sale | receiving | transfer | adjustment | count | return
            $table->string('reason', 24)->index();
            $table->nullableMorphs('source');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['item_id', 'stock_location_id', 'occurred_at'], 'stock_movements_replay_idx');
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('stock_location_id')->constrained()->cascadeOnDelete();
            // draft | counting | review | approved | cancelled
            $table->string('status', 16)->default('draft')->index();
            $table->boolean('is_blind')->default(true);
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('expected_quantity', 15, 3)->default(0);
            $table->decimal('counted_quantity', 15, 3)->nullable();
            $table->decimal('variance', 15, 3)->nullable();
            $table->foreignId('counted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->unique(['stock_count_id', 'item_id', 'stock_lot_id'], 'stock_count_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('serial_numbers');
        Schema::dropIfExists('stock_lots');
        Schema::dropIfExists('stock_levels');
    }
};
