<?php

return [
    /*
     * Base currency. All stored money columns are decimal(19,4) in this
     * currency; there is no implicit conversion anywhere in the domain.
     */
    'currency' => env('POS_CURRENCY', 'USD'),

    /*
     * Local timezone used when displaying business dates and times. Database
     * timestamps remain in UTC so changing this setting cannot corrupt or
     * reinterpret historical sales.
     */
    'timezone' => env('POS_TIMEZONE', config('app.timezone', 'UTC')),

    /*
     * Scale used for intermediate money arithmetic before the final rounding
     * step. Kept above the currency's minor unit so a chain of line-level
     * calculations does not accumulate rounding error.
     */
    'calculation_scale' => 4,

    'cash' => [
        // Smallest cash denomination; totals paid in cash round to it.
        // 0 disables cash rounding entirely.
        'rounding_increment' => env('POS_CASH_ROUNDING', 0),
        // LKR notes (5000/1000/500/100/50/20) and coins (10/5/2/1) currently
        // in circulation — the close-shift cash count is denominated in the
        // configured base currency (POS_CURRENCY), so this list needs to
        // match it rather than a generic/USD note set.
        'denominations' => [5000, 1000, 500, 100, 50, 20, 10, 5, 2, 1],
    ],

    'documents' => [
        'sequences' => [
            'sale' => ['prefix' => 'POS', 'padding' => 6],
            'invoice' => ['prefix' => 'INV', 'padding' => 6],
            'quote' => ['prefix' => 'QUO', 'padding' => 6],
            'work_order' => ['prefix' => 'WO', 'padding' => 6],
            'receiving' => ['prefix' => 'RCV', 'padding' => 6],
            'purchase_order' => ['prefix' => 'PO', 'padding' => 6],
            'stock_count' => ['prefix' => 'SC', 'padding' => 6],
            'gift_card' => ['prefix' => 'GC', 'padding' => 6],
            // Empty prefix: format() joins [prefix, scope, number] and drops
            // falsy parts, so with the category code as scope this yields
            // "{CATEGORY_CODE}-000001" rather than double-prefixing it.
            'item_sku' => ['prefix' => '', 'padding' => 6],
        ],
    ],

    'idempotency' => [
        'ttl_hours' => 48,
    ],

    /*
     * Per-user request ceiling for the JSON API (the 'api' RateLimiter in
     * AppServiceProvider), a minute window. The register is chatty by
     * design -- a single scan is a barcode lookup plus a queued add-line
     * drain, and a fast cashier bursts well past the old value of 120 --
     * so this has to sit above real peak scanning, not average load. It is
     * still a per-token brake on a stolen/shared credential enumerating the
     * catalogue. Raise via env for unusually fast lanes.
     */
    'api_rate_limit' => (int) env('POS_API_RATE_LIMIT', 300),

    'purchasing' => [
        // Max difference between a supplier invoice's total and what was
        // actually received against its PO before it's flagged disputed.
        'match_tolerance' => '0.01',
    ],
];
