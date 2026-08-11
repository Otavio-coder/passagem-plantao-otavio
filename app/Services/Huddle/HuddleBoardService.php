<?php

namespace App\Services\Huddle;

use App\Enums\Huddle\DayColor;
use App\Enums\Huddle\HuddleChecklistItem;
use App\Models\Huddle\HuddlePatientDay;
use App\Models\Huddle\HuddleSafetyAssessment;
use App\Services\PatientData\PatientDataLoader;
use Carbon\Carbon;

/**
 * Monta o board do Huddle de Gestão de Altas para um setor.
 *
 * Reaproveita o PatientDataLoader (demografia + pendências + multidisciplinar +
 * cirurgia) e faz o merge com o estado do Huddle persistido em MySQL
 * (huddle_patient_days do dia + contadores red/green da internação).
 *
 * Enquanto não há registro do dia para um paciente, o card assume RED por padrão,
 * conforme a metodologia Red2Green ("todo dia começa vermelho").
 */
class HuddleBoardService
{
    /**
     * @return array<int, array<string, mixed>> Lista de pacientes enriquecida para o card do Huddle.
     */
    public function forSector(int $sectorId, ?string $date = null): array
    {
        $date ??= Carbon::today()->toDateString();

        $patients = PatientDataLoader::forSector($sectorId)
            ->include('demographics', 'scales', 'pending_events', 'multidisciplinary', 'surgery', 'clinical')
            ->get();

        $attendanceNumbers = collect($patients)
            ->filter(fn ($p) => ! empty($p['has_patient']) && ! empty($p['nr_atendimento']))
            ->pluck('nr_atendimento')
            ->map(fn ($nr) => (int) $nr)
            ->all();

        $dayRecords = $this->dayRecords($sectorId, $date, $attendanceNumbers);
        $colorCounts = HuddlePatientDay::colorCountsFor($attendanceNumbers);
        $dischargeLoader = app(HuddleDischargeLoader::class);
        $transporte = $dischargeLoader->transporteForSector($sectorId, $attendanceNumbers);
        $orientacao = $dischargeLoader->orientacaoForSector($sectorId, $attendanceNumbers);

        // A unidade já teve o Round Unidade preenchido hoje? (1 registro por setor/dia)
        $roundDone = HuddleSafetyAssessment::query()
            ->forSector($sectorId)
            ->forDate($date)
            ->exists();

        return array_map(
            fn (array $patient) => $this->mergeHuddleState($patient, $dayRecords, $colorCounts, $transporte, $orientacao, $roundDone),
            $patients
        );
    }

    /**
     * Registros de huddle do dia, indexados por nr_atendimento, com motivos carregados.
     *
     * @param  int[]  $attendanceNumbers
     * @return array<int, HuddlePatientDay>
     */
    private function dayRecords(int $sectorId, string $date, array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        return HuddlePatientDay::query()
            ->with(['redReasons', 'checklistAnswers'])
            ->forSector($sectorId)
            ->forDate($date)
            ->whereIn('nr_atendimento', $attendanceNumbers)
            ->get()
            ->keyBy('nr_atendimento')
            ->all();
    }

    /**
     * @param  array<int, HuddlePatientDay>  $dayRecords
     * @param  array<int, array{red: int, green: int}>  $colorCounts
     * @param  array<int, string|null>  $transporte
     * @param  array<int, string|null>  $orientacao
     * @return array<string, mixed>
     */
    private function mergeHuddleState(array $patient, array $dayRecords, array $colorCounts, array $transporte = [], array $orientacao = [], bool $roundDone = false): array
    {
        if (empty($patient['has_patient']) || empty($patient['nr_atendimento'])) {
            return $patient;
        }

        // Marca no card que a unidade já teve o Round Unidade preenchido hoje.
        $patient['huddle_unit_round_done'] = $roundDone;

        $nr = (int) $patient['nr_atendimento'];
        $record = $dayRecords[$nr] ?? null;

        $color = $record?->color ?? DayColor::Red;
        $counts = $colorCounts[$nr] ?? ['red' => 0, 'green' => 0];

        $patient['huddle_has_record'] = $record !== null;
        $patient['huddle_color'] = $color->value;
        $patient['huddle_color_label'] = $color->shortLabel();
        $patient['huddle_styling'] = $color->cardStyling();
        // Previsão de alta exibida no card: a editada no Huddle tem prioridade; senão,
        // usa a previsão que já vem do Tasy (autofill).
        $patient['huddle_expected_discharge'] = $record?->expected_discharge_date?->format('d/m/Y')
            ?? ($patient['discharge_info']['dt_previsto_alta_formatted'] ?? null);
        $patient['huddle_clinical_criteria'] = $record?->clinical_criteria;
        $patient['huddle_red_count'] = $counts['red'];
        $patient['huddle_green_count'] = $counts['green'];
        $patient['huddle_reasons'] = $this->formatReasons($record);
        $patient['huddle_status'] = $record?->status;

        // Sinais do checklist (item_code => 'red'|'green') e contagens reais do Tasy,
        // para o card mostrar categoria + status + quantidade.
        $patient['huddle_checklist'] = $this->checklistSignals($record);
        $patient['huddle_pending'] = $this->pendingCounts($patient);
        $patient['huddle_prescricao_alta'] = ($patient['discharge_info']['tipo'] ?? null) === 'alta_medica';

        // Idade legível (meses para menores de 1 ano) e as janelas de alta (72h/24h),
        // automáticas a partir da previsão de alta do Tasy (sem pergunta manual).
        // Sequência de dias consecutivos na mesma cor (contagem red/green seguidos).
        $patient['huddle_streak'] = HuddlePatientDay::consecutiveStreak($nr);

        $patient['age_label'] = $this->ageLabel($patient);
        $days = $this->daysUntilDischarge($patient);
        $patient['huddle_days_until_discharge'] = $days;
        $patient['huddle_discharge_within_72h'] = $days !== null && $days >= 0 && $days <= 3;
        $patient['huddle_discharge_within_24h'] = $days !== null && $days >= 0 && $days <= 1;
        $patient['huddle_transporte'] = $transporte[(int) $patient['nr_atendimento']] ?? null;
        $patient['huddle_orientacao'] = $orientacao[(int) $patient['nr_atendimento']] ?? null;

        return $patient;
    }

