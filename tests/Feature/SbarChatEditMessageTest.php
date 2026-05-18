<?php

namespace Tests\Feature;

use App\Livewire\SbarChatComponent;
use App\Models\System\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SbarChatEditMessageTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        Cache::put(
            config('config.ms_graph_token_name'),
            (object) ['access_token' => 'fake-token', 'expires_on' => now()->addHour()->timestamp]
        );

        Http::fake([
            'https://graph.microsoft.com/*' => Http::response(['jobTitle' => 'Médico'], 200),
        ]);
    }

    private function insertMessage(string $nr_atendimento, int $userId, string $content, ?Carbon $createdAt = null): int
    {
        return DB::table('chat_messages')->insertGetId([
            'nr_atendimento' => $nr_atendimento,
            'user_id' => $userId,
            'content' => $content,
            'created_at' => ($createdAt ?? now())->toDateTimeString(),
        ]);
    }

    private function makeComponent(string $patientId = 'NR001'): SbarChatComponent
    {
        $this->actingAs($this->user);

        $component = new SbarChatComponent;
        $component->mount($patientId, null, 'A-01');
        $component->initialize();

        return $component;
    }

    public function test_user_can_edit_own_message_twice_within_six_hours(): void
    {
        $messageId = $this->insertMessage('NR001', $this->user->id, 'Mensagem original');

        $component = $this->makeComponent('NR001');

        $result1 = $component->editMessage($messageId, 'Primeira edição');
        $this->assertTrue($result1['success'], 'Primeira edição deveria ser bem-sucedida: '.($result1['error'] ?? ''));
        $this->assertDatabaseHas('chat_messages', ['id' => $messageId, 'content' => 'Primeira edição']);

        $result2 = $component->editMessage($messageId, 'Segunda edição');
        $this->assertTrue($result2['success'], 'Segunda edição deveria ser bem-sucedida: '.($result2['error'] ?? ''));
        $this->assertDatabaseHas('chat_messages', ['id' => $messageId, 'content' => 'Segunda edição']);
    }

    public function test_edit_fails_after_six_hours(): void
    {
        $messageId = $this->insertMessage('NR002', $this->user->id, 'Mensagem antiga', now()->subHours(7));

        $component = $this->makeComponent('NR002');

        $result = $component->editMessage($messageId, 'Tentativa de edição');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('expirado', $result['error']);
    }

    public function test_edit_fails_if_not_owner(): void
    {
        $otherUser = User::factory()->create();
        $messageId = $this->insertMessage('NR003', $otherUser->id, 'Mensagem de outro usuário');

        $component = $this->makeComponent('NR003');

        $result = $component->editMessage($messageId, 'Tentativa');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('autor', $result['error']);
    }
}
