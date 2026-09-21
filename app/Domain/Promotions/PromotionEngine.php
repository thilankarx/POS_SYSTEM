<?php

declare(strict_types=1);

namespace App\Domain\Promotions;

use App\Domain\Promotions\Data\AppliedPromotion;
use App\Domain\Promotions\Data\PromotionEvaluation;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionCondition;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Support\Money\Money as MoneySupport;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Collection;

/**
 * Pure computation over already-loaded promotion candidates: no DB queries.
 * Condition membership (item/category) inherently tests already-loaded cart
 * line/item relations, so — unlike TaxEngine — this deliberately does not
 * flatten Cart/CartLine into plain structs first; the purity boundary that
 * matters (no queries during computation) is preserved without it.
 */
final class PromotionEngine
{
    /**
     * @param  Collection<int, Promotion>  $candidates
     * @param  array<int, array{line: CartLine, gross: Money, discount: Money, net: Money, cost: Money}>  $working  keyed by line_number
     */
    public function evaluate(Collection $candidates, Cart $cart, array $working, ?string $couponCode): PromotionEvaluation
    {
        $eligible = $candidates
            ->map(fn (Promotion $promotion) => [$promotion, $this->scopeFor($promotion, $working)])
            ->filter(fn (array $pair) => $pair[1] !== [] && $this->passesCartLevelConditions($pair[0], $cart, $working, $pair[1]));

        $selected = $this->selectForStacking($eligible)
            ->sort(fn (array $a, array $b) => ($b[0]->priority <=> $a[0]->priority) ?: ($a[0]->id <=> $b[0]->id))
            ->values();

        $netByLine = array_map(fn (array $parts) => $parts['net'], $working);
        $perLineDiscount = array_fill_keys(array_keys($working), MoneySupport::zero());
        $promotionIdsByLine = array_fill_keys(array_keys($working), []);
        $applied = [];

        foreach ($selected as [$promotion, $scope]) {
            if (in_array($promotion->reward_type, [Promotion::REWARD_BOGO, Promotion::REWARD_FREE_ITEM], true)) {
                $reward = $this->applyUnitReward($promotion, $scope, $working, $netByLine);

                if ($reward === null) {
                    continue;
                }

                [$lineNumber, $discount] = $reward;

                $netByLine[$lineNumber] = $netByLine[$lineNumber]->minus($discount);
                $perLineDiscount[$lineNumber] = $perLineDiscount[$lineNumber]->plus($discount);
                $promotionIdsByLine[$lineNumber][] = $promotion->id;

                $applied[] = new AppliedPromotion(
                    promotionId: $promotion->id,
                    couponId: $promotion->requires_coupon ? $this->matchedCouponId($promotion, $couponCode) : null,
                    discount: $discount,
                );

                continue;
            }

            $rewardBase = $this->sumNet($scope, $netByLine);

            if ($rewardBase->isZero()) {
                continue;
            }

            $rawDiscount = $this->rawDiscount($promotion, $rewardBase);

            if (! $rawDiscount->isPositive()) {
                continue;
            }

            $lineDiscounts = $this->apportion($rawDiscount, $scope, $netByLine);
            $totalApplied = MoneySupport::zero();

            foreach ($lineDiscounts as $lineNumber => $discount) {
                $netByLine[$lineNumber] = $netByLine[$lineNumber]->minus($discount);
                $perLineDiscount[$lineNumber] = $perLineDiscount[$lineNumber]->plus($discount);
                $promotionIdsByLine[$lineNumber][] = $promotion->id;
                $totalApplied = $totalApplied->plus($discount);
            }

            $applied[] = new AppliedPromotion(
                promotionId: $promotion->id,
                couponId: $promotion->requires_coupon ? $this->matchedCouponId($promotion, $couponCode) : null,
                discount: $totalApplied,
            );
        }

        return new PromotionEvaluation($perLineDiscount, $promotionIdsByLine, $applied);
    }

    /** @return list<int> line numbers */
    private function scopeFor(Promotion $promotion, array $working): array
    {
        $itemCategoryConditions = $promotion->conditions->filter(
            fn (PromotionCondition $c) => in_array($c->subject, [PromotionCondition::SUBJECT_ITEM, PromotionCondition::SUBJECT_CATEGORY], true)
        );

        if ($itemCategoryConditions->isEmpty()) {
            return array_keys($working);
        }

        $scope = array_keys($working);

        foreach ($itemCategoryConditions as $condition) {
            $scope = array_values(array_filter(
                $scope,
                fn (int $lineNumber) => $this->matchesItemOrCategory($condition, $working[$lineNumber])
            ));
        }

        return $scope;
    }

