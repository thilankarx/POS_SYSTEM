<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_lots', function (Blueprint $table) {
            // Null means "this lot has no price of its own -- use the
            // item's unit_price" -- the fallback signal, not just "not
            // entered yet". Lets old stock keep an old price while a newer
            // lot of the same item is priced differently on the shelf.
            $table->decimal('selling_price', 19, 4)->nullable()->after('cost_price');
        });
    }

    public function down(): void
    {
        Schema::table('stock_lots', function (Blueprint $table) {
            $table->dropColumn('selling_price');
        });
    }
};
