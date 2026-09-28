<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Windows receipt printer on a register PC is stored as
     * smb://HOST/Share, which does not fit the original 64 characters.
     */
    public function up(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->string('receipt_printer', 160)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->string('receipt_printer', 64)->nullable()->change();
        });
    }
};
