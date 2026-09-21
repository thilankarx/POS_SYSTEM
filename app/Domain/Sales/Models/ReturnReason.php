<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnReason extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'restocks' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
