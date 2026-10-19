<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MicrosoftController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Token auth for the mobile supervisor app.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:120',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        return $this->issueToken($user, $credentials['device_name'] ?? 'mobile');
    }

    /** Redeems the one-time code from the Microsoft SSO callback, with the PKCE verifier. */
    public function microsoft(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:128',
            'verifier' => 'required|string|min:43|max:128',
            'device_name' => 'nullable|string|max:120',
        ]);

        $grant = Cache::pull(MicrosoftController::mobileCodeKey($data['code']));
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $data['verifier'], true)), '+/', '-_'), '=');
        $user = $grant && hash_equals($grant['challenge'], $challenge) ? User::find($grant['user_id']) : null;

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => ['The Microsoft sign-in has expired. Please try again.'],
            ]);
        }

        return $this->issueToken($user, $data['device_name'] ?? 'mobile');
    }

    private function issueToken(User $user, string $device): JsonResponse
    {
        // One active token per device keeps re-logins from piling up rows.
        $user->tokens()->where('name', $device)->delete();

        return response()->json([
            'token' => $user->createToken($device)->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            // The app hides checkpoint and issue actions; the API refuses them anyway.
            'read_only' => ! $user->can('jobs.work'),
        ];
    }
}
