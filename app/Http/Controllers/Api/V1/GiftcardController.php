<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Giftcards\Models\Giftcard;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\GiftcardBalanceResource;

class GiftcardController extends Controller
{
    public function showByNumber(string $number): GiftcardBalanceResource
    {
        $giftcard = Giftcard::where('number', $number)->first();

        abort_if($giftcard === null, 404, 'No gift card found with that number.');

        return new GiftcardBalanceResource($giftcard);
    }
}
