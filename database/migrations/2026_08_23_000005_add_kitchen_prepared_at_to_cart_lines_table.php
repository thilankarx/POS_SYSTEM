<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_lines', function (Blueprint $table) {
            $table->timestamp('kitchen_prepared_at')->nullable()->after('kitchen_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('cart_lines', function (Blueprint $table) {
            $table->dropColumn('kitchen_prepared_at');
        });
    }
};
