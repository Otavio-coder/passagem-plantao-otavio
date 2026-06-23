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

    #[Test]
    public function it_build_rows_handles_four_thousand_events_within_time_limit(): void
    {
        $controller = app(PendingEventsReportController::class);

        // 200 patients × 20 events = 4000 events
        $patients = collect(range(1, 200))->map(fn (int $i) => [
            'has_patient' => true,
            'nr_atendimento' => (string) (100000 + $i),
            'nm_pessoa_fisica' => 'Paciente Teste '.$i,
            'cd_unidade_basica' => 'L'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'ds_setor_atendimento' => 'UTI ADULTO',
            '_setor_label' => 'UTI ADULTO',
            'discharge_info' => null,
            'pending_events' => $this->makeSyntheticPendingEvents(20, $i),
            'multidisciplinary_requests' => [],
        ]);

        $start = hrtime(true);
        $rows = $this->invokeBuildRows($controller, $patients);
        $elapsed = (hrtime(true) - $start) / 1e9;

        $this->assertGreaterThanOrEqual(4000, $rows->count(),
            'Expected at least 4000 rows from 200 patients × 20 events');
        $this->assertLessThan(2.0, $elapsed,
            sprintf('buildRows() took %.3fs for 4000 events — must complete under 2s', $elapsed));
    }

    #[Test]
    public function it_json_data_rows_have_all_required_fields(): void
    {
        $controller = app(PendingEventsReportController::class);

        $patients = collect([[
            'has_patient' => true,
            'nr_atendimento' => '99999',
            'nm_pessoa_fisica' => 'Paciente JSON',
            'cd_unidade_basica' => 'A01',
            'ds_setor_atendimento' => 'CTI',
            '_setor_label' => 'CTI',
            'discharge_info' => null,
            'pending_events' => $this->makeSyntheticPendingEvents(5, 1),
            'multidisciplinary_requests' => [],
        ]]);

        $rows = $this->invokeBuildRows($controller, $patients);
        $this->assertGreaterThan(0, $rows->count());

        $invokeMap = new ReflectionMethod(PendingEventsReportController::class, 'buildBadgeCls');
        $invokeMap->setAccessible(true);

        $required = [
            'paciente', 'ugb', 'atendimento', 'tipo_label', 'classificacao',
            'motivo_categoria', 'motivo_pendente', 'item', 'nr_prescricao',
            'nm_prescritor', 'status_execucao', 'data_solicitacao',
            'data_solicitacao_sort', 'data_coleta', 'data_coleta_sort',
            'data_resultado', 'data_resultado_sort', 'tempo_pendente',
            'tempo_pendente_sort', 'setor_origem', 'prev_alta',
            'tipo_evento', 'is_overdue',
        ];

        foreach ($required as $field) {
            $this->assertArrayHasKey($field, $rows->first(), "Missing field: {$field}");
        }

        $badgeCls = $invokeMap->invoke($controller, $rows->first()['motivo_categoria'] ?? '');
        $this->assertStringStartsWith('badge-', $badgeCls);
    }

    /** @return array<int, array<string, mixed>> */
    private function makeSyntheticPendingEvents(int $count, int $seed): array
    {
        $types = ['exame', 'proc_exame', 'antibiotico', 'hemoterapia', 'prescricao'];
        $statuses = ['10', '14', '20', '22', '25', '30'];
        $now = now();

        return array_map(function (int $i) use ($types, $statuses, $seed, $now): array {
            $typeIdx = ($seed + $i) % count($types);

            return [
                'tipo' => $types[$typeIdx],
                'descricao' => 'Hemograma Completo '.$i,
                'ds_subtipo' => 'Laboratorial',
                'dt_solicitacao' => $now->copy()->subHours($i + 1)->toDateTimeString(),
                // Alternate between ISO strings and pre-formatted d/m/Y H:i (as PatientPendingEventsService produces)
                'dt_coleta' => $i % 3 === 0 ? ($i % 2 === 0
                    ? $now->copy()->subHours($i)->toDateTimeString()
                    : $now->copy()->subHours($i)->format('d/m/Y H:i')) : null,
                'dt_resultado' => $i % 5 === 0 ? ($i % 2 === 0
                    ? $now->copy()->subMinutes(30)->toDateTimeString()
                    : $now->copy()->subMinutes(30)->format('d/m/Y H:i')) : null,
                'dt_evento' => $now->copy()->subHours($i + 1)->toDateTimeString(),
                'ie_status_execucao' => $statuses[$i % count($statuses)],
                'tempo_pendente' => $i.'h em aberto',
                'nr_prescricao' => (string) (900000 + $seed * 100 + $i),
                'nm_prescritor' => 'Dr. Teste '.($i % 10),
                'urgente' => $i % 7 === 0,
                'scola_status' => $i % 4 === 0 ? 'COLETADO' : null,
                'scola_resultado' => null,
                'scola_data_colheita' => $i % 4 === 0 ? $now->copy()->subHours(2)->toDateTimeString() : null,
            ];
        }, range(1, $count));
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
