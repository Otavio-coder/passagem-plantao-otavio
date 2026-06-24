<?php

namespace App\Services\Tasy;

use App\Support\PendingEventTypeClassifier;
use Carbon\Carbon;

class PrescriptionFormatter
{
    public function formatMedication(object $row): array
    {
        $totalDoses = (int) ($row->num1 ?? 0);
        $checkedDoses = (int) ($row->num2 ?? 0);

        $doseDisplay = trim(($row->qty ?? '').' '.($row->unit_measure ?? ''));

        $isAntibiotic = ($row->flag2 ?? '') === 'S';
        $antibioticDay = ! empty($row->flag3) ? (int) $row->flag3 : null;
        $antibioticDays = ! empty($row->flag4) ? (int) $row->flag4 : null;

        $hasDialuent = ! empty($row->diluent_name);
        $diluentDisplay = $hasDialuent
            ? trim($row->diluent_name.(! empty($row->diluent_qty) ? ' – '.$row->diluent_qty : ''))
            : null;

        $hasPrepNotes = ! empty($row->prep_notes);
        $hasObs = ! empty($row->observation);
        $hasJust = ! empty($row->note_text);

        $volume = ! empty($row->med_volume) ? trim($row->med_volume).' mL' : null;
        $infusionMin = ! empty($row->infusion_min) ? (int) $row->infusion_min : null;

        $nrPrescricao = isset($row->nr_prescricao) && $row->nr_prescricao !== null
            ? (int) $row->nr_prescricao
            : null;

        return [
            'id' => (int) ($row->id ?? 0),
            'name' => $row->name ?? 'Medicamento não identificado',
            'dose' => $doseDisplay ?: null,
            'route' => $row->route ?? null,
            'frequency' => $row->secondary_info ?? null,
            'schedule' => $row->schedule ?? ($row->first_hour ?? null),
            'admin_code' => $row->flag1 ?? null,
            'is_antibiotic' => $isAntibiotic,
            'antibiotic_day' => $antibioticDay,
            'antibiotic_days' => $antibioticDays,
            'diluent' => $diluentDisplay,
            'volume' => $volume,
            'infusion_min' => $infusionMin,
            'prep_notes' => $hasPrepNotes ? $row->prep_notes : null,
            'dt_start' => ! empty($row->dt_start ?? null) ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end' => ! empty($row->dt_end ?? null) ? Carbon::parse($row->dt_end)->format('d/m/Y') : null,
            'status' => 'active',
            'total_doses' => $totalDoses,
            'checked_doses' => $checkedDoses,
            'observation' => $hasObs ? $row->observation : null,
            'justification' => $hasJust ? $row->note_text : null,
            'prescriber' => $row->extra1 ?? ($row->professional_name ?? null),
            'is_high_alert' => ($row->flag5 ?? '') === 'S',
            'is_controlled' => ($row->flag6 ?? '') === 'S',
            'drug_class' => ! empty($row->extra2) ? $row->extra2 : null,
            'has_details' => $hasObs || $hasJust || $hasPrepNotes || $hasDialuent || $infusionMin || $volume,
            'nr_prescricao' => $nrPrescricao,
        ];
    }

    public function formatRecommendation(object $row): array
    {
        $text = trim((string) ($row->name ?? 'No description'));
        $type = ! empty($row->secondary_info) ? trim((string) $row->secondary_info) : null;
        if ($type !== null && mb_strtolower($type) === mb_strtolower($text)) {
            $type = null;
        }

        return [
            'id' => (int) ($row->id ?? 0),
            'text' => $text !== '' ? $text : 'No description',
            'type' => $type,
            'observation' => ! empty($row->observation) ? $row->observation : null,
            'schedule' => $row->schedule ?? ($row->first_hour ?? null),
            'prescriber' => $row->professional_name ?? null,
            'dt_start' => ! empty($row->dt_start ?? null) ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end' => ! empty($row->dt_end ?? null) ? Carbon::parse($row->dt_end)->format('d/m/Y') : null,
            'has_details' => ! empty($row->observation),
        ];
    }

