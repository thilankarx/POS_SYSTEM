<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionCondition extends Model
{
    public const SUBJECT_ITEM = 'item';

    public const SUBJECT_CATEGORY = 'category';

    public const SUBJECT_CUSTOMER_GROUP = 'customer_group';

    public const SUBJECT_CART_TOTAL = 'cart_total';

    public const SUBJECT_QUANTITY = 'quantity';

    public const OPERATOR_IN = 'in';

    public const OPERATOR_NOT_IN = 'not_in';

    public const OPERATOR_GTE = 'gte';

    public const OPERATOR_LTE = 'lte';

    public const OPERATOR_EQ = 'eq';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
