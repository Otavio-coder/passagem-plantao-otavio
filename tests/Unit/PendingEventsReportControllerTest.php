<?php

namespace Tests\Unit;

use App\Http\Controllers\PendingEventsReportController;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class PendingEventsReportControllerTest extends TestCase
{
    #[Test]
    public function it_filters_multidisciplinary_requests_that_are_not_pending(): void
    {
        $controller = app(PendingEventsReportController::class);

        $patients = collect([
            [
                'has_patient' => true,
                'nr_atendimento' => 123,
                'nm_pessoa_fisica' => 'Paciente Teste',
                'cd_unidade_basica' => 'UB1',
                'ds_setor_atendimento' => 'Clínica',
                'pending_events' => [],
                'multidisciplinary_requests' => [
                    ['ds_status' => 'Respondido', 'ds_equipe_destino' => 'Nutrição'],
                    ['ds_status' => 'Cancelado', 'ds_equipe_destino' => 'Fisioterapia'],
                    ['ds_status' => 'Liberado', 'ds_equipe_destino' => 'Fonoaudiologia'],
                    ['ds_status' => 'Aberto', 'ds_equipe_destino' => 'Psicologia'],
                ],
            ],
        ]);

        $rows = $this->invokeBuildRows($controller, $patients);

        $this->assertCount(1, $rows);
        $this->assertSame('Consultoria', $rows[0]['tipo_label']);
        $this->assertSame('Aberto', $rows[0]['status']);
        $this->assertSame('Aguardando resposta', $rows[0]['motivo_pendente']);
        $this->assertSame('Consultoria - Psicologia', $rows[0]['item']);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $patients
     * @return Collection<int, array<string, mixed>>
     */
    private function invokeBuildRows(PendingEventsReportController $controller, Collection $patients): Collection
    {
        $method = new ReflectionMethod(PendingEventsReportController::class, 'buildRows');
        $method->setAccessible(true);

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $method->invoke($controller, $patients);

        return $rows;
    }
}