    public function formatIntervention(object $row): array
    {
        $labels = [];
        if (($row->flag1 ?? '') === '1') {
            $labels[] = 'Urgente';
        }
        if (($row->flag2 ?? '') === 'S') {
            $labels[] = 'Se necessário';
        }
        if (($row->flag3 ?? '') === 'S') {
            $labels[] = 'A critério médico';
        }
        if (! empty($row->flag4)) {
            $sideMap = ['D' => 'Direito', 'E' => 'Esquerdo', 'B' => 'Bilateral', 'U' => 'Unilateral', 'N' => 'Não se aplica'];
            $side = $sideMap[$row->flag4] ?? $row->flag4;
            $labels[] = 'Lado: '.$side;
        }

        return [
            'id' => (int) ($row->id ?? 0),
            'procedure_code' => ! empty($row->procedure_code) ? (string) $row->procedure_code : null,
            'prescriber_id' => ! empty($row->prescriber_id) ? (string) $row->prescriber_id : null,
            'name' => $row->name ?? 'Unidentified intervention',
            'observation' => ! empty($row->observation) ? $row->observation : null,
            'schedule' => $row->schedule ?? ($row->first_hour ?? null),
            'assignee' => $row->professional_name ?? null,
            'prescriber' => null,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
            'labels' => $labels,
            'dt_start' => $row->dt_start ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end' => $row->dt_end ? Carbon::parse($row->dt_end)->format('d/m/Y') : null,
            'has_details' => ! empty($row->observation) || ! empty($labels),
        ];
    }

    public function formatProcedure(object $row): array
    {
        $origem = $row->origem ?? 'PRESCRICAO';
        $statusRaw = (string) ($row->status_raw ?? '');
        $statusLabel = ! empty($row->status_label) ? trim((string) $row->status_label) : null;

        $status = 'Pendente';
        if (! empty($row->dt_baixa)) {
            $status = 'Realizado';
        } elseif ($statusLabel !== null) {
            $status = $statusLabel;
        } elseif ($statusRaw !== '') {
            $status = $statusRaw;
        }

        $type = ! empty($row->tipo) ? $row->tipo : null;

        $eventType = PendingEventTypeClassifier::fromTherapeuticProcedure([
            'event_type' => $row->event_type ?? null,
            'type' => $type,
            'name' => $row->name ?? null,
        ]);

        if ($origem === 'AGENDAMENTO' && $type === null) {
            $type = $eventType === PendingEventTypeClassifier::EXAM ? 'Exame' : 'Procedimento';
        }

        $isToday = (int) ($row->is_today ?? 0) === 1;
        $isYest = (int) ($row->is_yesterday ?? 0) === 1;
        $isTomorrow = (int) ($row->is_tomorrow ?? 0) === 1;
        $isNear = $isToday || $isYest || $isTomorrow;

        $scheduledRaw = null;
        if (! empty($row->scheduled_raw)) {
            try {
                $scheduledRaw = Carbon::parse($row->scheduled_raw)->format('Y-m-d');
            } catch (\Exception $e) {
                $scheduledRaw = null;
            }
        }

        return [
            'id' => (int) ($row->id ?? 0),
            'name' => $row->name ?? 'Procedimento não identificado',
            'origem' => $origem,
            'type' => $type,
            'scheduled' => $row->scheduled ?? null,
            'scheduled_raw' => $scheduledRaw,
            'status' => $status,
            'event_type' => $eventType,
            'is_today' => $isToday,
            'is_yesterday' => $isYest,
            'is_tomorrow' => $isTomorrow,
            'is_near' => $isNear,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
            'prescriber' => ! empty($row->professional_name) ? $row->professional_name : null,
            'nr_prescricao' => isset($row->nr_prescricao) && $row->nr_prescricao !== null ? (int) $row->nr_prescricao : null,
            'checklist_amostra' => ! empty($row->sample_check) ? (string) $row->sample_check : null,
            'dt_coleta' => ! empty($row->collected_at_raw) ? Carbon::parse($row->collected_at_raw)->format('d/m/Y H:i') : null,
            'dt_solicitacao' => ! empty($row->dt_solicitacao_raw) ? Carbon::parse($row->dt_solicitacao_raw)->format('d/m/Y H:i') : null,
            'dt_liberacao' => ! empty($row->dt_liberacao_raw) ? Carbon::parse($row->dt_liberacao_raw)->format('d/m/Y H:i') : null,
            'classificacao' => ! empty($row->ds_grupo_lab) ? trim((string) $row->ds_grupo_lab) : null,
            'status_laudo' => ! empty($row->ds_status_laudo) ? trim((string) $row->ds_status_laudo) : null,
            'tempo_pendente' => $this->formatPendingDurationFromDate(
                ! empty($row->dt_solicitacao_raw) ? (string) $row->dt_solicitacao_raw : (! empty($row->scheduled_raw) ? (string) $row->scheduled_raw : null)
            ),
            'resultado_laudo' => ! empty($row->ds_resultado_laudo) ? trim((string) $row->ds_resultado_laudo) : null,
            'dt_resultado' => ! empty($row->dt_resultado) ? Carbon::parse($row->dt_resultado)->format('d/m/Y H:i') : null,
            'foi_executado_sem_baixa' => (int) ($row->foi_executado_sem_baixa ?? 0) === 1,
            'exame_coletado_em_prescricao_mais_nova' => (int) ($row->exame_coletado_em_prescricao_mais_nova ?? 0) === 1,
            'prescricao_mais_nova_pendente_info' => ! empty($row->prescricao_mais_nova_pendente_info) ? (string) $row->prescricao_mais_nova_pendente_info : null,
        ];
    }

