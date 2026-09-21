<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Actions;

use App\Domain\Promotions\Exceptions\PromotionException;
use App\Domain\Promotions\Models\Promotion;
use Illuminate\Support\Facades\DB;

final class UpdatePromotionAction
{
    public function __construct(private readonly CreatePromotionAction $creator) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{subject: string, operator: string, value: mixed}>  $conditions
     */
    public function execute(Promotion $promotion, array $attributes, array $conditions): Promotion
    {
        if ($conditions === []) {
            throw PromotionException::emptyConditions();
        }

        $this->creator->assertValidRewardConfiguration($attributes, $conditions);

        return DB::transaction(function () use ($promotion, $attributes, $conditions) {
            $promotion->update($attributes);
            $promotion->conditions()->delete();
            $this->creator->writeConditions($promotion, $conditions);

            return $promotion->fresh('conditions');
        });
    }
}
