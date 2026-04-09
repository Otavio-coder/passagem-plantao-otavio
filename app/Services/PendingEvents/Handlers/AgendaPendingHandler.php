<?php

namespace App\Services\PendingEvents\Handlers;

use App\Repositories\EMR\PatientPrescriptionsRepository;
use App\Services\PendingEvents\AbstractPendingHandler;
use Illuminate\Support\Facades\Log;

/**
 * Cirurgias e exames agendados — handler unificado para agenda_paciente.
 *
 * Fonte: PatientPrescriptionsRepository::queryAgendaChunk()
 * Diferenciação cirurgia/exame: ie_carater_cirurgia NOT NULL e <> 'X' → cirurgia.
 * Janela: TRUNC(SYSDATE) até SYSDATE + 30 dias.
 */
class AgendaPendingHandler extends AbstractPendingHandler
{
    public function __construct(private readonly PatientPrescriptionsRepository $repository) {}

    protected function handlerName(): string
    {
        return 'Agenda (cirurgias+exames)';
    }

    protected function processChunk(array &$results, array $chunk): void
    {
        try {
            $rows = $this->repository->queryAgendaChunk($chunk);

            foreach ($rows as $row) {
                if (! isset($results[$row->nr_atendimento])) {
                    continue;
                }

                $dtFormatted = null;
                if ($row->dt_evento) {
                    $dtFormatted = date('d/m/Y', strtotime($row->dt_evento));
                    if (! empty($row->hr_inicio)) {
                        $dtFormatted .= ' '.date('H:i', strtotime($row->hr_inicio));
                    }
                }

                if ($row->tipo === 'cirurgia') {
                    $caraterCode = trim((string) ($row->ie_carater_cirurgia ?? ''));
                    $carater = ! empty($row->ds_carater_label)
                        ? trim((string) $row->ds_carater_label)
                        : ($caraterCode !== '' ? $caraterCode : 'Não informado');

                    $parts = [];
                    $localAgenda = trim((string) ($row->ds_local_agenda ?? $row->ds_cirurgia ?? ''));
                    if ($localAgenda !== '') {
                        $parts[] = 'Local: '.$localAgenda;
                    }
                    if (! empty($row->nr_seq_sala)) {
                        $parts[] = 'Sala: '.$row->nr_seq_sala;
                    }

                    $surgeryTypeCodeRaw = trim((string) ($row->cd_tipo_cirurgia ?? ''));
                    $surgeryTypeCode = $surgeryTypeCodeRaw !== '' ? (int) $surgeryTypeCodeRaw : null;
                    $statusCode = strtoupper(trim((string) ($row->ie_status_agenda ?? '')));
                    $statusLabel = ! empty($row->ds_status_agenda_label)
                        ? trim((string) $row->ds_status_agenda_label)
                        : ($statusCode !== '' ? $statusCode : null);

                    $descricaoNormalizado = $this->normalizeSurgeryDescription($row->descricao_proc ?? 'Cirurgia Agendada');
                    $dataAgenda = $row->dt_evento ? date('d/m/Y', strtotime($row->dt_evento)) : null;
                    $horaAgenda = ! empty($row->hr_inicio) ? date('H:i', strtotime($row->hr_inicio)) : '00:00';

                    $results[$row->nr_atendimento]['events'][] = [
                        'tipo' => 'cirurgia',
                        'icone' => 'general-surgery.svg',
                        'descricao' => $descricaoNormalizado,
                        'descricao_padronizada' => $descricaoNormalizado,
                        'ds_subtipo' => 'Cirurgia '.$carater,
                        'dt_evento' => $row->dt_evento,
                        'dt_evento_formatted' => $dtFormatted,
                        'data_agenda' => $dataAgenda,
                        'hora_agenda' => $horaAgenda,
                        'ds_complemento' => implode(' · ', $parts),
                        'carater' => $carater,
                        'carater_cirurgia' => $carater,
                        'tipo_agendamento' => 'Cirúrgico',
                        'setor_execucao' => trim((string) ($row->ds_setor_execucao ?? '')) !== '' ? trim((string) $row->ds_setor_execucao) : null,
                        'local' => $localAgenda !== '' ? $localAgenda : null,
                        'sala' => $row->nr_seq_sala ?? null,
                        'tipo_cirurgia_codigo' => $surgeryTypeCode,
                        'cd_tipo_cirurgia' => $surgeryTypeCode,
                        'status_agenda_codigo' => $statusCode !== '' ? $statusCode : null,
                        'status_laudo' => $statusLabel,
                        'observacoes' => $this->filterSensitiveSurgeryObservation($row->ds_observacao ?? null),
                        'urgente' => in_array($row->ie_carater_cirurgia, ['U', 'M']),
                        'nr_seq_proc_interno' => $row->nr_seq_proc_interno ?? null,
                        '_fonte' => 'agenda',
                    ];
                } else {
                    $descricao = $row->descricao_proc ?? $row->ds_observacao ?? 'Procedimento Agendado';
                    $tipoAgenda = ! empty($row->nr_seq_exame_lab) ? 'exame' : 'procedimento';

                    $statusCode = strtoupper(trim((string) ($row->ie_status_agenda ?? '')));
                    $statusLabel = ! empty($row->ds_status_agenda_label)
                        ? trim((string) $row->ds_status_agenda_label)
                        : ($statusCode !== '' ? $statusCode : null);

                    $results[$row->nr_atendimento]['events'][] = [
                        'tipo' => $tipoAgenda,
                        'icone' => $tipoAgenda === 'exame' ? 'outpatient-department.svg' : 'tac.svg',
                        'descricao' => substr($descricao, 0, 80),
                        'ds_subtipo' => 'Agendamento',
                        'status_laudo' => $statusLabel,
                        'ie_status_agenda' => $statusCode !== '' ? $statusCode : null,
                        'dt_evento' => $row->dt_evento,
                        'dt_evento_formatted' => $dtFormatted,
                        'setor_execucao' => trim((string) ($row->ds_setor_execucao ?? '')) !== '' ? trim((string) $row->ds_setor_execucao) : null,
                        'urgente' => false,
                        'nr_seq_proc_interno' => $row->nr_seq_proc_interno ?? null,
                        '_fonte' => 'agenda',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[PendingEvents] AgendaHandler query failed', [
                'exception' => $e,
                'attendance_count' => count($chunk),
            ]);
        }
    }

    private function normalizeSurgeryDescription(string $description): string
    {
        $cleaned = preg_replace('/\s*\(\s*Cirurgia[^\)]*\)\s*$/iu', '', $description);
        $normalized = trim((string) ($cleaned ?? $description));

        return $normalized !== '' ? $normalized : $description;
    }

    private function filterSensitiveSurgeryObservation(?string $observation): ?string
    {
        if (empty($observation)) {
            return null;
        }

        $patterns = [
            '/R\$\s*[\d.,]+/i',
            '/valor.*[\d.,]+/i',
            '/custo.*[\d.,]+/i',
            '/autorizado.*coordenação/i',
        ];

        $filtered = $observation;
        foreach ($patterns as $pattern) {
            $filtered = preg_replace($pattern, '', (string) $filtered);
        }

        $normalized = trim((string) $filtered);

        return $normalized !== '' ? $normalized : null;
    }
}
