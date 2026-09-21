<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function store(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('username', 'password');

        if (! Auth::guard('web')->validate($credentials)) {
            throw ValidationException::withMessages([
                'username' => ['These credentials do not match our records.'],
            ]);
        }

        $user = User::where('username', $credentials['username'])->firstOrFail();

        abort_if(! $user->is_active, 422, 'This account is not active.');

        // Not Auth::login() -- this is a stateless Sanctum-token endpoint,
        // and logging the user into a session would wrongly issue a
        // session cookie to an API client. Firing the event directly still
        // triggers RecordLastLogin the same as a real session login does.
        event(new Login('web', $user, false));

        // Scoped, not '*': this is the only token this endpoint (or anywhere
        // else in the app -- it's the sole createToken() call site) ever
        // issues, and it exists to authenticate the register API in
        // routes/api.php, nothing else. The 'register' ability is enforced
        // there via the 'abilities:register' middleware. A '*'-abilities
        // token already in the database from before this change still
        // works everywhere a scoped one does -- Sanctum's tokenCan() treats
        // '*' as satisfying any ability check -- so this doesn't invalidate
        // any session already issued.
        $deviceName = $request->string('device_name')->toString() ?: 'register';
        $token = $user->createToken($deviceName, ['register'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }
}
