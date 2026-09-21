<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemKit extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['discount_value' => 'decimal:4'];
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_kit_items')
            ->withPivot(['quantity', 'sequence'])
            ->withTimestamps()
            ->orderByPivot('sequence');
    }

    /**
     * Restrict to kits every component of which is sold by the given
     * business type (Item::scopeForBusinessType()'s counterpart) -- a kit
     * has no business_types of its own, so it's excluded once any of its
     * components is explicitly restricted to a different type. A kit whose
     * components are all unrestricted, or all include $type, still passes.
     */
    public function scopeForBusinessType(Builder $query, ?string $type): Builder
    {
        if ($type === null || $type === '') {
            return $query;
        }

        return $query->whereDoesntHave('items', function (Builder $inner) use ($type) {
            $inner->whereNotNull('business_types')
                ->whereJsonLength('business_types', '>', 0)
                ->whereJsonDoesntContain('business_types', $type);
        });
    }
}
