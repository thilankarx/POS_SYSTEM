<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Actions;

use App\Domain\Promotions\Exceptions\PromotionException;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionCondition;
use Illuminate\Support\Facades\DB;

final class CreatePromotionAction
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{subject: string, operator: string, value: mixed}>  $conditions
     */
    public function execute(array $attributes, array $conditions): Promotion
    {
        if ($conditions === []) {
            throw PromotionException::emptyConditions();
        }

        $this->assertValidRewardConfiguration($attributes, $conditions);

        return DB::transaction(function () use ($attributes, $conditions) {
            $promotion = Promotion::create($attributes);

            $this->writeConditions($promotion, $conditions);

            return $promotion->fresh('conditions');
        });
    }

    /**
     * @param  array<int, array{subject: string, operator: string, value: mixed}>  $conditions
     */
    public function writeConditions(Promotion $promotion, array $conditions): void
    {
        foreach ($conditions as $condition) {
            $promotion->conditions()->create([
                'subject' => $condition['subject'],
                'operator' => $condition['operator'],
                'value' => $condition['value'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{subject: string, operator: string, value: mixed}>  $conditions
     */
    public function assertValidRewardConfiguration(array $attributes, array $conditions): void
    {
        if (($attributes['reward_type'] ?? null) === Promotion::REWARD_FREE_ITEM && empty($attributes['reward_item_id'])) {
            throw PromotionException::freeItemRequiresRewardItem();
        }

        if (($attributes['reward_type'] ?? null) === Promotion::REWARD_BOGO) {
            $hasQuantityFloor = collect($conditions)->contains(
                fn (array $condition) => $condition['subject'] === PromotionCondition::SUBJECT_QUANTITY
                    && in_array($condition['operator'], [PromotionCondition::OPERATOR_GTE, PromotionCondition::OPERATOR_EQ], true)
            );

            if (! $hasQuantityFloor) {
                throw PromotionException::bogoRequiresQuantityCondition();
            }
        }
    }
}
