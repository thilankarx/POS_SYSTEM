<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Defaults match this project's prior config/pos.php defaults
        // before these settings became editable.
        $this->migrator->add('labels.width_mm', (int) env('POS_LABEL_WIDTH_MM', 50));
        $this->migrator->add('labels.height_mm', (int) env('POS_LABEL_HEIGHT_MM', 30));
        $this->migrator->add('labels.gap_mm', (int) env('POS_LABEL_GAP_MM', 2));
        $this->migrator->add('labels.density', (int) env('POS_LABEL_DENSITY', 8));
    }
};
