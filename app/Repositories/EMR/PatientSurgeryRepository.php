<?php

namespace App\Repositories\EMR;

use App\Models\EMR\Core\Patient;
use App\Models\EMR\CPOE\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientSurgeryRepository
{
    /**
     * Retorna dois mapas para os atendimentos informados:
     *   'surgery'          => [nr_atendimento => bool has_surgery]
     *   'surgery_detailed' => [nr_atendimento => array de detalhes]
     */
    public function getFutureSurgeriesForAttendances(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return ['surgery' => [], 'surgery_detailed' => []];
        }

        $attToPerson = Patient::whereIn('nr_atendimento', $attendanceNumbers)
            ->pluck('cd_pessoa_fisica', 'nr_atendimento')
            ->toArray();

        $personIds = array_values(array_filter(array_unique(array_values($attToPerson))));

        $hasMap = [];
        $detailed = [];
        foreach ($attendanceNumbers as $nr) {
            $hasMap[$nr] = false;
            $detailed[$nr] = [];
        }

        if (empty($personIds)) {
            return ['surgery' => $hasMap, 'surgery_detailed' => $detailed];
        }

        $cutoff = Carbon::now()->addMonth();

        $surgeriesByPerson = Appointment::surgeries()
            ->select('agenda_paciente.*')
            ->with(['patient.sector'])
            ->addSelect(DB::raw('COALESCE(TASY.OBTER_TIPO_CIRUR_PROC(nr_seq_proc_interno), (SELECT MAX(p.cd_tipo_procedimento) FROM tasy.procedimento p WHERE p.cd_procedimento = agenda_paciente.cd_procedimento AND p.ie_origem_proced = agenda_paciente.ie_origem_proced)) AS cd_tipo_cirurgia_proc'))
            ->addSelect(DB::raw('NVL(TASY.OBTER_SETOR_PRESCR_AGENDA(nr_sequencia), NVL(TASY.OBTER_SETOR_AGENDA(cd_agenda), cd_setor_atendimento)) AS cd_setor_execucao'))
            ->addSelect(DB::raw('(SELECT NVL(sa.ds_prescricao, sa.ds_setor_atendimento) FROM tasy.setor_atendimento sa WHERE sa.cd_setor_atendimento = NVL(TASY.OBTER_SETOR_PRESCR_AGENDA(nr_sequencia), NVL(TASY.OBTER_SETOR_AGENDA(cd_agenda), cd_setor_atendimento))) AS ds_setor_execucao'))
            ->addSelect(DB::raw('(SELECT MAX(vd.ds_valor_dominio) FROM tasy.valor_dominio vd WHERE vd.cd_dominio = 83 AND vd.vl_dominio = agenda_paciente.ie_status_agenda) AS ds_status_agenda_label'))
            ->addSelect(DB::raw('(SELECT MAX(vd.ds_valor_dominio) FROM tasy.valor_dominio vd WHERE vd.cd_dominio = 1016 AND vd.vl_dominio = agenda_paciente.ie_carater_cirurgia) AS ds_carater_label'))
            ->whereIn('cd_pessoa_fisica', $personIds)
            ->where('dt_agenda', '>=', Carbon::today())
            ->where('dt_agenda', '<=', $cutoff)
            ->whereNull('dt_executada')
            ->whereNotIn('ie_status_agenda', ['C', 'S', 'CR', 'E', 'AD'])
            ->orderBy('dt_agenda')
            ->orderBy('hr_inicio')
            ->get()
            ->groupBy('cd_pessoa_fisica');

        foreach ($attToPerson as $nr => $personId) {
            $group = $surgeriesByPerson->get($personId, collect());
            if ($group->isNotEmpty()) {
                $surgeryDescription = $this->getSurgeryDescription($nr, 'AA');
                $hasMap[$nr] = true;
                $detailed[$nr] = $group->map(fn (Appointment $appointment) => $this->mapSurgeryAppointment($appointment, $surgeryDescription))
                    ->values()
                    ->all();
            }
        }

        return ['surgery' => $hasMap, 'surgery_detailed' => $detailed];
    }

    /**
     * Busca procedimentos cirúrgicos agendados de um paciente específico
     */
    public function getSurgicalProcedures(int $attendanceNumber, ?string $sectorCode = null): array
    {
        try {
            $query = Appointment::surgeries()
                ->select('agenda_paciente.*')
                ->with(['patient.sector'])
                ->addSelect(DB::raw('COALESCE(TASY.OBTER_TIPO_CIRUR_PROC(nr_seq_proc_interno), (SELECT MAX(p.cd_tipo_procedimento) FROM tasy.procedimento p WHERE p.cd_procedimento = agenda_paciente.cd_procedimento AND p.ie_origem_proced = agenda_paciente.ie_origem_proced)) AS cd_tipo_cirurgia_proc'))
                ->addSelect(DB::raw('NVL(TASY.OBTER_SETOR_PRESCR_AGENDA(nr_sequencia), NVL(TASY.OBTER_SETOR_AGENDA(cd_agenda), cd_setor_atendimento)) AS cd_setor_execucao'))
                ->addSelect(DB::raw('(SELECT NVL(sa.ds_prescricao, sa.ds_setor_atendimento) FROM tasy.setor_atendimento sa WHERE sa.cd_setor_atendimento = NVL(TASY.OBTER_SETOR_PRESCR_AGENDA(nr_sequencia), NVL(TASY.OBTER_SETOR_AGENDA(cd_agenda), cd_setor_atendimento))) AS ds_setor_execucao'))
                ->addSelect(DB::raw('(SELECT MAX(vd.ds_valor_dominio) FROM tasy.valor_dominio vd WHERE vd.cd_dominio = 83 AND vd.vl_dominio = agenda_paciente.ie_status_agenda) AS ds_status_agenda_label'))
                ->addSelect(DB::raw('(SELECT MAX(vd.ds_valor_dominio) FROM tasy.valor_dominio vd WHERE vd.cd_dominio = 1016 AND vd.vl_dominio = agenda_paciente.ie_carater_cirurgia) AS ds_carater_label'))
                ->where('nr_atendimento', $attendanceNumber)
                ->where('dt_agenda', '>', Carbon::now())
                ->whereNull('dt_executada')
                ->whereNotIn('ie_status_agenda', ['C', 'S', 'CR', 'E', 'AD'])
                ->orderBy('dt_agenda')
                ->orderBy('hr_inicio');

            if (! empty($sectorCode)) {
                $query->where('cd_setor_atendimento', $sectorCode);
            }

            $rows = $query->get();

            if ($rows->isEmpty()) {
                return [];
            }

            return $rows->map(fn (Appointment $appointment) => $this->mapSurgeryAppointment($appointment))
                ->values()
                ->all();
        } catch (\Exception $e) {
            Log::warning('PatientSurgeryRepository failed to fetch surgical procedures', [
                'exception' => $e,
                'attendance_number' => $attendanceNumber,
                'sector_code' => $sectorCode,
            ]);

            return [];
        }
    }

    private function getCaraterCirurgiaDescription(string $carater): string
    {
        return match ($carater) {
            'A' => 'Ambulatorial',
            'E' => 'Eletiva',
            'M' => 'Emergência',
            'U' => 'Urgência',
            default => $carater !== '' ? $carater : 'Não informado',
        };
    }

    private function getSurgeryDescription(int $attendanceNumber, string $option): ?string
    {
        try {
            $result = DB::connection('tasy')->selectOne(
                'SELECT TASY.OBTER_CIRURGIA_PACIENTE(:attendance_id, :ie_opcao) AS descricao FROM dual',
                [
                    'attendance_id' => $attendanceNumber,
                    'ie_opcao' => $option,
                ]
            );

            $descricao = trim((string) ($result->descricao ?? ''));

            return $descricao !== '' ? $descricao : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function mapSurgeryAppointment(Appointment $appointment, ?string $fallbackDescription = null): array
    {
        $descricaoOriginal = trim((string) ($fallbackDescription ?: $appointment->procedure_description ?: $appointment->ds_cirurgia ?: 'Procedimento cirúrgico'));
        $descricao = $this->normalizeSurgeryDescription($descricaoOriginal);
        $local = trim((string) ($appointment->ds_cirurgia ?? ''));
        $sala = trim((string) ($appointment->nr_seq_sala ?? ''));
        $sectorLabel = trim((string) ($appointment->ds_setor_execucao ?? $appointment->cd_setor_execucao ?? $appointment->sector?->ds_prescricao ?? $appointment->sector?->ds_setor_atendimento ?? $appointment->cd_setor_atendimento ?? ''));
        $dataAgendada = $appointment->dt_agenda ? Carbon::parse($appointment->dt_agenda)->format('d/m/Y') : 'Data não informada';
        $horaAgendada = $appointment->hr_inicio ? Carbon::parse($appointment->hr_inicio)->format('H:i') : '00:00';

        return [
            'procedimento' => $descricao,
            'descricao' => $descricao,
            'descricao_padronizada' => $descricao,
            'data_agenda' => $dataAgendada,
            'hora_agenda' => $horaAgendada,
            'status' => ! empty($appointment->ds_status_agenda_label) ? trim((string) $appointment->ds_status_agenda_label) : ($appointment->ie_status_agenda ?? 'Aguardando'),
            'tipo_agendamento' => 'Cirúrgico',
            'carater_cirurgia' => ! empty($appointment->ds_carater_label) ? trim((string) $appointment->ds_carater_label) : ($appointment->getSurgeryCharacterAttribute() ?? ($appointment->ie_carater_cirurgia ?? 'Não informado')),
            'observacoes' => $this->filterSensitiveData($appointment->ds_observacao ?? ''),
            'duracao_formatada' => 'A definir',
            'cd_procedimento' => $appointment->cd_procedimento,
            'ie_origem_proced' => $appointment->ie_origem_proced,
            'tipo_cirurgia_codigo' => ! empty($appointment->cd_tipo_cirurgia_proc)
                ? (int) $appointment->cd_tipo_cirurgia_proc
                : null,
            'setor_execucao' => $sectorLabel !== '' ? $sectorLabel : '-',
            'local' => $local !== '' ? $local : '-',
            'sala' => $sala !== '' ? $sala : '-',
            'summary' => trim(sprintf('[Cir] %s %s %s%s%s', $descricao, $dataAgendada, $horaAgendada, $local !== '' ? ' '.$local : '', $sala !== '' ? ' Sala '.$sala : '')),
        ];
    }

    private function normalizeSurgeryDescription(string $description): string
    {
        $cleaned = preg_replace('/\s*\(\s*Cirurgia[^\)]*\)\s*$/iu', '', $description);
        $normalized = trim((string) ($cleaned ?? $description));

        return $normalized !== '' ? $normalized : $description;
    }

    private function filterSensitiveData(?string $observacao): string
    {
        if (empty($observacao)) {
            return '';
        }

        $patterns = [
            '/R\$\s*[\d.,]+/i',
            '/valor.*[\d.,]+/i',
            '/custo.*[\d.,]+/i',
            '/autorizado.*coordenação/i',
        ];

        foreach ($patterns as $pattern) {
            $observacao = preg_replace($pattern, '', $observacao);
        }

        return trim($observacao) ?: 'Informações disponíveis no prontuário';
    }
}
