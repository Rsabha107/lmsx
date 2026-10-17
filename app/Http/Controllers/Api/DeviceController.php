<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Registers the phone's push token for the signed-in user. A token moves to
 * whoever signs in on that phone last, and is dropped with their session.
 */
class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|max:4096',
            'platform' => 'required|in:android,ios',
            'app_version' => 'nullable|string|max:40',
        ]);

        $accessToken = $request->user()->currentAccessToken();

        UserDevice::updateOrCreate(
            ['token_hash' => UserDevice::hashToken($data['token'])],
            [
                'user_id' => $request->user()->id,
                'personal_access_token_id' => $accessToken instanceof PersonalAccessToken ? $accessToken->id : null,
                'token' => $data['token'],
                'platform' => $data['platform'],
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['registered' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:4096']);

        UserDevice::where('user_id', $request->user()->id)
            ->where('token_hash', UserDevice::hashToken($data['token']))
            ->delete();

        return response()->json(['registered' => false]);
    }
}
