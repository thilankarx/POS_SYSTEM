<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Defaults match this project's prior config/pos.php default
        // (exclusive pricing) before this setting became editable.
        $this->migrator->add('tax.prices_include_tax', (bool) env('POS_PRICES_INCLUDE_TAX', false));
    }
};
