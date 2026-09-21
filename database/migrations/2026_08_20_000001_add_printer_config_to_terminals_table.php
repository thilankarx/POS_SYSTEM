<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->string('printer_connector', 16)->nullable()->after('receipt_printer');
            $table->unsignedSmallInteger('printer_paper_width')->nullable()->after('printer_connector');
        });
    }

    public function down(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->dropColumn(['printer_connector', 'printer_paper_width']);
        });
    }
};
