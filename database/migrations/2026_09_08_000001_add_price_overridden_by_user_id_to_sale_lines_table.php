<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cart_lines already records who overrode a line's price
 * (price_overridden_by_user_id); sale_lines only ever inherited the boolean
 * flag from it, dropping the attribution the moment a sale completed --
 * an owner investigating a pattern of price overrides has no way to tell
 * which cashier is responsible once the cart becomes a sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_lines', function (Blueprint $table) {
            $table->foreignId('price_overridden_by_user_id')->nullable()->after('price_overridden')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_overridden_by_user_id');
        });
    }
};
