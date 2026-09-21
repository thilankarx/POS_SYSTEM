<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cost is now always per stock lot -- no item-level fallback survives it.
 * Lot tracking is now mandatory for every stocked item, so the opt-in flag
 * that used to gate the lot_number requirement at receiving is gone too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['cost_price', 'tracks_lots']);
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('cost_price', 19, 4)->default(0)->after('supplier_id');
            $table->boolean('tracks_lots')->default(false)->after('stock_type');
        });
    }
};
