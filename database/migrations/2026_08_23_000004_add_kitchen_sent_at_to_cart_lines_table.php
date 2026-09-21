<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_lines', function (Blueprint $table) {
            $table->timestamp('kitchen_sent_at')->nullable()->after('price_overridden_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('cart_lines', function (Blueprint $table) {
            $table->dropColumn('kitchen_sent_at');
        });
    }
};
