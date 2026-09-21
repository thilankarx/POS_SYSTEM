<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exceptions;

use RuntimeException;

final class StockCountException extends RuntimeException
{
    public static function notDraft(): self
    {
        return new self('Lines can only be generated for a draft stock count.');
    }

    public static function notCounting(): self
    {
        return new self('Counts can only be recorded while the stock count is in progress.');
    }

    public static function notReview(): self
    {
        return new self('A stock count can only be approved once it is under review.');
    }

    public static function incompleteLines(): self
    {
        return new self('Every line must have a counted quantity before submitting for review.');
    }

    public static function alreadyClosed(): self
    {
        return new self('This stock count is already approved or cancelled.');
    }

    public static function notApproved(): self
    {
        return new self('A stock count can only be unapproved once it has been approved.');
    }

    public static function noLinesGenerated(): self
    {
        return new self('No items matched the selection for this stock count.');
    }
}