    /**
     * bogo/free_item discount exactly one unit on a single target line,
     * unlike the other reward types which spread a scalar discount
     * pro-rata across every line in scope.
     *
     * @param  list<int>  $scope
     * @param  array<int, array{line: CartLine, gross: Money, discount: Money, net: Money, cost: Money}>  $working
     * @param  array<int, Money>  $netByLine
     * @return array{0: int, 1: Money}|null
     */
    private function applyUnitReward(Promotion $promotion, array $scope, array $working, array $netByLine): ?array
    {
        $lineNumber = $promotion->reward_type === Promotion::REWARD_FREE_ITEM
            ? $this->lineForItem($working, $promotion->reward_item_id)
            : $this->cheapestUnitLine($scope, $working, $netByLine);

        if ($lineNumber === null) {
            return null;
        }

        $discount = $this->unitNet($working[$lineNumber]['line'], $netByLine[$lineNumber]);

        if (! $discount->isPositive()) {
            return null;
        }

        return [$lineNumber, $discount];
    }

    /** @param  array<int, array{line: CartLine, gross: Money, discount: Money, net: Money, cost: Money}>  $working */
    private function lineForItem(array $working, ?int $itemId): ?int
    {
        if ($itemId === null) {
            return null;
        }

        foreach ($working as $lineNumber => $parts) {
            if ((int) $parts['line']->item_id === $itemId) {
                return $lineNumber;
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $scope
     * @param  array<int, array{line: CartLine, gross: Money, discount: Money, net: Money, cost: Money}>  $working
     * @param  array<int, Money>  $netByLine
     */
    private function cheapestUnitLine(array $scope, array $working, array $netByLine): ?int
    {
        $cheapestLine = null;
        $cheapestUnit = null;

        foreach ($scope as $lineNumber) {
            $unit = $this->unitNet($working[$lineNumber]['line'], $netByLine[$lineNumber]);

            if ($cheapestUnit === null || $unit->isLessThan($cheapestUnit)) {
                $cheapestLine = $lineNumber;
                $cheapestUnit = $unit;
            }
        }

        return $cheapestLine;
    }

    private function unitNet(CartLine $line, Money $net): Money
    {
        $quantity = (string) $line->quantity;

        if (bccomp($quantity, '0', 3) === 0) {
            return MoneySupport::zero();
        }

        return $this->min($net, $net->dividedBy($quantity, RoundingMode::HalfUp));
    }

    private function matchesItemOrCategory(PromotionCondition $condition, array $lineParts): bool
    {
        $item = $lineParts['line']->item;

        $subjectValue = $condition->subject === PromotionCondition::SUBJECT_ITEM
            ? (int) $item->id
            : $item->category_id;

        $list = array_map('intval', is_array($condition->value) ? $condition->value : []);
        $inList = $subjectValue !== null && in_array((int) $subjectValue, $list, true);

        return $condition->operator === PromotionCondition::OPERATOR_NOT_IN ? ! $inList : $inList;
    }

    private function passesCartLevelConditions(Promotion $promotion, Cart $cart, array $working, array $scope): bool
    {
        foreach ($promotion->conditions as $condition) {
            if (in_array($condition->subject, [PromotionCondition::SUBJECT_ITEM, PromotionCondition::SUBJECT_CATEGORY], true)) {
                continue;
            }

            $passes = match ($condition->subject) {
                PromotionCondition::SUBJECT_CUSTOMER_GROUP => $this->matchesCustomerGroup($condition, $cart),
                PromotionCondition::SUBJECT_CART_TOTAL => $this->matchesCartTotal($condition, $working),
                PromotionCondition::SUBJECT_QUANTITY => $this->matchesQuantity($condition, $working, $scope),
                default => true,
            };

            if (! $passes) {
                return false;
            }
        }

        return true;
    }

    private function matchesCustomerGroup(PromotionCondition $condition, Cart $cart): bool
    {
        $groupId = $cart->customer?->loyalty_package_id;

        if ($groupId === null) {
            return false;
        }

        $list = array_map('intval', is_array($condition->value) ? $condition->value : []);
        $inList = in_array((int) $groupId, $list, true);

        return $condition->operator === PromotionCondition::OPERATOR_NOT_IN ? ! $inList : $inList;
    }

    private function matchesCartTotal(PromotionCondition $condition, array $working): bool
    {
        $total = $this->sumNet(array_keys($working), array_map(fn (array $parts) => $parts['net'], $working));
        $threshold = MoneySupport::of($this->scalarValue($condition));

        return match ($condition->operator) {
            PromotionCondition::OPERATOR_GTE => $total->isGreaterThanOrEqualTo($threshold),
            PromotionCondition::OPERATOR_LTE => $total->isLessThanOrEqualTo($threshold),
            PromotionCondition::OPERATOR_EQ => $total->isEqualTo($threshold),
            default => false,
        };
    }

    private function matchesQuantity(PromotionCondition $condition, array $working, array $scope): bool
    {
        $quantity = '0';

        foreach ($scope as $lineNumber) {
            $quantity = bcadd($quantity, (string) $working[$lineNumber]['line']->quantity, 3);
        }

        $expected = $this->scalarValue($condition);
        $cmp = bccomp($quantity, $expected, 3);

        return match ($condition->operator) {
            PromotionCondition::OPERATOR_GTE => $cmp >= 0,
            PromotionCondition::OPERATOR_LTE => $cmp <= 0,
            PromotionCondition::OPERATOR_EQ => $cmp === 0,
            default => false,
        };
    }

    private function scalarValue(PromotionCondition $condition): string
    {
        $value = $condition->value;

        if (is_array($value)) {
            $value = $value[0] ?? '0';
        }

        return (string) $value;
    }

    /** @return Collection<int, array{0: Promotion, 1: list<int>}> */
    private function selectForStacking(Collection $eligible): Collection
    {
        $stackable = $eligible->filter(fn (array $pair) => $pair[0]->stackable);
        $nonStackable = $eligible->reject(fn (array $pair) => $pair[0]->stackable);

        if ($nonStackable->isEmpty()) {
            return $stackable->values();
        }

        $best = $nonStackable
            ->sort(fn (array $a, array $b) => ($b[0]->priority <=> $a[0]->priority) ?: ($a[0]->id <=> $b[0]->id))
            ->first();

        return $stackable->push($best)->values();
    }

    private function rawDiscount(Promotion $promotion, Money $rewardBase): Money
    {
        return match ($promotion->reward_type) {
            Promotion::REWARD_PERCENT_OFF => MoneySupport::percentageOf($rewardBase, (string) $promotion->reward_value->getAmount()),
            Promotion::REWARD_AMOUNT_OFF => $this->min($rewardBase, MoneySupport::of($promotion->reward_value)),
            Promotion::REWARD_FIXED_PRICE => $this->floorAtZero($rewardBase->minus(MoneySupport::of($promotion->reward_value))),
            default => MoneySupport::zero(),
        };
    }

    /** @param  list<int>  $scopeLineNumbers  @param  array<int, Money>  $netByLine  @return array<int, Money> */
    private function apportion(Money $rawDiscount, array $scopeLineNumbers, array $netByLine): array
    {
        $rewardBase = $this->sumNet($scopeLineNumbers, $netByLine);

        if ($rewardBase->isZero()) {
            return array_fill_keys($scopeLineNumbers, MoneySupport::zero());
        }

        $result = [];
        $allocated = MoneySupport::zero();
        $last = $scopeLineNumbers[array_key_last($scopeLineNumbers)];

        foreach ($scopeLineNumbers as $lineNumber) {
            if ($lineNumber === $last) {
                continue;
            }

            $ratio = bcdiv((string) $netByLine[$lineNumber]->getAmount(), (string) $rewardBase->getAmount(), 10);
            $share = $rawDiscount->multipliedBy($ratio, RoundingMode::HalfUp);
            $share = $this->min($share, $netByLine[$lineNumber]);

            $result[$lineNumber] = $share;
            $allocated = $allocated->plus($share);
        }

        $remainder = $rawDiscount->minus($allocated);
        $remainder = $this->floorAtZero($remainder);
        $result[$last] = $this->min($remainder, $netByLine[$last]);

        return $result;
    }

    private function sumNet(array $lineNumbers, array $netByLine): Money
    {
        $sum = MoneySupport::zero();

        foreach ($lineNumbers as $lineNumber) {
            $sum = $sum->plus($netByLine[$lineNumber]);
        }

        return $sum;
    }

    private function matchedCouponId(Promotion $promotion, ?string $couponCode): ?int
    {
        if ($couponCode === null || $couponCode === '') {
            return null;
        }

        $coupon = $promotion->coupons->firstWhere('code', $couponCode);

        return $coupon?->id;
    }

    private function min(Money $a, Money $b): Money
    {
        return $a->isLessThan($b) ? $a : $b;
    }

    private function floorAtZero(Money $money): Money
    {
        return $money->isNegative() ? MoneySupport::zero() : $money;
    }
}
