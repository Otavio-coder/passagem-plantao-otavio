<?php

namespace Tests\Feature;

use App\Models\System\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChatArchiveControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_show_view_builds_timeline_for_active_messages(): void
    {
        $user = User::factory()->create([
            'username' => 'plantonista.teste',
        ]);

        DB::table('chat_messages')->insert([
            [
                'nr_atendimento' => '12345',
                'cd_pessoa_fisica' => null,
                'user_id' => $user->id,
                'content' => 'Primeira anotacao',
                'created_at' => '2026-01-10 08:15:00',
                'updated_at' => '2026-01-10 08:15:00',
            ],
            [
                'nr_atendimento' => '12345',
                'cd_pessoa_fisica' => null,
                'user_id' => $user->id,
                'content' => 'Segunda anotacao',
                'created_at' => '2026-01-10 08:45:00',
                'updated_at' => '2026-01-10 08:45:00',
            ],
        ]);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->get(route('chat.archive.show', ['nr' => '12345']));

        $response->assertOk();
        $response->assertViewHas('timeline', function (array $timeline): bool {
            return collect($timeline)->contains(fn (array $item): bool => ($item['type'] ?? null) === 'separator')
                && collect($timeline)->contains(fn (array $item): bool => ($item['type'] ?? null) === 'message');
        });
        $response->assertSee('Primeira anotacao');
        $response->assertSee('Segunda anotacao');
        $response->assertSee('08:15');
    }
}
