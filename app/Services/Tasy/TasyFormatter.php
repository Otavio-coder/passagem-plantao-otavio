<?php

namespace App\Services\Tasy;

use Carbon\Carbon;

/**
 * Responsável pela formatação dos dados Tasy para o frontend.
 * Sem acesso a banco de dados — recebe dados brutos e monta arrays para o frontend.
 */
class TasyFormatter
{
    public static function buildDischargeInfo(
        ?string $dtAlta,
        ?string $dtAltaMedico,
        ?string $dtPrevistoAlta,
        ?string $motivoAlta = null
    ): ?array {
        if (! empty($dtAlta)) {
            return [
                'tipo' => 'alta',
                'dt_alta' => $dtAlta,
                'dt_alta_formatted' => Carbon::parse($dtAlta)->format('d/m/Y H:i'),
                'ds_motivo_alta' => $motivoAlta,
            ];
        }

        if (! empty($dtAltaMedico)) {
            return [
                'tipo' => 'alta_medica',
                'dt_alta_medico' => $dtAltaMedico,
                'dt_alta_medico_formatted' => Carbon::parse($dtAltaMedico)->format('d/m/Y H:i'),
                'dt_previsto_alta' => $dtPrevistoAlta,
                'dt_previsto_alta_formatted' => ! empty($dtPrevistoAlta)
                    ? Carbon::parse($dtPrevistoAlta)->format('d/m/Y')
                    : null,
                'ds_motivo_alta' => $motivoAlta,
            ];
        }

        if (! empty($dtPrevistoAlta)) {
            return [
                'tipo' => 'previsao_alta',
                'dt_previsto_alta' => $dtPrevistoAlta,
                'dt_previsto_alta_formatted' => Carbon::parse($dtPrevistoAlta)->format('d/m/Y'),
            ];
        }

        return null;
    }

    private const DEFAULT_SCALE_DATA = [
        'score' => null,
        'needs_assessment' => true,
        'increased' => false,
        'classification' => 'Não classificado',
        'styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
        'shift' => null,
        'timestamp' => null,
    ];

    /**
     * Formata um leito/paciente para o SBAR Report
     */
    public function formatPatientForSbar($bed, array $sectorContext, array $batchData): array
    {
        $attendanceNumber = $bed->nr_atendimento;
        $age = $bed->age ?? 99;
        $isPediatric = $age < 18;
        $internmentDays = is_numeric($bed->internment_days ?? null) ? floatval($bed->internment_days) : null;
        $isNewPatient = ($internmentDays === null || $internmentDays < 1);

        $clinicalDetails = $batchData['clinical_details'][$attendanceNumber] ?? null;

        $hasAllergy = $this->checkHasAllergy($clinicalDetails->alergias_detalhadas ?? null);
        $hasIsolation = $this->checkHasIsolation($clinicalDetails->medida_bloqueio ?? null);
        $diagnosticos = $clinicalDetails->diagnosticos_comorbidades ?? null;
        $dispositivos = $clinicalDetails->dispositivos ?? null;
        $alergiasDetalhadas = $clinicalDetails->alergias_detalhadas ?? null;

        $patientData = [
            'cd_unidade_basica' => $bed->cd_unidade_basica ?? 'N/A',
            'bed_sequence' => $bed->bed_sequence ?? 0,
            'bed_display_order' => (int) ($bed->bed_display_order ?? $bed->bed_sequence ?? 0),
            'bed_status' => $bed->bed_status ?? 'A',
            'cd_setor_atendimento' => $bed->cd_setor_atendimento ?? null,
            'ds_setor_atendimento' => $bed->ds_prescricao ?? $bed->ds_setor_atendimento ?? 'Setor não identificado',
            'hospital_id' => $sectorContext['hospital_id'] ?? null,
            'hospital_name' => $sectorContext['hospital_name'] ?? 'Hospital não identificado',
            'nr_atendimento' => $attendanceNumber,
            'is_occupied' => (bool) ($bed->is_occupied ?? false),
            'has_patient' => (bool) ($bed->has_patient ?? false),
            'cd_pessoa_fisica' => $bed->cd_pessoa_fisica ?? null,
            'nm_pessoa_fisica' => $bed->nm_pessoa_fisica ?? 'Nome não informado',
            'nr_prontuario' => $bed->nr_prontuario ?? 'N/A',
            'birth_date' => $bed->birth_date ? Carbon::parse($bed->birth_date)->format('d/m/Y') : null,
            'age' => $age,
            'age_detailed' => $this->formatDetailedAgeFromParts($age, $bed->age_months ?? null, $bed->age_days ?? null),
            'sexo' => $bed->sexo ?? 'N/A',
            'convenio' => $bed->convenio ?? 'Não informado',
            'medico_responsavel' => $bed->medico_responsavel ?? 'Não informado',
            'dt_entrada' => $bed->dt_entrada ?? null,
            'internment_days' => $internmentDays,
            'is_new_patient' => $isNewPatient,
            'is_pediatric' => $isPediatric,
            'has_surgery' => $batchData['surgery'][$attendanceNumber] ?? false,
            'procedimentos_cirurgicos' => $batchData['surgery_detailed'][$attendanceNumber] ?? [],
            'multidisciplinary' => $batchData['multidisciplinary'][$attendanceNumber] ?? $this->getDefaultMultidisciplinary(),
            'multidisciplinary_requests' => $batchData['multidisciplinary_requests'][$attendanceNumber] ?? [],
            'diagnosticos_comorbidades' => $diagnosticos,
            'diagnosticos_list' => $this->parsePipeSeparatedList($diagnosticos),
            'medida_bloqueio' => $clinicalDetails->medida_bloqueio ?? 'Não',
            'motivos_isolamento' => $clinicalDetails->motivos_isolamento ?? null,
            'avaliacao_enf' => $clinicalDetails->avaliacao_enf ?? null,
            'plano_educ' => $clinicalDetails->plano_educ ?? null,
            'pe_data' => $clinicalDetails->pe_data ?? null,
            'ds_queda' => $clinicalDetails->ds_queda ?? 'Não',
            'diag' => $clinicalDetails->diag ?? null,
            'dispositivos' => $dispositivos,
            'dispositivos_list' => $this->parsePipeSeparatedList($dispositivos),
            'alergias_detalhadas' => $alergiasDetalhadas,
            'alergias_items' => $this->parseAllergyItems($alergiasDetalhadas),
            'materiais' => $clinicalDetails->materiais ?? null,
            'ultima_hemocultura' => $clinicalDetails->ultima_hemocultura ?? null,
            'hemocultura_pendente' => (int) ($clinicalDetails->hemocultura_pendente ?? 0) === 1,
            'alerts' => [],
            'has_allergy' => $hasAllergy,
            'has_isolation' => $hasIsolation,
            'pending_events' => $batchData['pending_events'][$attendanceNumber] ?? [],
            'discharge_info' => $this->buildDischargeInfoFromBed($bed) ?? ($batchData['discharge_info'][$attendanceNumber] ?? null),
        ];

        $rawScales = $batchData['scales'][$attendanceNumber] ?? null;
        $patientData = $this->addScalesToData($patientData, $rawScales, $age);
        $patientData = $this->applyCardStyling($patientData, $isPediatric);

        return $patientData;
    }

