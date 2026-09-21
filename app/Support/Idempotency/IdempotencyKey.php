<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class IdempotencyKey extends Model
{
    use Prunable;

    protected $table = 'idempotency_keys';

    protected $guarded = ['id'];

    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now());
    }

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
