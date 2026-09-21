<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taxation. Scoped to single-rate VAT/GST with tax categories, but modelled so
 * the full destination-based jurisdiction matrix can be switched on later
 * without a schema rewrite: `tax_jurisdictions` exists and `tax_rates` already
 * carries the jurisdiction, effective-date and cascade columns the matrix needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_jurisdictions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->string('reporting_authority')->nullable();
            $table->unsignedTinyInteger('priority')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tax_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_jurisdiction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('rate', 9, 4);
            $table->string('rounding_mode', 24)->default('half_up');
            $table->unsignedTinyInteger('cascade_sequence')->default(0);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['tax_category_id', 'effective_from', 'effective_to'], 'tax_rates_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_categories');
        Schema::dropIfExists('tax_jurisdictions');
    }
};
