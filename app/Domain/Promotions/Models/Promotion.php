<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Models;

use App\Domain\Catalog\Models\Item;
use App\Support\Money\MoneyCast;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A set of conditions plus a reward, evaluated by CartPricer alongside tax.
 */
class Promotion extends Model
{
    use SoftDeletes;

    public const REWARD_PERCENT_OFF = 'percent_off';

    public const REWARD_AMOUNT_OFF = 'amount_off';

    public const REWARD_FIXED_PRICE = 'fixed_price';

    public const REWARD_BOGO = 'bogo';

    public const REWARD_FREE_ITEM = 'free_item';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reward_value' => MoneyCast::class,
            'requires_coupon' => 'boolean',
            'stackable' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active_from' => 'datetime:H:i:s',
            'active_to' => 'datetime:H:i:s',
        ];
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(PromotionCondition::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    public function rewardItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'reward_item_id');
    }

    public function isCurrentlyActive(CarbonInterface $at): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // starts_at/ends_at are absolute instants (stored in UTC): a Carbon
        // comparison is correct regardless of $at's timezone, no conversion
        // needed here.
        if ($this->starts_at !== null && $at->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && $at->gt($this->ends_at)) {
            return false;
        }

        // active_days/active_from/active_to describe a recurring LOCAL
        // business-hours schedule ("open Mon-Sat, 9am-6pm") -- that concept
        // only makes sense evaluated in the store's own timezone, not UTC,
        // so $at is converted to config('pos.timezone') before extracting
        // its weekday/time-of-day.
        $local = $at->copy()->timezone(config('pos.timezone'));

        if ($this->active_days !== null) {
            $days = array_map('trim', explode(',', $this->active_days));
            if (! in_array((string) $local->isoWeekday(), $days, true)) {
                return false;
            }
        }

        $time = $local->format('H:i:s');

        if ($this->active_from !== null && $time < $this->active_from->format('H:i:s')) {
            return false;
        }

        if ($this->active_to !== null && $time > $this->active_to->format('H:i:s')) {
            return false;
        }

        return true;
    }

    public function hasRedemptionsRemaining(): bool
    {
        return $this->max_redemptions === null || $this->redemption_count < $this->max_redemptions;
    }
}
