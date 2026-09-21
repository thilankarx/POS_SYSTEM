<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Crm\Models\Customer;
use App\Domain\Crm\Models\Supplier;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The shared identity behind employees, customers and suppliers. Carried over
 * from OSPOS, where one person genuinely can be all three.
 */
class Person extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected static function newFactory(): PersonFactory
    {
        return PersonFactory::new();
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
