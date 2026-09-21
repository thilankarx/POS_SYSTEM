<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Models\Sale;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;

    /**
     * Role/permission assignment is a spatie/laravel-permission pivot-table
     * change, not an attribute on this model, so it is not picked up by
     * logOnly()'s dirty-attribute tracking below -- it is logged explicitly
     * at the one place a role is ever changed, Identity\Users\Form::save().
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected $guarded = ['id'];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'commission_rate' => 'decimal:2',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Locations this user may operate in. Replaces the OSPOS scheme of encoding
     * the location into the permission id by name.
     */
    public function stockLocations(): BelongsToMany
    {
        return $this->belongsToMany(StockLocation::class)->withTimestamps();
    }

    /** Sales this user took as the waiter -- see Cart::waiter()/Sale::waiter(). */
    public function waiterSales(): HasMany
    {
        return $this->hasMany(Sale::class, 'waiter_id');
    }

    public function canOperateAt(StockLocation|int $location): bool
    {
        $id = $location instanceof StockLocation ? $location->id : $location;

        return $this->stockLocations()->whereKey($id)->exists();
    }

    public function getNameAttribute(): string
    {
        return $this->person?->full_name ?? $this->username;
    }
}
