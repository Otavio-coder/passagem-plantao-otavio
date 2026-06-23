<?php

namespace Tests\Feature;

use App\Models\System\User;
use App\Models\System\UserSectorPreference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PendingEventsReportJsonDataTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_returns_empty_data_when_no_sectors_authorized(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->get(route('pending.report.data', ['sector_ids[]' => [99999]]));

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'meta' => ['total', 'tipo_labels', 'categorias', 'classificacoes'],
        ]);
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function it_rejects_sectors_not_belonging_to_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        UserSectorPreference::create([
            'user_id' => $otherUser->id,
            'hospital_code' => 1,
            'hospital_name' => 'Hospital Teste',
            'sector_code' => 555,
            'sector_name' => 'Setor Alheio',
        ]);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->get(route('pending.report.data', ['sector_ids[]' => [555]]));

        $response->assertOk();
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function it_returns_empty_data_when_no_sector_ids_provided(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->get(route('pending.report.data'));

        $response->assertOk();
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->get(route('pending.report.data'));

        $response->assertRedirect();
    }

    #[Test]
    public function it_accepts_legacy_single_sector_id_param(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->get(route('pending.report.data').'?sector_id=99999');

        $response->assertOk();
        $response->assertJsonPath('data', []);
    }
}
