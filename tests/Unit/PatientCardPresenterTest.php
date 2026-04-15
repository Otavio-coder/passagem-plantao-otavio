<?php

namespace Tests\Unit;

use App\Models\System\User;
use App\View\Presenters\PatientCardPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PatientCardPresenterTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_keeps_alta_efetivada_label_for_alta_type(): void
    {
        $display = PatientCardPresenter::buildDischargeDisplay([
            'tipo' => 'alta',
        ]);

        $this->assertTrue($display['show']);
        $this->assertSame('Alta Efetivada', $display['label']);
        $this->assertSame('alta.svg', $display['icon']);
    }

    #[Test]
    public function it_uses_prev_alta_label_for_alta_medica_type(): void
    {
        $display = PatientCardPresenter::buildDischargeDisplay([
            'tipo' => 'alta_medica',
        ]);

        $this->assertTrue($display['show']);
        $this->assertSame('Prev. Alta', $display['label']);
        $this->assertSame('alta.svg', $display['icon']);
    }

    #[Test]
    public function it_enriches_multidisciplinary_request_people_with_role(): void
    {
        User::factory()->create([
            'name' => 'Maria Requisitante',
            'username' => 'maria.req',
            'role' => 'Enfermeira',
            'role_synced_at' => now(),
        ]);

        User::factory()->create([
            'name' => 'Joao Responsavel',
            'username' => 'joao.resp',
            'role' => 'Médico',
            'role_synced_at' => now(),
        ]);

        $requests = PatientCardPresenter::buildMultidisciplinaryRequests([
            [
                'ds_equipe_destino' => 'Fisioterapia',
                'ie_status' => 'R',
                'dt_registro' => '2026-04-15 10:00:00',
                'dt_liberacao' => '2026-04-15 11:00:00',
                'dt_resposta' => '2026-04-15 12:00:00',
                'nm_requisitante' => 'Maria Requisitante',
                'nm_responsavel_resposta' => 'Joao Responsavel',
            ],
        ]);

        $this->assertSame('Maria Requisitante - Enfermeira', $requests[0]['nm_requisitante_display']);
        $this->assertSame('Joao Responsavel - Médico', $requests[0]['nm_responsavel_resposta_display']);
    }
}
