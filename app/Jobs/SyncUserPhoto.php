<?php

namespace App\Jobs;

use App\Models\System\User;
use App\Services\MSGraph\AccessTokenGetter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncUserPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(public readonly int $userId) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        try {
            $token = AccessTokenGetter::get();

            $response = Http::withToken($token)
                ->timeout(20)
                ->get("https://graph.microsoft.com/v1.0/users/{$user->username}@santacasa.org.br/photos/96x96/\$value");

            if ($response->status() === 200) {
                $body = $response->body();

                // Validate it's image data, not a JSON error
                $json = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($json['error'])) {
                    Log::warning('SyncUserPhoto: MS Graph returned error', [
                        'userId' => $this->userId,
                        'error' => $json['error'],
                    ]);

                    return;
                }

                $user->forceFill(['photo' => base64_encode($body)])->save();
            } elseif ($response->status() === 404) {
                // User has no photo — clear stale data if any
                if (! empty($user->photo)) {
                    $user->forceFill(['photo' => null])->save();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SyncUserPhoto: failed to fetch photo', [
                'userId' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
