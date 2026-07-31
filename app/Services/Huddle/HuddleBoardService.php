<?php

namespace App\Services\Huddle;

use App\Enums\Huddle\DayColor;
use App\Models\Huddle\HuddlePatientDay;
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
            ->include('demographics', 'pending_events', 'multidisciplinary', 'surgery', 'clinical')
            ->get();

        $attendanceNumbers = collect($patients)
            ->filter(fn ($p) => ! empty($p['has_patient']) && ! empty($p['nr_atendimento']))
            ->pluck('nr_atendimento')
            ->map(fn ($nr) => (int) $nr)
            ->all();

        $dayRecords = $this->dayRecords($sectorId, $date, $attendanceNumbers);
        $colorCounts = HuddlePatientDay::colorCountsFor($attendanceNumbers);

        return array_map(
            fn (array $patient) => $this->mergeHuddleState($patient, $dayRecords, $colorCounts),
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
            ->with('redReasons')
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
     * @return array<string, mixed>
     */
    private function mergeHuddleState(array $patient, array $dayRecords, array $colorCounts): array
    {
        if (empty($patient['has_patient']) || empty($patient['nr_atendimento'])) {
            return $patient;
        }

        $nr = (int) $patient['nr_atendimento'];
        $record = $dayRecords[$nr] ?? null;

        $color = $record?->color ?? DayColor::Red;
        $counts = $colorCounts[$nr] ?? ['red' => 0, 'green' => 0];

        $patient['huddle_has_record'] = $record !== null;
        $patient['huddle_color'] = $color->value;
        $patient['huddle_color_label'] = $color->shortLabel();
        $patient['huddle_styling'] = $color->cardStyling();
        $patient['huddle_expected_discharge'] = $record?->expected_discharge_date?->format('d/m/Y');
        $patient['huddle_clinical_criteria'] = $record?->clinical_criteria;
        $patient['huddle_red_count'] = $counts['red'];
        $patient['huddle_green_count'] = $counts['green'];
        $patient['huddle_reasons'] = $this->formatReasons($record);

        return $patient;
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
