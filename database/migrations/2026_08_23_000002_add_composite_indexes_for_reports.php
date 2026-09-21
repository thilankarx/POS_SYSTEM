<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['stock_location_id', 'sold_at']);
        });

        Schema::table('receivings', function (Blueprint $table) {
            $table->index(['supplier_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['stock_location_id', 'sold_at']);
        });

        Schema::table('receivings', function (Blueprint $table) {
            $table->dropIndex(['supplier_id', 'received_at']);
        });
    }
};
