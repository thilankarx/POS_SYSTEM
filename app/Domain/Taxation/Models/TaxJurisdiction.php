<?php

declare(strict_types=1);

namespace App\Domain\Taxation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reserved for destination-based taxation. Unused while the engine runs in
 * single-rate VAT/GST mode, but present so switching modes is configuration
 * rather than a migration.
 */
class TaxJurisdiction extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }
}
