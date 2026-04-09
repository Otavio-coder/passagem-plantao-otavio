<?php

namespace Tests\Unit;

use App\Jobs\SyncUserPhoto;
use App\Models\System\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncUserPhotoJobTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_saves_photo_when_ms_graph_returns_image(): void
    {
        $user = User::factory()->create(['photo' => null]);

        Http::fake([
            'https://graph.microsoft.com/*' => Http::response(
                'fake-image-binary-data',
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $this->mockAccessToken();

        (new SyncUserPhoto($user->id))->handle();

        $this->assertSame(base64_encode('fake-image-binary-data'), $user->fresh()->photo);
    }

    public function test_it_clears_photo_when_user_has_no_photo_in_graph(): void
    {
        $user = User::factory()->create(['photo' => base64_encode('old-photo-data')]);

        Http::fake([
            'https://graph.microsoft.com/*' => Http::response('', 404),
        ]);

        $this->mockAccessToken();

        (new SyncUserPhoto($user->id))->handle();

        $this->assertNull($user->fresh()->photo);
    }

    public function test_it_does_not_save_when_graph_returns_json_error(): void
    {
        $user = User::factory()->create(['photo' => null]);

        Http::fake([
            'https://graph.microsoft.com/*' => Http::response(
                json_encode(['error' => ['code' => 'ErrorItemNotFound']]),
                200,
                ['Content-Type' => 'application/json']
            ),
        ]);

        $this->mockAccessToken();

        (new SyncUserPhoto($user->id))->handle();

        $this->assertNull($user->fresh()->photo);
    }

    public function test_it_overwrites_existing_photo_on_sync(): void
    {
        $user = User::factory()->create(['photo' => base64_encode('old-photo')]);

        Http::fake([
            'https://graph.microsoft.com/*' => Http::response(
                'new-image-data',
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $this->mockAccessToken();

        (new SyncUserPhoto($user->id))->handle();

        $this->assertSame(base64_encode('new-image-data'), $user->fresh()->photo);
    }

    public function test_it_does_nothing_when_user_not_found(): void
    {
        Http::fake();

        (new SyncUserPhoto(999999))->handle();

        Http::assertNothingSent();
    }

    private function mockAccessToken(): void
    {
        Cache::put(
            config('config.ms_graph_token_name'),
            (object) ['access_token' => 'fake-token', 'expires_on' => now()->addHour()->timestamp]
        );
    }
}
