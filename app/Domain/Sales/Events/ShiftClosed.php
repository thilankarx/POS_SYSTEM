<?php

declare(strict_types=1);

namespace App\Domain\Sales\Events;

use App\Domain\Sales\Models\Shift;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ShiftClosed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Shift $shift) {}
}
