<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class LabelSettings extends Settings
{
    public int $width_mm;

    public int $height_mm;

    public int $gap_mm;

    public int $density;

    public static function group(): string
    {
        return 'labels';
    }
}
