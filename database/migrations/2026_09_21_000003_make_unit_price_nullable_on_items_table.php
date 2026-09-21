<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * unit_price now only means anything for non-stocked items (services,
 * amount-entry) -- a stocked item's price always lives on its stock lot.
 * Null, not zero, so a stocked item with no priced lot correctly reads as
 * "no price yet" rather than "free".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('unit_price', 19, 4)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('unit_price', 19, 4)->default(0)->change();
        });
    }
};
