<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Actions\ApprovePurchaseOrderAction;
use App\Domain\Purchasing\Actions\CancelPurchaseOrderAction;
use App\Domain\Purchasing\Actions\CreatePurchaseOrderAction;
use App\Domain\Purchasing\Actions\SubmitPurchaseOrderAction;
use App\Domain\Purchasing\Actions\UpdatePurchaseOrderLinesAction;
use App\Domain\Purchasing\Exceptions\PurchasingException;
use App\Domain\Purchasing\Models\PurchaseOrder;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

function createDraftPo(): PurchaseOrder
{
    return app(CreatePurchaseOrderAction::class)->execute(
        supplier: test()->supplier,
        location: test()->location,
        creator: test()->admin,
        lines: [['item_id' => test()->item->id, 'quantity_ordered' => '10', 'unit_cost' => '0.80']],
    );
}

it('creates a purchase order as a draft with computed totals', function () {
    $po = createDraftPo();

    expect($po->status)->toBe(PurchaseOrder::STATUS_DRAFT)
        ->and($po->lines)->toHaveCount(1)
        ->and((string) $po->subtotal->getAmount())->toBe('8.00')
        ->and((string) $po->tax_total->getAmount())->toBe('1.20')
        ->and((string) $po->total->getAmount())->toBe('9.20')
        ->and($po->number)->not->toBeEmpty();
});

it('allows editing lines while draft', function () {
    $po = createDraftPo();

    $updated = app(UpdatePurchaseOrderLinesAction::class)->execute($po, [
        ['item_id' => $this->item->id, 'quantity_ordered' => '20', 'unit_cost' => '0.80'],
    ]);

    expect((string) $updated->subtotal->getAmount())->toBe('16.00')
        ->and($updated->lines)->toHaveCount(1)
        ->and((string) $updated->lines->first()->quantity_ordered)->toBe('20.000');
});

it('rejects editing lines once submitted', function () {
    $po = createDraftPo();
    app(SubmitPurchaseOrderAction::class)->execute($po);

    app(UpdatePurchaseOrderLinesAction::class)->execute($po, [
        ['item_id' => $this->item->id, 'quantity_ordered' => '5', 'unit_cost' => '0.80'],
    ]);
})->throws(PurchasingException::class);

it('moves through submit then approve', function () {
    $po = createDraftPo();

    app(SubmitPurchaseOrderAction::class)->execute($po);
    expect($po->fresh()->status)->toBe(PurchaseOrder::STATUS_SUBMITTED);

    app(ApprovePurchaseOrderAction::class)->execute($po, $this->admin);
    $fresh = $po->fresh();
    expect($fresh->status)->toBe(PurchaseOrder::STATUS_APPROVED)
        ->and($fresh->approved_by_user_id)->toBe($this->admin->id)
        ->and($fresh->approved_at)->not->toBeNull();
});

it('rejects approving a purchase order that is still a draft', function () {
    $po = createDraftPo();

    app(ApprovePurchaseOrderAction::class)->execute($po, $this->admin);
})->throws(PurchasingException::class);

it('cancels a draft or submitted purchase order', function () {
    $po = createDraftPo();
    app(CancelPurchaseOrderAction::class)->execute($po);

    expect($po->fresh()->status)->toBe(PurchaseOrder::STATUS_CANCELLED);
});

it('rejects cancelling an approved purchase order', function () {
    $po = createDraftPo();
    app(SubmitPurchaseOrderAction::class)->execute($po);
    app(ApprovePurchaseOrderAction::class)->execute($po, $this->admin);

    app(CancelPurchaseOrderAction::class)->execute($po);
})->throws(PurchasingException::class);
