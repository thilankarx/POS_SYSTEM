<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // Set at payment time (gated by sales.checkout, same as taking a
            // payment or completing the sale) -- never nullable so every
            // consumer (CartResource, dueRemaining math, CompleteSaleAction)
            // can treat it as a real amount without a null check.
            $table->decimal('tip_amount', 19, 4)->default(0)->after('waiter_id');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('tip_amount');
        });
    }
};
