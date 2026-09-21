<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_locations', function (Blueprint $table) {
            $table->string('label_printer_connector', 16)->nullable()->after('receives');
            $table->string('label_printer', 64)->nullable()->after('label_printer_connector');
        });
    }

    public function down(): void
    {
        Schema::table('stock_locations', function (Blueprint $table) {
            $table->dropColumn(['label_printer_connector', 'label_printer']);
        });
    }
};
