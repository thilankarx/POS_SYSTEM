<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Documents\DocumentNumberGenerator;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Inventory\Models\StockLocation;

final class CreateStockCountAction
{
    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    public function execute(StockLocation $location, User $user, bool $isBlind, ?string $note = null): StockCount
    {
        return StockCount::create([
            'reference' => $this->numbers->next('stock_count'),
            'stock_location_id' => $location->id,
            'status' => StockCount::STATUS_DRAFT,
            'is_blind' => $isBlind,
            'opened_by_user_id' => $user->id,
            'opened_at' => now(),
            'note' => $note,
        ]);
    }
}
