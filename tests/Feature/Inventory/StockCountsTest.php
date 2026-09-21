<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Actions\ApproveStockCountAction;
use App\Domain\Inventory\Actions\CancelStockCountAction;
use App\Domain\Inventory\Actions\CreateStockCountAction;
use App\Domain\Inventory\Actions\GenerateStockCountLinesAction;
use App\Domain\Inventory\Actions\RecordCountedQuantityAction;
use App\Domain\Inventory\Actions\SubmitStockCountForReviewAction;
use App\Domain\Inventory\Actions\UnapproveStockCountAction;
use App\Domain\Inventory\Exceptions\StockCountException;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockMovement;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->cola = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

it('creates a stock count in draft status', function () {
    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, true, 'Monthly count');

    expect($count->status)->toBe(StockCount::STATUS_DRAFT)
        ->and($count->is_blind)->toBeTrue()
        ->and($count->reference)->toStartWith('SC-');
});

it('generates lines snapshotting expected quantity and skips service items', function () {
    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);

    $count = app(GenerateStockCountLinesAction::class)->execute($count);

    $skuList = $count->lines->map(fn ($line) => $line->item->sku)->all();

    expect($skuList)->toContain('BEV-COLA-330')
        ->and($skuList)->not->toContain('SRV-DELIVERY') // service item, never stocked
        ->and($count->status)->toBe(StockCount::STATUS_COUNTING);

    $colaLine = $count->lines->firstWhere('item_id', $this->cola->id);
    expect((string) $colaLine->expected_quantity)->toBe($this->cola->quantityAt($this->location));
});

it('generates lines only for the selected items when a subset is given', function () {
    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);

    $count = app(GenerateStockCountLinesAction::class)->execute($count, [$this->cola->id]);

    expect($count->lines)->toHaveCount(1)
        ->and($count->lines->first()->item_id)->toBe($this->cola->id);
});

it('refuses to generate lines twice for a non-draft count', function () {
    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);
    app(GenerateStockCountLinesAction::class)->execute($count, [$this->cola->id]);

    app(GenerateStockCountLinesAction::class)->execute($count->fresh(), [$this->cola->id]);
})->throws(StockCountException::class, 'draft');

it('only records a counted quantity while the count is in progress', function () {
    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);
    $count = app(GenerateStockCountLinesAction::class)->execute($count, [$this->cola->id]);
    $line = $count->lines->first();

    app(RecordCountedQuantityAction::class)->execute($line, '100', $this->admin);

    expect((string) $line->fresh()->counted_quantity)->toBe('100.000')
        ->and($line->fresh()->counted_by_user_id)->toBe($this->admin->id);
});

it('refuses to submit a count with an uncounted line', function () {
    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);
    $count = app(GenerateStockCountLinesAction::class)->execute($count, [$this->cola->id]);

    app(SubmitStockCountForReviewAction::class)->execute($count);
})->throws(StockCountException::class, 'counted quantity');

it('computes variance on submit and moves to review', function () {
    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);
    $count = app(GenerateStockCountLinesAction::class)->execute($count, [$this->cola->id]);
    $line = $count->lines->first();
    $expected = (string) $line->expected_quantity;

    app(RecordCountedQuantityAction::class)->execute($line, bcadd($expected, '5', 3), $this->admin);

    $count = app(SubmitStockCountForReviewAction::class)->execute($count->fresh());

    expect($count->status)->toBe(StockCount::STATUS_REVIEW)
        ->and((string) $count->lines->first()->variance)->toBe('5.000');
});

it('approve posts ledger movements only for nonzero variance lines and updates stock levels', function () {
    $before = $this->cola->quantityAt($this->location);

    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);
    $count = app(GenerateStockCountLinesAction::class)->execute($count, [$this->cola->id]);
    $line = $count->lines->first();
    $expected = (string) $line->expected_quantity;

    // Counted 3 more than expected -> positive variance.
    app(RecordCountedQuantityAction::class)->execute($line, bcadd($expected, '3', 3), $this->admin);
    $count = app(SubmitStockCountForReviewAction::class)->execute($count->fresh());

    $count = app(ApproveStockCountAction::class)->execute($count, $this->admin);

    expect($count->status)->toBe(StockCount::STATUS_APPROVED)
        ->and($count->approved_by_user_id)->toBe($this->admin->id);

    $movement = StockMovement::where('source_type', $count->getMorphClass())
        ->where('source_id', $count->id)
        ->where('item_id', $this->cola->id)
        ->firstOrFail();

    expect((string) $movement->quantity_delta)->toBe('3.000')
        ->and($movement->reason)->toBe(StockMovement::REASON_COUNT);

    $after = $this->cola->fresh()->quantityAt($this->location);
    expect(bcsub($after, $before, 3))->toBe('3.000');
});

