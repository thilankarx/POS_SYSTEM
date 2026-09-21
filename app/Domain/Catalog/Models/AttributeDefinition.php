<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttributeDefinition extends Model
{
    use SoftDeletes;

    public const TYPE_TEXT = 'text';

    public const TYPE_DROPDOWN = 'dropdown';

    public const TYPE_DECIMAL = 'decimal';

    public const TYPE_DATE = 'date';

    public const TYPE_CHECKBOX = 'checkbox';

    public const TYPE_GROUP = 'group';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'show_in_receipt' => 'boolean',
            'show_in_search' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }
}
