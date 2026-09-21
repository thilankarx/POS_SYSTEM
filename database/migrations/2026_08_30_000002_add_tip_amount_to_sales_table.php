<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Unlike commission_amount, this is never signed/reversed on a
            // return -- a customer returning an item doesn't get their tip
            // back, and a void already drops the whole sale (tip included)
            // out of any report via the same status != voided filter every
            // other report already uses. Never nullable, same reasoning as
            // carts.tip_amount.
            $table->decimal('tip_amount', 19, 4)->default(0)->after('change_given');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('tip_amount');
        });
    }
};
