<?php

namespace App\Enums\Huddle;

/**
 * Catálogo de motivos de "dia vermelho" (red day) da metodologia Red2Green.
 *
 * Espelha os motivos da Tabela 3 do estudo de referência (BMJ Open Quality 2024).
 * Cada motivo pertence a uma RedReasonCategory (ver category()), o que permite
 * agregar métricas tanto no nível fino (motivo) quanto no gerencial (categoria).
 *
 * O `value` string é persistido em huddle_red_reasons.reason_code (cast do Eloquent);
 * valores são estáveis e não devem mudar. Novos motivos podem ser adicionados sem
 * quebrar registros existentes.
 */
enum RedReason: string
{
    // Equipe multidisciplinar
    case LackOfSeniorReview = 'lack_of_senior_review';
    case WaitSpecialtyConsult = 'wait_specialty_consult';
    case WaitTreatmentDecision = 'wait_treatment_decision';
    case LackOfDischargePlanning = 'lack_of_discharge_planning';
    case ConservativeAttitude = 'conservative_attitude';
    case TeamCommunicationFailure = 'team_communication_failure';

    // Exames e laudos
    case WaitRadiology = 'wait_radiology';
    case WaitCardiology = 'wait_cardiology';
    case WaitEndoscopy = 'wait_endoscopy';
    case WaitPathology = 'wait_pathology';
    case WaitLaboratory = 'wait_laboratory';

    // Externo à instituição
    case WaitAlternateCareOrFamily = 'wait_alternate_care_or_family';
    case WaitOutpatientDialysis = 'wait_outpatient_dialysis';
    case ExternalOther = 'external_other';

    // Cirurgia e procedimentos invasivos
    case WaitSurgeryOrProcedure = 'wait_surgery_or_procedure';

    // Diversos
    case Miscellaneous = 'miscellaneous';

    /**
     * Categoria à qual o motivo pertence.
     */
    public function category(): RedReasonCategory
    {
        return match ($this) {
            self::LackOfSeniorReview,
            self::WaitSpecialtyConsult,
            self::WaitTreatmentDecision,
            self::LackOfDischargePlanning,
            self::ConservativeAttitude,
            self::TeamCommunicationFailure => RedReasonCategory::MultidisciplinaryTeam,

            self::WaitRadiology,
            self::WaitCardiology,
            self::WaitEndoscopy,
            self::WaitPathology,
            self::WaitLaboratory => RedReasonCategory::Tests,

            self::WaitAlternateCareOrFamily,
            self::WaitOutpatientDialysis,
            self::ExternalOther => RedReasonCategory::External,

            self::WaitSurgeryOrProcedure => RedReasonCategory::Surgery,

            self::Miscellaneous => RedReasonCategory::Misc,
        };
    }

    /**
     * Rótulo em português para exibição.
     */
    public function label(): string
    {
        return match ($this) {
            self::LackOfSeniorReview => 'Falta de revisão sênior',
            self::WaitSpecialtyConsult => 'Aguardando parecer/especialidade',
            self::WaitTreatmentDecision => 'Aguardando decisão de conduta',
            self::LackOfDischargePlanning => 'Falta de plano de alta',
            self::ConservativeAttitude => 'Atitude conservadora quanto à alta',
            self::TeamCommunicationFailure => 'Falha de comunicação entre equipes',
            self::WaitRadiology => 'Aguardando radiologia',
            self::WaitCardiology => 'Aguardando cardiologia',
            self::WaitEndoscopy => 'Aguardando endoscopia/colonoscopia',
            self::WaitPathology => 'Aguardando patologia',
            self::WaitLaboratory => 'Aguardando laboratório',
            self::WaitAlternateCareOrFamily => 'Aguardando nível alternativo de cuidado/família',
            self::WaitOutpatientDialysis => 'Aguardando diálise ambulatorial',
            self::ExternalOther => 'Outros (externo)',
            self::WaitSurgeryOrProcedure => 'Aguardando cirurgia/procedimento',
            self::Miscellaneous => 'Diversos',
        };
    }

    /**
     * Motivos agrupados por categoria — útil para montar o seletor no modal.
     *
     * @return array<string, array<int, self>>
     */
    public static function groupedByCategory(): array
    {
        $grouped = [];

        foreach (self::cases() as $reason) {
            $grouped[$reason->category()->value][] = $reason;
        }

        return $grouped;
    }
}
