<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('abandoned_by_user_id')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();
            $table->timestamp('abandoned_at')->nullable()->after('abandoned_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('abandoned_by_user_id');
            $table->dropColumn('abandoned_at');
        });
    }
};
