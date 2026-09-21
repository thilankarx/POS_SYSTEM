<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_locations', function (Blueprint $table) {
            $table->string('kitchen_printer_connector', 16)->nullable()->after('label_printer');
            $table->string('kitchen_printer', 64)->nullable()->after('kitchen_printer_connector');
        });
    }

    public function down(): void
    {
        Schema::table('stock_locations', function (Blueprint $table) {
            $table->dropColumn(['kitchen_printer_connector', 'kitchen_printer']);
        });
    }
};