    public function formatHemotherapy(object $row): array
    {
        $volume = null;
        if (! empty($row->qty)) {
            $volume = trim($row->qty.(! empty($row->unit_measure) ? ' '.$row->unit_measure : ''));
        }

        return [
            'id' => (int) ($row->id ?? 0),
            'name' => $row->name ?? 'Hemocomponente',
            'tipo_code' => $row->secondary_info ?? null,
            'prescriber_id' => ! empty($row->prescriber_id) ? (string) $row->prescriber_id : null,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
            'is_urgent' => ($row->flag1 ?? 'N') === 'S',
            'schedule' => $row->schedule ?? null,
            'volume' => $volume,
            'observation' => ! empty($row->observation) ? $row->observation : null,
            'prescriber' => $row->professional_name ?? null,
            'dt_start' => $row->dt_start ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end' => $row->dt_end ? Carbon::parse($row->dt_end)->format('d/m/Y') : null,
            'has_details' => ! empty($row->observation),
        ];
    }

    public function formatSurgery(object $row): array
    {
        $caraterCode = (string) ($row->flag1 ?? '');
        $carater = ! empty($row->carater_label) ? trim((string) $row->carater_label) : ($caraterCode !== '' ? $caraterCode : 'Não informado');
        $is_urgent = in_array($caraterCode, ['U', 'M']);

        $statusRaw = (string) ($row->status_raw ?? '');
        $status = ! empty($row->status_label) ? trim((string) $row->status_label) : ($statusRaw !== '' ? $statusRaw : 'Aguardando');

        $name = $this->normalizeSurgeryDescription((string) ($row->name ?? 'Cirurgia não especificada'));
        $surgeryObservation = $this->filterSensitiveSurgeryObservation($row->observation ?? null);

        return [
            'id' => (int) ($row->id ?? 0),
            'name' => $name,
            'carater' => $carater,
            'status' => $status,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
            'is_urgent' => $is_urgent,
            'dt' => $row->schedule ?? null,
            'sala' => ! empty($row->extra1) ? 'Sala '.$row->extra1 : null,
            'description' => ! empty($row->extra2) ? $row->extra2 : null,
            'tipo_cirurgia_codigo' => ! empty($row->extra3) ? (int) $row->extra3 : null,
            'local' => ! empty($row->extra4) ? trim((string) $row->extra4) : (! empty($row->extra2) ? trim((string) $row->extra2) : null),
            'observation' => ! empty($row->observation) ? $row->observation : null,
            'observacoes' => $surgeryObservation,
            'has_details' => ! empty($row->observation) || ! empty($surgeryObservation),
        ];
    }

