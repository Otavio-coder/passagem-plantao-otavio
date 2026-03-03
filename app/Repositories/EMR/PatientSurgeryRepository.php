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

        $hasMap  = [];
        $detailed = [];
        foreach ($attendanceNumbers as $nr) {
            $hasMap[$nr]   = false;
            $detailed[$nr] = [];
        }

        if (empty($personIds)) {
            return ['surgery' => $hasMap, 'surgery_detailed' => $detailed];
        }

        $cutoff = Carbon::now()->addMonth();

        $surgeriesByPerson = Appointment::surgeries()
            ->whereIn('cd_pessoa_fisica', $personIds)
            ->where('dt_agenda', '>=', Carbon::today())
            ->where('dt_agenda', '<=', $cutoff)
            ->whereNull('dt_executada')
            ->orderBy('dt_agenda')
            ->orderBy('hr_inicio')
            ->get()
            ->groupBy('cd_pessoa_fisica');

        foreach ($attToPerson as $nr => $personId) {
            $group = $surgeriesByPerson->get($personId, collect());
            if ($group->isNotEmpty()) {
                $hasMap[$nr]   = true;
                $detailed[$nr] = $group->map(function (Appointment $s) {
                    $proc = $s->getProcedureDescriptionAttribute() ?? ($s->ds_cirurgia ?? 'Procedimento cirúrgico');
                    return [
                        'data_agenda'      => $s->dt_agenda ? Carbon::parse($s->dt_agenda)->format('d/m/Y') : '',
                        'hora_agenda'      => $s->hr_inicio ? Carbon::parse($s->hr_inicio)->format('H:i') : '00:00',
                        'procedimento'     => $proc,
                        'carater_cirurgia' => $s->getSurgeryCharacterAttribute() ?? ($s->ie_carater_cirurgia ?? ''),
                        'observacoes'      => $s->ds_observacao ?? '',
                        'nr_sequencia'     => $s->nr_sequencia,
                        'cd_procedimento'  => $s->cd_procedimento,
                        'ie_origem_proced' => $s->ie_origem_proced,
                    ];
                })->values()->all();
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
            $rows = DB::connection('tasy')->select("
                SELECT
                    SUBSTR(
                        TASY.obter_desc_agenda(aaa.cd_agenda) ||
                        ' / Data e hora: ' || TO_CHAR(aaa.dt_agenda, 'DD/MM/YYYY') ||
                        CASE WHEN aaa.hr_inicio IS NOT NULL THEN ' ' || TO_CHAR(aaa.hr_inicio, 'HH24:MI') ELSE ' 00:00' END ||
                        ' - Proced.: ' || TASY.obter_descricao_procedimento(aaa.cd_procedimento, aaa.ie_origem_proced),
                        1, 200
                    ) AS ds_agenda_contat,
                    aaa.hr_inicio,
                    aaa.dt_agenda,
                    aaa.cd_procedimento,
                    aaa.ie_origem_proced,
                    aaa.ie_carater_cirurgia,
                    aaa.ds_observacao
                FROM tasy.atendimento_paciente atp
                JOIN tasy.agenda_paciente aaa ON atp.cd_pessoa_fisica = aaa.cd_pessoa_fisica
                WHERE atp.nr_atendimento = :nr_atendimento
                AND aaa.IE_CARATER_CIRURGIA IS NOT NULL
                AND aaa.IE_CARATER_CIRURGIA <> 'X'
                AND aaa.dt_agenda > SYSDATE
                ORDER BY aaa.dt_agenda, aaa.hr_inicio
            ", ['nr_atendimento' => $attendanceNumber]);

            if (empty($rows)) {
                return [];
            }

            return array_map(function ($row) {
                return [
                    'procedimento'     => $row->ds_agenda_contat ?? 'Agendas de Cirurgia Recente',
                    'data_agenda'      => $row->dt_agenda ? Carbon::parse($row->dt_agenda)->format('d/m/Y') : 'Data não informada',
                    'hora_agenda'      => $row->hr_inicio ? Carbon::parse($row->hr_inicio)->format('H:i') : '00:00',
                    'status'           => 'AGENDADA',
                    'tipo_agendamento' => 'Cirúrgico',
                    'carater_cirurgia' => $this->getCaraterCirurgiaDescription($row->ie_carater_cirurgia ?? ''),
                    'observacoes'      => $this->filterSensitiveData($row->ds_observacao ?? ''),
                    'duracao_formatada'=> 'A definir',
                    'cd_procedimento'  => $row->cd_procedimento,
                    'ie_origem_proced' => $row->ie_origem_proced,
                ];
            }, $rows);
        } catch (\Exception $e) {
            Log::warning('PatientSurgeryRepository: Erro ao buscar procedimentos cirúrgicos: ' . $e->getMessage());
            return [];
        }
    }

    private function getCaraterCirurgiaDescription(string $carater): string
    {
        return match($carater) {
            'E' => 'Eletiva',
            'U' => 'Urgência',
            'G' => 'Emergência',
            default => 'Não informado',
        };
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
