<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\BusinessProfileSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route to one or more business types (the BusinessProfile setting).
 * A store that is not one of the listed types gets a 404 -- the feature
 * simply does not exist for it, e.g. dinner-table management outside a
 * restaurant. Runs after the permission middleware, so a user who also
 * lacks the permission still sees the 403 first.
 *
 * Usage: ->middleware('business.type:restaurant')
 */
class EnsureBusinessType
{
    public function __construct(private readonly BusinessProfileSettings $settings) {}

    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        abort_unless(in_array($this->settings->business_type, $types, true), 404);

        return $next($request);
    }
}