    public function buildDischargeInfoFromBed($bed): ?array
    {
        return self::buildDischargeInfo(
            null,
            ! empty($bed->dt_alta_medico) ? (string) $bed->dt_alta_medico : null,
            ! empty($bed->apa_dt_previsto_alta) ? (string) $bed->apa_dt_previsto_alta : null,
            ! empty($bed->ds_motivo_alta) ? (string) $bed->ds_motivo_alta : null
        );
    }

    public function addScalesToData(array $data, ?array $scales, int $age): array
    {
        $isPediatric = $age < 18;

        if (! $isPediatric) {
            $mews = $scales['mews'] ?? self::DEFAULT_SCALE_DATA;
            $data['mews_score'] = $mews['score'];
            $data['mews_previous_score'] = $mews['previous_score'] ?? null;
            $data['mews_previous_timestamp'] = $mews['previous_timestamp'] ?? null;
            $data['mews_needs_assessment'] = $mews['needs_assessment'];
            $data['mews_increased'] = $mews['increased'];
            $data['mews_classification'] = $mews['classification'];
            $data['mews_styling'] = $mews['styling'];
            $data['mews_shift'] = $mews['shift'];
            $data['mews_timestamp'] = $mews['timestamp'];
            $data['mews_details'] = $mews['details'] ?? null;
        } else {
            $pews = $scales['pews'] ?? self::DEFAULT_SCALE_DATA;
            $data['pews_score'] = $pews['score'];
            $data['pews_previous_score'] = $pews['previous_score'] ?? null;
            $data['pews_previous_timestamp'] = $pews['previous_timestamp'] ?? null;
            $data['pews_needs_assessment'] = $pews['needs_assessment'];
            $data['pews_increased'] = $pews['increased'];
            $data['pews_classification'] = $pews['classification'];
            $data['pews_styling'] = $pews['styling'];
            $data['pews_shift'] = $pews['shift'];
            $data['pews_timestamp'] = $pews['timestamp'];
            $data['pews_details'] = $pews['details'] ?? null;
        }

        foreach (['braden', 'morse', 'pain', 'vte'] as $scaleName) {
            $scale = $scales[$scaleName] ?? self::DEFAULT_SCALE_DATA;
            $data["{$scaleName}_score"] = $scale['score'];
            $data["{$scaleName}_previous_score"] = $scale['previous_score'] ?? null;
            $data["{$scaleName}_previous_timestamp"] = $scale['previous_timestamp'] ?? null;
            $data["{$scaleName}_needs_assessment"] = $scale['needs_assessment'];
            $data["{$scaleName}_increased"] = $scale['increased'];
            $data["{$scaleName}_classification"] = $scale['classification'];
            $data["{$scaleName}_styling"] = $scale['styling'];
            $data["{$scaleName}_shift"] = $scale['shift'];
            $data["{$scaleName}_timestamp"] = $scale['timestamp'];
            $data["{$scaleName}_details"] = $scale['details'] ?? null;
        }

        return $data;
    }

