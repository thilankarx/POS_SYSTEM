<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Settings\BusinessProfileSettings;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function show(BusinessProfileSettings $settings): JsonResponse
    {
        return response()->json([
            'business_type' => $settings->business_type,
        ]);
    }
}