    public function formatChemotherapy(object $row): array
    {
        return [
            'id' => (int) ($row->id ?? 0),
            'patient_person_id' => ! empty($row->patient_person_id) ? (string) $row->patient_person_id : null,
            'name' => $row->name ?? 'Quimioterapia',
            'secondary_info' => ! empty($row->secondary_info) ? $row->secondary_info : null,
            'protocol' => ! empty($row->extra1) ? $row->extra1 : null,
            'cycle' => ! empty($row->extra2) ? (int) $row->extra2 : null,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
            'scheduled' => $row->schedule ?? null,
            'local' => ! empty($row->observation) ? $row->observation : null,
            'prescriber' => ! empty($row->professional_name) ? $row->professional_name : null,
            'dt_start' => $row->dt_start ? Carbon::parse($row->dt_start)->format('d/m/Y H:i') : null,
            'has_details' => ! empty($row->observation) || ! empty($row->professional_name),
        ];
    }

    public function formatGasotherapy(object $row): array
    {
        return [
            'id' => (int) ($row->id ?? 0),
            'gas_code' => ! empty($row->gas_code) ? (string) $row->gas_code : null,
            'prescriber_id' => ! empty($row->prescriber_id) ? (string) $row->prescriber_id : null,
            'tipo_gas' => $row->tipo_gas ?? null,
            'modalidade' => $row->modalidade ?? null,
            'modo_administracao' => $row->modo_administracao ?? null,
            'quantidade' => $row->quantidade ?? null,
            'unidade' => $row->unidade ?? null,
            'fio2' => $row->fio2 ?? null,
            'fluxo_inspiratorio' => $row->fluxo_inspiratorio ?? null,
            'pip' => $row->pip ?? null,
            'peep' => $row->peep ?? null,
            'volume_corrente' => $row->volume_corrente ?? null,
            'freq_ventilatoria' => $row->freq_ventilatoria ?? null,
            'pressao_suporte' => $row->pressao_suporte ?? null,
            'equipamento_1' => $row->equipamento_1 ?? null,
            'equipamento_2' => $row->equipamento_2 ?? null,
            'equipamento_3' => $row->equipamento_3 ?? null,
            'horarios' => $row->horarios ?? null,
            'dt_inicio' => $row->dt_inicio ? Carbon::parse($row->dt_inicio)->format('d/m/Y') : null,
            'dt_fim' => $row->dt_fim ? Carbon::parse($row->dt_fim)->format('d/m/Y') : null,
            'urgente' => ($row->urgente ?? 'N') === 'S',
            'se_necessario' => ($row->se_necessario ?? 'N') === 'S',
            'a_criterio_medico' => ($row->a_criterio_medico ?? 'N') === 'S',
            'observacao' => ! empty($row->observacao) ? $row->observacao : null,
            'justificativa' => ! empty($row->justificativa) ? $row->justificativa : null,
            'prescriber' => $row->professional_name ?? null,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
        ];
    }

