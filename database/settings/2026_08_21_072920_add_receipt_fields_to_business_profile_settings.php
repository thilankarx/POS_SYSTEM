<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('business_profile.logo_path', null);
        $this->migrator->add('business_profile.receipt_header', '');
        $this->migrator->add('business_profile.receipt_footer', 'Thank you');
    }
};
