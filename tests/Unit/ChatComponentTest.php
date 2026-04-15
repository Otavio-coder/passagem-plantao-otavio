<?php

namespace Tests\Unit;

use App\Livewire\ChatComponent;
use App\Models\System\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatComponentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_mounts_current_user_with_display_name_and_role(): void
    {
        $user = User::factory()->create([
            'name' => 'Maria da Silva',
            'username' => 'maria.silva',
            'photo' => base64_encode('fake-photo-bytes'),
        ]);

        Http::fake([
            'https://graph.microsoft.com/*' => Http::response([
                'jobTitle' => 'Enfermeira',
            ], 200),
        ]);

        $this->mockAccessToken();
        $this->actingAs($user);

        $component = new ChatComponent;
        $component->mount(123, 456, 'A-01');

        $currentUser = $component->currentUser;

        $this->assertSame('Maria da Silva', $currentUser['name']);
        $this->assertSame('Enfermeira', $currentUser['role']);
        $this->assertSame('Maria da Silva - Enfermeira', $currentUser['display_name']);
        $this->assertSame(base64_encode('fake-photo-bytes'), $currentUser['photo']);
    }

    private function mockAccessToken(): void
    {
        Cache::put(
            config('config.ms_graph_token_name'),
            (object) ['access_token' => 'fake-token', 'expires_on' => now()->addHour()->timestamp]
        );
    }
}
