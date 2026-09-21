<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeValue extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value_date' => 'date',
            'value_decimal' => 'decimal:4',
            'value_boolean' => 'boolean',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(AttributeDefinition::class, 'attribute_definition_id');
    }

    public function display(): string
    {
        return match (true) {
            $this->value_text !== null => $this->value_text,
            $this->value_date !== null => $this->value_date->toDateString(),
            $this->value_decimal !== null => (string) $this->value_decimal,
            $this->value_boolean !== null => $this->value_boolean ? '1' : '0',
            default => '',
        };
    }
}