it('approve writes no ledger movement for a zero-variance line', function () {
    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);
    $count = app(GenerateStockCountLinesAction::class)->execute($count, [$this->cola->id]);
    $line = $count->lines->first();

    // Counted exactly what was expected -> zero variance.
    app(RecordCountedQuantityAction::class)->execute($line, (string) $line->expected_quantity, $this->admin);
    $count = app(SubmitStockCountForReviewAction::class)->execute($count->fresh());
    $count = app(ApproveStockCountAction::class)->execute($count, $this->admin);

    $movementCount = StockMovement::where('source_type', $count->getMorphClass())
        ->where('source_id', $count->id)
        ->count();

    expect($movementCount)->toBe(0);
});

it('refuses to approve a count that is not under review', function () {
    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);

    app(ApproveStockCountAction::class)->execute($count, $this->admin);
})->throws(StockCountException::class, 'review');

it('unapprove reverses ledger movements for nonzero variance lines and restores stock levels', function () {
    $before = $this->cola->quantityAt($this->location);

    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);
    $count = app(GenerateStockCountLinesAction::class)->execute($count, [$this->cola->id]);
    $line = $count->lines->first();
    $expected = (string) $line->expected_quantity;

    // Counted 3 more than expected -> positive variance.
    app(RecordCountedQuantityAction::class)->execute($line, bcadd($expected, '3', 3), $this->admin);
    $count = app(SubmitStockCountForReviewAction::class)->execute($count->fresh());
    $count = app(ApproveStockCountAction::class)->execute($count, $this->admin);

    $count = app(UnapproveStockCountAction::class)->execute($count, $this->admin);

    expect($count->status)->toBe(StockCount::STATUS_REVIEW)
        ->and($count->unapproved_by_user_id)->toBe($this->admin->id);

    $movements = StockMovement::where('source_type', $count->getMorphClass())
        ->where('source_id', $count->id)
        ->where('item_id', $this->cola->id)
        ->orderBy('id')
        ->get();

    expect($movements)->toHaveCount(2)
        ->and((string) $movements->last()->quantity_delta)->toBe('-3.000')
        ->and($movements->last()->reason)->toBe(StockMovement::REASON_COUNT);

    $after = $this->cola->fresh()->quantityAt($this->location);
    expect(bcsub($after, $before, 3))->toBe('0.000');
});

it('unapprove writes no reversal movement for a zero-variance line', function () {
    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);
    $count = app(GenerateStockCountLinesAction::class)->execute($count, [$this->cola->id]);
    $line = $count->lines->first();

    app(RecordCountedQuantityAction::class)->execute($line, (string) $line->expected_quantity, $this->admin);
    $count = app(SubmitStockCountForReviewAction::class)->execute($count->fresh());
    $count = app(ApproveStockCountAction::class)->execute($count, $this->admin);

    app(UnapproveStockCountAction::class)->execute($count, $this->admin);

    $movementCount = StockMovement::where('source_type', $count->getMorphClass())
        ->where('source_id', $count->id)
        ->count();

    expect($movementCount)->toBe(0);
});

it('refuses to unapprove a count that is not approved', function () {
    $count = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);
    $count = app(GenerateStockCountLinesAction::class)->execute($count, [$this->cola->id]);
    $line = $count->lines->first();
    app(RecordCountedQuantityAction::class)->execute($line, (string) $line->expected_quantity, $this->admin);
    $count = app(SubmitStockCountForReviewAction::class)->execute($count->fresh());

    app(UnapproveStockCountAction::class)->execute($count, $this->admin);
})->throws(StockCountException::class, 'approved');

it('cancels a count from draft, counting or review but not from approved', function () {
    $draft = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);
    expect(app(CancelStockCountAction::class)->execute($draft)->status)->toBe(StockCount::STATUS_CANCELLED);

    $counting = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);
    $counting = app(GenerateStockCountLinesAction::class)->execute($counting, [$this->cola->id]);
    expect(app(CancelStockCountAction::class)->execute($counting)->status)->toBe(StockCount::STATUS_CANCELLED);

    $approved = app(CreateStockCountAction::class)->execute($this->location, $this->admin, false);
    $approved = app(GenerateStockCountLinesAction::class)->execute($approved, [$this->cola->id]);
    $line = $approved->lines->first();
    app(RecordCountedQuantityAction::class)->execute($line, (string) $line->expected_quantity, $this->admin);
    $approved = app(SubmitStockCountForReviewAction::class)->execute($approved->fresh());
    $approved = app(ApproveStockCountAction::class)->execute($approved, $this->admin);

    app(CancelStockCountAction::class)->execute($approved);
})->throws(StockCountException::class, 'already');
