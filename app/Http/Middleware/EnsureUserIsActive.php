<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AuthController::store() already refuses to *issue* a token to a
 * deactivated account, but that check runs once, at login -- a Sanctum
 * token normally lives up to 24h (config/sanctum.php), so an employee
 * deactivated mid-shift keeps a fully working token for however long is
 * left on it. Re-checked here, on every authenticated request, so
 * deactivation takes effect immediately instead of waiting out the token.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            $user->currentAccessToken()?->delete();

            abort(401, 'This account is no longer active.');
        }

        return $next($request);
    }
}