    /**
     * Idade legível a partir das partes inteiras vindas do Oracle (mesmo modelo do
     * SBAR): "X anos" para 1 ano ou mais; para menores de 1 ano, "X meses" (ou
     * "recém-nascido"). Usa idade/meses inteiros — nunca cálculo com fração.
     */
    private function ageLabel(array $patient): string
    {
        $years = $patient['age'] ?? null;

        if ($years === null || ! is_numeric($years)) {
            return '—';
        }

        $years = (int) $years;

        if ($years >= 1) {
            return $years.' '.($years === 1 ? 'ano' : 'anos');
        }

        // Menos de 1 ano → meses inteiros (parte já calculada pelo Oracle).
        $months = (int) ($patient['age_months'] ?? 0);

        if ($months <= 0) {
            return 'recém-nascido';
        }

        return $months.' '.($months === 1 ? 'mês' : 'meses');
    }

    /**
     * Gate 72h automático: verdadeiro quando há previsão de alta no Tasy até 3 dias
     * a partir de hoje. Substitui a pergunta manual de triagem.
     */
    /**
     * Dias entre hoje e a previsão de alta do Tasy.
     *
     * Retorna: 0 = alta hoje · 1 = amanhã · negativo = previsão já vencida ·
     * null = sem previsão registrada. As janelas de 72h/24h são derivadas deste valor
     * (ver mergeHuddleState), o que exclui previsões passadas do gate.
     *
     * Usa a data já formatada (d/m/Y) — determinística, evita a ambiguidade do
     * formato brasileiro que o Carbon::parse poderia interpretar errado.
     */
    private function daysUntilDischarge(array $patient): ?int
    {
        $formatted = $patient['discharge_info']['dt_previsto_alta_formatted'] ?? null;

        if (empty($formatted)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('d/m/Y', $formatted)->startOfDay();

            // Sinalizado: negativo quando a previsão está no passado.
            return (int) Carbon::today()->diffInDays($date, false);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Sinal (Red/Green) de cada item do checklist respondido, indexado pelo código do item.
     *
     * @return array<string, string>
     */
    private function checklistSignals(?HuddlePatientDay $record): array
    {
        if ($record === null) {
            return [];
        }

        $signals = [];

        foreach ($record->checklistAnswers as $answer) {
            $code = $answer->item_code instanceof HuddleChecklistItem
                ? $answer->item_code->value
                : (string) $answer->item_code;

            $signals[$code] = $answer->signal?->value;
        }

        return $signals;
    }

    /**
     * Contagem real de pendências do Tasy por categoria (a partir de pending_events
     * e do mapa multidisciplinar já carregados no paciente).
     *
     * @return array{exames: int, procedimentos: int, terapias: int, multidisciplinar: int}
     */
    private function pendingCounts(array $patient): array
    {
        $events = collect($patient['pending_events'] ?? []);

        $countOf = fn (array $tipos): int => $events
            ->filter(fn ($e) => in_array($e['tipo'] ?? '', $tipos, true))
            ->count();

        $multidisciplinar = collect($patient['multidisciplinary'] ?? [])
            ->filter(fn ($open) => $open === true)
            ->count();

        return [
            'exames' => $countOf(['exame']),
            'procedimentos' => $countOf(['procedimento', 'cirurgia']),
            'terapias' => $countOf(['hemoterapia', 'quimioterapia', 'antibiotico']),
            'multidisciplinar' => $multidisciplinar,
        ];
    }

    /**
     * @return array<int, array{label: string, category: string, accent: string}>
     */
    private function formatReasons(?HuddlePatientDay $record): array
    {
        if ($record === null) {
            return [];
        }

        return $record->redReasons
            ->map(fn ($reason) => [
                'label' => $reason->reason_code->label(),
                'category' => $reason->category->label(),
                'accent' => $reason->category->accentClass(),
            ])
            ->all();
    }
}