    public function formatDialysis(object $row): array
    {
        $modalidade = ($row->ie_hemodialise ?? 'S') === 'S'
            ? 'Hemodiálise'
            : 'Diálise Peritoneal';

        $diasMap = [
            'dia_seg' => 'Seg',
            'dia_ter' => 'Ter',
            'dia_qua' => 'Qua',
            'dia_qui' => 'Qui',
            'dia_sex' => 'Sex',
            'dia_sab' => 'Sáb',
            'dia_dom' => 'Dom',
        ];
        $diasSemana = [];
        foreach ($diasMap as $col => $label) {
            if (($row->$col ?? 'N') === 'S') {
                $diasSemana[] = $label;
            }
        }

        return [
            'id' => (int) ($row->id ?? 0),
            'prescriber_id' => ! empty($row->prescriber_id) ? (string) $row->prescriber_id : null,
            'modalidade' => $modalidade,
            'sessoes_por_semana' => $row->sessoes_por_semana ?? null,
            'duracao_sessao' => $row->duracao_sessao ?? null,
            'dias_semana' => implode(', ', $diasSemana) ?: null,
            'fluxo_sangue' => $row->fluxo_sangue ?? null,
            'ktv' => $row->ktv ?? null,
            'ultrafiltracao' => $row->ultrafiltracao ?? null,
            'horarios' => $row->horarios ?? null,
            'dt_inicio' => $row->dt_inicio ? Carbon::parse($row->dt_inicio)->format('d/m/Y') : null,
            'dt_fim' => $row->dt_fim ? Carbon::parse($row->dt_fim)->format('d/m/Y') : null,
            'observacao' => ! empty($row->observacao) ? $row->observacao : null,
            'justificativa' => ! empty($row->justificativa) ? $row->justificativa : null,
            'prescriber' => $row->professional_name ?? null,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
        ];
    }

