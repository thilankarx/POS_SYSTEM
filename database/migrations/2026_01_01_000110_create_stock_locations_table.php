<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock locations, and the pivot that scopes a user to the locations they may
 * operate in.
 *
 * OSPOS derived per-location permission ids from the location *name*
 * (`sales_Main_Warehouse`), so renaming a location deleted and recreated the
 * permission rows and cascade-wiped every grant. Scoping is a pivot on the
 * immutable id instead, so renames are inert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->boolean('is_default')->default(false);
            $table->boolean('sells')->default(true);
            $table->boolean('receives')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_location_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_location_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'stock_location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_location_user');
        Schema::dropIfExists('stock_locations');
    }
};
