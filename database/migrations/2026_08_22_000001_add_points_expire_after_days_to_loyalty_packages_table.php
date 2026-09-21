<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_packages', function (Blueprint $table) {
            $table->unsignedSmallInteger('points_expire_after_days')->nullable()->after('currency_value_per_point');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_packages', function (Blueprint $table) {
            $table->dropColumn('points_expire_after_days');
        });
    }
};
