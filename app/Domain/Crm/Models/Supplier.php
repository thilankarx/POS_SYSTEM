<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
