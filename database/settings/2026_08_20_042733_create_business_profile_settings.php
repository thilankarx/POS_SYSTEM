<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('business_profile.store_name', config('app.name'));
        $this->migrator->add('business_profile.address', '');
        $this->migrator->add('business_profile.phone', '');
    }
};
