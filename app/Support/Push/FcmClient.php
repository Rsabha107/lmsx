<?php

namespace App\Support\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends one message to one device through the FCM HTTP v1 API, signed with a
 * Google service account (FCM_CREDENTIALS = path to its JSON key file).
 */
class FcmClient
{
    public const SENT = 'sent';

    public const INVALID_TOKEN = 'invalid_token';

    public const FAILED = 'failed';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private ?array $account = null;

    public function enabled(): bool
    {
        return $this->account() !== null;
    }

    /**
     * @param  array<string, string>  $data
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): string
    {
        $account = $this->account();
        if (! $account) {
            return self::FAILED;
        }

        $response = Http::withToken($this->accessToken($account))
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$account['project_id']}/messages:send", [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => ['channel_id' => config('services.fcm.channel')],
                    ],
                ],
            ]);

        if ($response->successful()) {
            return self::SENT;
        }

        // Uninstalled app or a token from another Firebase project.
        $status = $response->json('error.status');
        if ($response->status() === 404 || $status === 'NOT_FOUND'
            || ($status === 'INVALID_ARGUMENT' && str_contains((string) $response->json('error.message'), 'registration token'))) {
            return self::INVALID_TOKEN;
        }

        Log::warning('FCM send failed', ['status' => $response->status(), 'error' => $status]);

        return self::FAILED;
    }

    private function accessToken(array $account): string
    {
        return Cache::remember('fcm.access_token.'.md5($account['client_email']), now()->addMinutes(50), function () use ($account) {
            $now = time();
            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode([
                    'iss' => $account['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => $account['token_uri'],
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];
            openssl_sign(implode('.', $segments), $signature, $account['private_key'], OPENSSL_ALGO_SHA256);
            $segments[] = $this->base64Url($signature);

            return Http::asForm()->timeout(10)->post($account['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ])->throw()->json('access_token');
        });
    }

    /** @return array{project_id: string, client_email: string, private_key: string, token_uri: string}|null */
    private function account(): ?array
    {
        if ($this->account !== null) {
            return $this->account;
        }

        $path = config('services.fcm.credentials');
        if (! $path) {
            return null;
        }
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = base_path($path);
        }

        $json = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($json) || empty($json['project_id']) || empty($json['client_email']) || empty($json['private_key'])) {
            Log::warning('FCM_CREDENTIALS is set but is not a readable service-account key file.');

            return null;
        }

        return $this->account = [
            'project_id' => $json['project_id'],
            'client_email' => $json['client_email'],
            'private_key' => $json['private_key'],
            'token_uri' => $json['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        ];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
