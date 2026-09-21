<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchasing.
 *
 * OSPOS modelled only the goods *arriving* (`receivings`). There was no order,
 * no expected delivery, no partial receipt against an order, and no payables.
 * Purchase orders and supplier invoices close that loop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('stock_location_id')->constrained();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // draft | submitted | approved | partially_received | received | cancelled
            $table->string('status', 24)->default('draft')->index();
            $table->date('expected_on')->nullable();
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('tax_total', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->text('note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->unsignedInteger('line_number');
            $table->decimal('quantity_ordered', 15, 3);
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('line_total', 19, 4)->default(0);
            $table->timestamps();

            $table->unique(['purchase_order_id', 'line_number']);
        });

        Schema::create('receivings', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_location_id')->constrained();
            $table->foreignId('user_id')->constrained();

            // receipt | return_to_supplier | transfer_in | transfer_out
            $table->string('type', 24)->default('receipt')->index();
            $table->foreignId('transfer_to_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();

            $table->string('supplier_reference')->nullable();
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('tax_total', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->text('comment')->nullable();
            $table->timestamp('received_at')->index();
            $table->timestamps();
        });

        Schema::create('receiving_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('line_number');

            $table->string('description')->nullable();
            $table->string('serial')->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('discount_value', 15, 4)->default(0);
            $table->string('discount_type', 12)->default('percent');
            $table->decimal('line_total', 19, 4)->default(0);
            // Pack handling: how many stock units one received unit represents.
            $table->decimal('pack_quantity', 15, 3)->default(1);
            $table->timestamps();

            $table->unique(['receiving_id', 'line_number']);
        });

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 64);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('total', 19, 4);
            $table->decimal('paid_total', 19, 4)->default(0);
            // open | partially_paid | paid | disputed
            $table->string('status', 24)->default('open')->index();
            $table->timestamps();

            $table->unique(['supplier_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('receiving_lines');
        Schema::dropIfExists('receivings');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
