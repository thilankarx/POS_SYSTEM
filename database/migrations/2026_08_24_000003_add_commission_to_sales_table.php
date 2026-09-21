<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('waiter_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            // Frozen at the moment of sale/refund, same reproducibility
            // principle as every other Sale total -- a later change to a
            // user's rate must never retroactively rewrite history.
            $table->decimal('commission_rate_applied', 5, 2)->nullable()->after('waiter_id');
            // Signed: positive on a sale, negative on a return, so a
            // report can just SUM() it and get the right answer with no
            // special-casing -- same convention as every other total here.
            $table->decimal('commission_amount', 19, 4)->nullable()->after('commission_rate_applied');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['commission_amount', 'commission_rate_applied']);
            $table->dropConstrainedForeignId('waiter_id');
        });
    }
};
