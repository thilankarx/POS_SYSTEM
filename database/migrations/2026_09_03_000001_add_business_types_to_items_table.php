<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which business types (retail | hardware | restaurant -- the same set the
 * BusinessProfile "business type" setting uses) an item belongs to.
 *
 * NULL / empty means "sold everywhere": the register, the back-office
 * catalog and the item report all only hide an item when it has an
 * explicit list that excludes the store's configured type. Kept as a JSON
 * array rather than a pivot table because the values are a fixed, tiny
 * enum with no entity of their own to reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->json('business_types')->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('business_types');
        });
    }
};
