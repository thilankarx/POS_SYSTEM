<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Unit tests boot the framework (so config and the container are available) but
 * deliberately do NOT get RefreshDatabase: nothing under tests/Unit is allowed
 * to touch the database. That is the property being protected -- the tax engine
 * and money arithmetic must be provable without a schema.
 */
pest()->extend(TestCase::class)->in('Unit');

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * Opens a shift directly (bypassing OpenShiftAction) for tests that need an
 * open shift as setup rather than as the thing under test. Idempotent: a
 * terminal can only ever have one open shift (the shifts_one_open_per_terminal
 * DB constraint enforces it), and several tests call this more than once for
 * the same terminal purely as setup, so returning the existing open shift
 * rather than trying to insert a second one is what "ensure a shift is open"
 * actually means here.
 */
function openShiftFor(User $user, Terminal $terminal, string $float = '100.00'): Shift
{
    $existing = Shift::where('terminal_id', $terminal->id)->where('status', Shift::STATUS_OPEN)->first();

    if ($existing !== null) {
        return $existing;
    }

    return Shift::create([
        'terminal_id' => $terminal->id,
        'opened_by_user_id' => $user->id,
        'opening_float' => $float,
        'status' => Shift::STATUS_OPEN,
        'opened_at' => now(),
    ]);
}

/**
 * The price AddCartLineAction would resolve for a demo item today: its
 * FEFO in-stock lot's price. Stocked items no longer have a price of their
 * own -- this exists so tests that only need "a known, correct price to
 * build a request payload/assertion with" don't each hand-roll a StockLot
 * lookup. $column accepts 'selling_price' (default) or 'cost_price'.
 */
function demoPriceFor(Item $item, string $column = 'selling_price'): string
{
    $lot = StockLot::where('item_id', $item->id)->fefo()->firstOrFail();

    return (string) $lot->{$column}->getAmount();
}
