<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class TaxSettings extends Settings
{
    public bool $prices_include_tax;

    public static function group(): string
    {
        return 'tax';
    }
}