    public function applyCardStyling(array $data, bool $isPediatric): array
    {
        if (! ($data['has_patient'] ?? false)) {
            $data['gradient_class'] = 'from-gray-200 to-gray-300';
            $data['border_class'] = 'border border-gray-300';
            $data['text_color_class'] = 'text-gray-600';
            $data['gradient_style'] = 'background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);';

            return $data;
        }

        $isNewPatient = $data['is_new_patient'] ?? false;
        $score = ! $isPediatric ? ($data['mews_score'] ?? null) : ($data['pews_score'] ?? null);

        if ($isNewPatient) {
            $data['gradient_style'] = 'background: linear-gradient(135deg, #ecfdf5 0%, #bbf7d0 100%);';
            $data['gradient_class'] = 'from-green-50 to-green-200';
            $data['border_class'] = 'border-2 border-green-400';
            $data['text_color_class'] = 'text-gray-800';
        } elseif ($score === null) {
            $data['gradient_style'] = 'background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);';
            $data['gradient_class'] = 'from-blue-50 to-blue-100';
            $data['border_class'] = 'border border-gray-300';
            $data['text_color_class'] = 'text-gray-800';
        } elseif ($score >= 5) {
            $data['gradient_style'] = 'background: linear-gradient(135deg, #fff1f2 0%, #fee2e2 100%);';
            $data['gradient_class'] = 'from-red-50 to-red-100';
            $data['border_class'] = 'border-2 border-red-400';
            $data['text_color_class'] = 'text-gray-800';
        } elseif ($score == 4) {
            $data['gradient_style'] = 'background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);';
            $data['gradient_class'] = 'from-orange-50 to-orange-100';
            $data['border_class'] = 'border-2 border-orange-400';
            $data['text_color_class'] = 'text-gray-800';
        } elseif ($score == 3) {
            $data['gradient_style'] = 'background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%);';
            $data['gradient_class'] = 'from-yellow-50 to-yellow-100';
            $data['border_class'] = 'border-2 border-yellow-400';
            $data['text_color_class'] = 'text-gray-800';
        } else {
            $data['gradient_style'] = 'background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);';
            $data['gradient_class'] = 'from-blue-50 to-blue-100';
            $data['border_class'] = 'border border-gray-300';
            $data['text_color_class'] = 'text-gray-800';
        }

        return $data;
    }

    public function getDefaultMultidisciplinary(): array
    {
        return [
            'fisioterapia' => false,
            'psicologia' => false,
            'nutricao' => false,
            'fonoaudiologia' => false,
            'servico_social' => false,
            'acessos_vasculares' => false,
        ];
    }

    public function checkHasAllergy(?string $allergies): bool
    {
        if (empty($allergies)) {
            return false;
        }

        $allergies = trim($allergies);

        $noAllergyIndicators = [
            'Sem alergias registradas',
            'sem alergia',
            'não possui',
            'não informado',
            'nenhuma alergia',
            'nega alergia',
        ];

        $lowerAllergies = mb_strtolower($allergies);
        foreach ($noAllergyIndicators as $indicator) {
            if (str_contains($lowerAllergies, mb_strtolower($indicator))) {
                return false;
            }
        }

        return true;
    }

    public function checkHasIsolation(?string $isolationStatus): bool
    {
        if (empty($isolationStatus)) {
            return false;
        }

        return trim(mb_strtolower($isolationStatus)) === 'sim';
    }

    public function formatDetailedAgeFromParts(?int $years, ?int $months, ?int $days): string
    {
        if ($years === null && $months === null && $days === null) {
            return 'Idade não informada';
        }

        $parts = [];
        if ($years > 0) {
            $parts[] = $years.'a';
        }
        if ($months > 0) {
            $parts[] = $months.'m';
        }
        if ($days > 0) {
            $parts[] = $days.'d';
        }

        return ! empty($parts) ? implode(' ', $parts) : 'Recém-nascido';
    }

    private function parsePipeSeparatedList(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return collect(preg_split('/\|+/', $value) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values()
            ->all();
    }

    public function parseAllergyItemsPublic(?string $allergies): array
    {
        return $this->parseAllergyItems($allergies);
    }

    private function parseAllergyItems(?string $allergies): array
    {
        $cleaned = trim((string) ($allergies ?? ''));
        if ($cleaned === '') {
            return [];
        }

        $cleaned = strip_tags($cleaned);
        $cleaned = preg_replace('/\s*-\s*(Não informado|desconhecido|N\/A)[^;]*/iu', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/;\s*;/', ';', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned, '; ');

        if ($cleaned === '' || mb_strtolower($cleaned) === 'sem alergias registradas') {
            return [];
        }

        $items = collect(preg_split('/[;\r\n]+/', $cleaned) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values();

        return $items->map(function (string $item) {
            if (preg_match('/^(.+?)\s*[-–]\s*(.+)$/u', $item, $matches)) {
                return [
                    'med' => trim($matches[1]),
                    'grav' => trim($matches[2]),
                ];
            }

            return ['text' => $item];
        })->all();
    }
}
