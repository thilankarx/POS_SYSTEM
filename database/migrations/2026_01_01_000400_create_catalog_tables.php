<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog: categories, items, barcodes, kits and the EAV attribute system.
 *
 * Two changes from OSPOS worth noting:
 *  - `category` was a free-text varchar on the item; it is a real table here.
 *  - an item had exactly one `item_number`; barcodes are now a separate table,
 *    so an item can carry a retail EAN, a case code and a supplier code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tax_category_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('cost_price', 19, 4)->default(0);
            $table->decimal('unit_price', 19, 4)->default(0);
            $table->decimal('reorder_level', 15, 3)->default(0);

            // 'stocked' items move inventory; 'service' and 'amount_entry' do not.
            $table->string('stock_type', 16)->default('stocked');
            // 'standard' | 'amount_entry' | 'kit'
            $table->string('item_type', 16)->default('standard');

            $table->boolean('is_serialized')->default(false);
            $table->boolean('tracks_lots')->default(false);
            $table->boolean('has_expiry')->default(false);
            $table->boolean('allow_alt_description')->default(false);
            $table->boolean('is_active')->default(true);

            $table->decimal('qty_per_pack', 15, 3)->default(1);
            $table->string('pack_name', 32)->default('Each');
            $table->string('unit_of_measure', 16)->default('unit');

            $table->string('hsn_code', 32)->nullable();
            $table->string('image_path')->nullable();
            $table->foreignId('low_sell_item_id')->nullable()->constrained('items')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('item_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('barcode')->unique();
            $table->string('type', 24)->default('ean13');
            $table->decimal('pack_quantity', 15, 3)->default(1);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('item_kits', function (Blueprint $table) {
            $table->id();
            $table->string('kit_number')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('discount_value', 15, 4)->default(0);
            $table->string('discount_type', 12)->default('percent');
            // how the kit prices out: 'kit' (fixed kit price) | 'components' | 'both'
            $table->string('price_option', 16)->default('kit');
            $table->string('print_option', 16)->default('all');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('item_kit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_kit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();

            $table->unique(['item_kit_id', 'item_id']);
        });

        Schema::create('attribute_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('attribute_definitions')->nullOnDelete();
            $table->string('name');
            // text | dropdown | decimal | date | checkbox | group
            $table->string('type', 16);
            $table->string('unit', 16)->nullable();
            $table->boolean('show_in_receipt')->default(false);
            $table->boolean('show_in_search')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_definition_id')->constrained()->cascadeOnDelete();
            $table->string('value_text')->nullable();
            $table->date('value_date')->nullable();
            $table->decimal('value_decimal', 19, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->timestamps();

            $table->index(['attribute_definition_id', 'value_text'], 'attr_values_lookup_idx');
        });

        // Attributes attach to items, and are snapshotted onto sales/receivings.
        Schema::create('attribute_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->morphs('attributable');
            $table->timestamps();

            $table->unique(['attribute_value_id', 'attributable_id', 'attributable_type'], 'attr_links_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_links');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attribute_definitions');
        Schema::dropIfExists('item_kit_items');
        Schema::dropIfExists('item_kits');
        Schema::dropIfExists('item_barcodes');
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
    }
};
