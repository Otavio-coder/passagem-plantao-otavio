<?php

namespace Tests\Unit\MSGraph;

use App\Models\System\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GetUserRoleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_resolves_job_title_and_formats_the_display_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Maria da Silva',
            'username' => 'maria.silva',
        ]);

        Http::fake([
            'https://graph.microsoft.com/*' => Http::response([
                'jobTitle' => 'Enfermeira',
                'department' => 'UTI Adulto',
            ], 200),
        ]);

        $this->mockAccessToken();

        $this->assertSame('Enfermeira', $user->getUserRole(true));
        $this->assertSame('Maria da Silva - Enfermeira', $user->display_name_with_role);
    }

    public function test_it_falls_back_to_department_when_job_title_is_empty(): void
    {
        $user = User::factory()->create([
            'name' => 'João Pereira',
            'username' => 'joao.pereira',
        ]);

        Http::fake([
            'https://graph.microsoft.com/*' => Http::response([
                'jobTitle' => '',
                'department' => 'Cirurgia',
            ], 200),
        ]);

        $this->mockAccessToken();

        $this->assertSame('Cirurgia', $user->getUserRole(true));
        $this->assertSame('João Pereira - Cirurgia', $user->display_name_with_role);
    }

    private function mockAccessToken(): void
    {
        Cache::put(
            config('config.ms_graph_token_name'),
            (object) ['access_token' => 'fake-token', 'expires_on' => now()->addHour()->timestamp]
        );
    }
}