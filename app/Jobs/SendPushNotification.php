<?php

namespace App\Jobs;

use App\Models\UserDevice;
use App\Support\Push\FcmClient;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Pushes one message to every signed-in device of the given users. Dispatched
 * after the response, so a slow or failing FCM call never holds up a save.
 */
class SendPushNotification
{
    use Dispatchable;

    /**
     * @param  array<int, int>  $userIds
     * @param  array<string, string|int>  $data
     */
    public function __construct(
        public array $userIds,
        public string $type,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    public function handle(FcmClient $fcm): void
    {
        if (! $fcm->enabled() || $this->userIds === []) {
            return;
        }

        $devices = UserDevice::whereIn('user_devices.user_id', $this->userIds)
            ->leftJoin('personal_access_tokens as pat', 'pat.id', '=', 'user_devices.personal_access_token_id')
            ->where(fn ($q) => $q->whereNull('pat.expires_at')->orWhere('pat.expires_at', '>', now()))
            ->get(['user_devices.id', 'user_devices.token']);

        foreach ($devices as $device) {
            try {
                $result = $fcm->send($device->token, $this->title, $this->body, ['type' => $this->type] + $this->data);
            } catch (\Throwable $e) {
                Log::warning('Push notification failed', ['type' => $this->type, 'error' => $e->getMessage()]);

                continue;
            }

            if ($result === FcmClient::INVALID_TOKEN) {
                UserDevice::whereKey($device->id)->delete();
            }
        }
    }
}
