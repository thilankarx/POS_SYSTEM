<?php

use App\Domain\Giftcards\Exceptions\GiftcardException;
use App\Domain\Loyalty\Exceptions\LoyaltyException;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Exceptions\KitchenPrintingException;
use App\Domain\Sales\Exceptions\PrintingException;
use App\Domain\Sales\Exceptions\ShiftException;
use App\Support\Idempotency\IdempotencyConflictException;
use App\Support\Idempotency\IdempotencyKey;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureBusinessType;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'business.type' => EnsureBusinessType::class,
            'active.user' => EnsureUserIsActive::class,
            'abilities' => CheckAbilities::class,
        ]);

        // Previously absent entirely: the framework only wires throttle:api
        // into the `api` middleware group when this is called, so every
        // route in routes/api.php except POST /login (which carries its own
        // throttle: middleware directly) had no rate limit of any kind.
        $middleware->throttleApi();
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Deliberately no --fix: this only surfaces drift for a human to
        // review, matching ReconcileStockCommand's own safety contract.
        $schedule->command('stock:reconcile')->daily();
        $schedule->command('model:prune', ['--model' => [IdempotencyKey::class]])->hourly();
        $schedule->command('loyalty:expire-points')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (CheckoutException $e) => response()->json(['message' => $e->getMessage()], 422));
        $exceptions->render(fn (ShiftException $e) => response()->json(['message' => $e->getMessage()], 422));
        $exceptions->render(fn (IdempotencyConflictException $e) => response()->json(['message' => $e->getMessage()], 409));
        $exceptions->render(fn (GiftcardException $e) => response()->json(['message' => $e->getMessage()], 422));
        $exceptions->render(fn (LoyaltyException $e) => response()->json(['message' => $e->getMessage()], 422));
        $exceptions->render(fn (PrintingException $e) => response()->json(['message' => $e->getMessage()], 422));
        $exceptions->render(fn (KitchenPrintingException $e) => response()->json(['message' => $e->getMessage()], 422));

        // A handful of races (two concurrent completes of the same cart,
        // two concurrent shift opens on the same terminal, ...) are only
        // caught by a unique index once the application-level lockForUpdate()
        // check can't -- see e.g. CompleteSaleAction's cart lock and
        // OpenShiftAction's own catch of this same exception. Any that
        // aren't caught locally still get a clean, expected 409 instead of a
        // raw 500 leaking a stack trace.
        $exceptions->render(fn (UniqueConstraintViolationException $e) => response()->json([
            'message' => 'This request conflicts with another request that was already processed. Please retry.',
        ], 409));
    })->create();