    public function formatNutritionItem(object $row): array
    {
        $isFasting = in_array((string) ($row->flag1 ?? ''), ['J', 'S'], true)
            || ! empty($row->fasting_goal)
            || ! empty($row->fasting_type)
            || ! empty($row->fasting_goal_id)
            || ! empty($row->fasting_type_id)
            || ! empty($row->fasting_start)
            || ! empty($row->fasting_end);

        if ($isFasting) {
            $type = 'Fasting';
            $fastingLabel = collect([$row->fasting_goal ?? null, $row->fasting_type ?? null])
                ->filter()
                ->map(fn ($value) => trim((string) $value))
                ->implode(' · ');
            $displayName = $fastingLabel !== '' ? $fastingLabel : 'Jejum';
        } elseif (($row->flag3 ?? '') === 'S') {
            $type = 'Enteral';
            $displayName = $row->name ?? 'Nutrição Enteral';
        } elseif (($row->flag4 ?? '') === 'S') {
            $type = 'Special';
            $displayName = $row->name ?? 'Dieta Especial';
        } else {
            $type = 'Diet';
            $displayName = $row->name ?? 'Dieta';
        }

        $volume = null;
        if (! empty($row->qty)) {
            $volume = trim($row->qty.(! empty($row->unit_measure) ? ' '.$row->unit_measure : ''));
        }

        $products = [];
        foreach ([1, 2, 3, 4, 5] as $idx) {
            $productName = $row->{"product_{$idx}"} ?? null;
            if (empty($productName)) {
                continue;
            }
            $dose = $row->{"product_{$idx}_qty"} ?? null;
            $products[] = [
                'name' => trim((string) $productName),
                'dose' => ! empty($dose) ? trim((string) $dose) : null,
            ];
        }

        $deliveryMode = collect([
            ($row->flag7 ?? 'N') === 'S' ? 'Contínuo' : null,
            ($row->flag6 ?? 'N') === 'S' ? 'Bolus' : null,
            ($row->flag5 ?? 'N') === 'S' ? 'Bomba de infusão' : null,
            ($row->flag8 ?? 'N') === 'S' ? 'Leite materno' : null,
        ])->filter()->values()->all();

        return [
            'id' => (int) ($row->id ?? 0),
            'name' => $displayName,
            'type' => $type,
            'diet_code' => ! empty($row->diet_code) ? (string) $row->diet_code : null,
            'material_code' => ! empty($row->material_code) ? (string) $row->material_code : null,
            'is_fasting' => $isFasting,
            'observation' => ! empty($row->observation) ? $row->observation : null,
            'allergies' => ! empty($row->note_text) ? $row->note_text : null,
            'schedule' => $row->schedule ?? ($row->first_hour ?? null),
            'volume' => $volume,
            'total_volume' => ! empty($row->total_volume) ? trim((string) $row->total_volume) : null,
            'total_kcal' => ! empty($row->total_kcal) ? trim((string) $row->total_kcal) : null,
            'infusion_speed' => ! empty($row->infusion_speed) ? trim((string) $row->infusion_speed) : null,
            'route' => ! empty($row->route) ? trim((string) $row->route) : null,
            'route_code' => ! empty($row->route_code) ? trim((string) $row->route_code) : null,
            'interval_code' => ! empty($row->interval_code) ? trim((string) $row->interval_code) : null,
            'interval_description' => ! empty($row->interval_description)
                ? trim((string) $row->interval_description)
                : (! empty($row->interval_code) ? trim((string) $row->interval_code) : null),
            'guidance' => ! empty($row->guidance) ? $row->guidance : null,
            'justification' => ! empty($row->justification) ? $row->justification : null,
            'delivery_mode' => $deliveryMode,
            'products' => $products,
            'prescriber' => $row->professional_name ?? null,
            'nutritionist' => ! empty($row->nutritionist_name)
                ? trim((string) $row->nutritionist_name)
                : ($row->professional_name ?? null),
            'nutritionist_id' => ! empty($row->nutritionist_id) ? (string) $row->nutritionist_id : null,
            'fasting_goal' => ! empty($row->fasting_goal) ? trim((string) $row->fasting_goal) : null,
            'fasting_type' => ! empty($row->fasting_type) ? trim((string) $row->fasting_type) : null,
            'fasting_start' => ! empty($row->fasting_start ?? null) ? (string) $row->fasting_start : null,
            'fasting_end' => ! empty($row->fasting_end ?? null) ? (string) $row->fasting_end : null,
            'dt_start' => ! empty($row->dt_start ?? null) ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end' => ! empty($row->dt_end ?? null) ? Carbon::parse($row->dt_end)->format('d/m/Y') : null,
            'has_details' => ! empty($row->observation)
                || ! empty($row->note_text)
                || ! empty($row->guidance)
                || ! empty($row->justification)
                || ! empty($row->route)
                || ! empty($row->infusion_speed)
                || ! empty($row->total_kcal)
                || ! empty($row->nutritionist_name)
                || ! empty($row->fasting_goal)
                || ! empty($row->fasting_type)
                || ! empty($products),
        ];
    }

    public function organizeNutritionByShift($nutrition): array
    {
        $items = $nutrition->map(fn ($r) => $this->formatNutritionItem($r))->values()->all();

        return ['count' => count($items), 'items' => $items];
    }

    private function formatPendingDurationFromDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            $start = Carbon::parse($date);
            $now = now();

            if ($start->greaterThan($now)) {
                $diffMinutes = (int) $now->diffInMinutes($start);
                if ($diffMinutes < 60) {
                    return 'em '.$diffMinutes.'min';
                }
                $diffHours = intdiv($diffMinutes, 60);

                return $diffHours < 24 ? 'em '.$diffHours.'h' : 'em '.intdiv($diffHours, 24).'d';
            }

            $diffMinutes = (int) $start->diffInMinutes($now);
            if ($diffMinutes < 60) {
                return $diffMinutes.'min em aberto';
            }
            $diffHours = intdiv($diffMinutes, 60);

            return $diffHours < 24 ? $diffHours.'h em aberto' : intdiv($diffHours, 24).'d em aberto';
        } catch (\Throwable) {
            return null;
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

        return $normalized !== '' ? $normalized : 'Informações disponíveis no prontuário';
    }
}
