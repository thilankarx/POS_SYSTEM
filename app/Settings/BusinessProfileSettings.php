<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BusinessProfileSettings extends Settings
{
    public const BUSINESS_TYPE_RETAIL = 'retail';

    public const BUSINESS_TYPE_HARDWARE = 'hardware';

    public const BUSINESS_TYPE_RESTAURANT = 'restaurant';

    /** @return list<string> every valid business type, for validation and pickers */
    public static function businessTypes(): array
    {
        return [
            self::BUSINESS_TYPE_RETAIL,
            self::BUSINESS_TYPE_HARDWARE,
            self::BUSINESS_TYPE_RESTAURANT,
        ];
    }

    public string $store_name;

    public string $address;

    public string $phone;

    public ?string $logo_path;

    public string $receipt_header;

    public string $receipt_footer;

    public string $business_type;

    public static function group(): string
    {
        return 'business_profile';
    }
}
