<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trading partners: customers and suppliers, both projections of `people`,
 * plus the loyalty packages customers can belong to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('points_per_currency_unit', 12, 4)->default(0);
            $table->decimal('currency_value_per_point', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->string('account_number')->nullable()->unique();
            $table->string('tax_number', 64)->nullable();
            $table->boolean('is_tax_exempt')->default(false);
            $table->foreignId('tax_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loyalty_package_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('points_balance', 15, 3)->default(0);
            $table->decimal('discount_value', 15, 4)->default(0);
            $table->string('discount_type', 12)->default('percent');
            $table->decimal('credit_limit', 19, 4)->default(0);
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('consent_given_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('agency_name')->nullable();
            $table->string('account_number')->nullable()->unique();
            $table->string('tax_number', 64)->nullable();
            // OSPOS `category`: 0 = goods supplier, 1 = cost/expense supplier.
            $table->string('supplier_type', 16)->default('goods');
            $table->unsignedSmallInteger('lead_time_days')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('loyalty_packages');
    }
};
