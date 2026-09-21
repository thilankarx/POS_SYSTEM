<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "One open shift per terminal" was enforced only by OpenShiftAction reading
 * then inserting inside a transaction -- a SELECT ... FOR UPDATE that
 * matches no rows only takes a gap lock, so it protects nothing once a
 * connection runs at an isolation level without gap locking, or MySQL picks
 * a different access path. Without a real constraint behind it, that gap
 * silently allows a second open shift on the same terminal with no error at
 * all -- and once two exist, Terminal::openShift() picks whichever is
 * newest, so the other accumulates no sales and can never be found to close.
 *
 * MySQL has no partial/filtered unique index, so a generated column stands
 * in for one: NULL while a shift is closed (NULLs don't collide in a unique
 * index), the terminal_id itself while open (so a second open row on the
 * same terminal collides).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->unsignedBigInteger('open_terminal_marker')
                ->nullable()
                ->virtualAs("CASE WHEN status = 'open' THEN terminal_id ELSE NULL END")
                ->after('terminal_id');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->unique('open_terminal_marker', 'shifts_one_open_per_terminal');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropUnique('shifts_one_open_per_terminal');
            $table->dropColumn('open_terminal_marker');
        });
    }
};
