<?php

namespace App\Enums\Huddle;

/**
 * Mapeamento bidirecional entre os itens do Tasy (MED_ITEM_AVALIAR.nr_sequencia)
 * e os itens do checklist local (HuddleChecklistItem).
 *
 * Tipo de avaliação Tasy: 9291 — "Huddle de Gestão de Altas"
 *
 * Cada case representa um item do formulário Tasy. O value é o nr_sequencia
 * no Oracle, permitindo queries diretas. O método checklistItem() retorna
 * o enum local correspondente (null para itens sem equivalente, como o gate
 * de triagem e os campos de recomendação).
 */
enum TasyEvaluationItemMap: int
{
    /** Tipo de avaliação no Tasy (MED_TIPO_AVALIACAO.nr_sequencia) */
    public const TIPO_AVALIACAO = 9291;

    // ── Perguntas com dropdown (Sim/Não) ──────────────────────────────
    case Previsao72h = 122013;         // Gate de triagem — não é item do checklist
    case CriteriosClinicos = 122014;
    case Consultorias = 122015;
    case Transporte = 122016;
    case ExamesLaudo = 122023;
    case Procedimentos = 122024;
    case Terapias = 122025;
    case OrientacaoAlta = 122026;

    // ── Campos de recomendação (texto livre) ────────────────────────────
    // Mapeamento confirmado via layout do formulário Tasy (dropdown → rec):
    case RecAlta72h = 122017;            // Recomendação de Previsao72h (122013)
    case RecProcedimentos = 122018;      // Recomendação de Procedimentos (122024)
    case RecTerapias = 122019;           // Recomendação de Terapias (122025)
    case RecConsultorias = 122020;       // Recomendação de Consultorias (122015)
    case RecOrientacaoAlta = 122021;     // Recomendação de OrientacaoAlta (122026)
    case RecTransporte = 122022;         // Recomendação de Transporte (122016)
    case RecCriteriosClinicos = 122027;  // Recomendação de CriteriosClinicos (122014)
    case RecExamesLaudo = 122028;        // Recomendação de ExamesLaudo (122023)

    /**
     * Retorna o HuddleChecklistItem correspondente, ou null para itens
     * sem equivalente direto (gate de triagem e recomendações).
     */
    public function checklistItem(): ?HuddleChecklistItem
    {
        return match ($this) {
            self::CriteriosClinicos => HuddleChecklistItem::CriteriosClinicos,
            self::ExamesLaudo       => HuddleChecklistItem::ExamesLaudo,
            self::Procedimentos     => HuddleChecklistItem::Procedimentos,
            self::Terapias          => HuddleChecklistItem::Terapias,
            self::Consultorias      => HuddleChecklistItem::Consultorias,
            self::OrientacaoAlta    => HuddleChecklistItem::OrientacaoAlta,
            self::Transporte        => HuddleChecklistItem::Transporte,
            default                 => null,
        };
    }

    /**
     * Indica se o item é uma pergunta com dropdown (Sim/Não) ou texto livre.
     */
    public function isDropdown(): bool
    {
        return match ($this) {
            self::RecAlta72h,
            self::RecProcedimentos,
            self::RecTerapias,
            self::RecConsultorias,
            self::RecOrientacaoAlta,
            self::RecTransporte,
            self::RecCriteriosClinicos,
            self::RecExamesLaudo => false,
            default              => true,
        };
    }

    /**
     * Indica se o item é um campo de recomendação (texto livre).
     */
    public function isRecommendation(): bool
    {
        return ! $this->isDropdown();
    }

    /**
     * Indica se este item é o gate de triagem (previsão 72h).
     */
    public function isTriageGate(): bool
    {
        return $this === self::Previsao72h;
    }

    /**
     * Retorna o nr_sequencia do Tasy a partir de um HuddleChecklistItem.
     */
    public static function fromChecklistItem(HuddleChecklistItem $item): self
    {
        return match ($item) {
            HuddleChecklistItem::CriteriosClinicos => self::CriteriosClinicos,
            HuddleChecklistItem::ExamesLaudo       => self::ExamesLaudo,
            HuddleChecklistItem::Procedimentos     => self::Procedimentos,
            HuddleChecklistItem::Terapias          => self::Terapias,
            HuddleChecklistItem::Consultorias      => self::Consultorias,
            HuddleChecklistItem::OrientacaoAlta    => self::OrientacaoAlta,
            HuddleChecklistItem::Transporte        => self::Transporte,
        };
    }

    /**
     * Todos os itens que são perguntas de checklist (exclui gate e recomendações).
     *
     * @return self[]
     */
    public static function checklistItems(): array
    {
        return array_filter(
            self::cases(),
            fn (self $item) => $item->checklistItem() !== null,
        );
    }

    /**
     * Retorna o item de recomendação (texto livre) associado a um item dropdown.
     *
     * Mapeamento completo confirmado via layout do formulário Tasy:
     *
     *   Dropdown (Sim/Não)              → Recomendação (texto livre)
     *   ─────────────────────────────────────────────────────────────
     *   Previsao72h (122013)            → RecAlta72h (122017)
     *   CriteriosClinicos (122014)      → RecCriteriosClinicos (122027)
     *   ExamesLaudo (122023)            → RecExamesLaudo (122028)
     *   Procedimentos (122024)          → RecProcedimentos (122018)
     *   Terapias (122025)               → RecTerapias (122019)
     *   Consultorias (122015)           → RecConsultorias (122020)
     *   OrientacaoAlta (122026)         → RecOrientacaoAlta (122021)
     *   Transporte (122016)             → RecTransporte (122022)
     */
    public static function recommendationFor(self $dropdown): ?self
    {
        return match ($dropdown) {
            self::Previsao72h       => self::RecAlta72h,
            self::CriteriosClinicos => self::RecCriteriosClinicos,
            self::ExamesLaudo       => self::RecExamesLaudo,
            self::Procedimentos     => self::RecProcedimentos,
            self::Terapias          => self::RecTerapias,
            self::Consultorias      => self::RecConsultorias,
            self::OrientacaoAlta    => self::RecOrientacaoAlta,
            self::Transporte        => self::RecTransporte,
            default                 => null,
        };
    }

    /**
     * Mapeamento reverso: dado um HuddleChecklistItem, retorna o item de
     * recomendação correspondente no Tasy.
     */
    public static function recommendationForChecklist(HuddleChecklistItem $item): ?self
    {
        $dropdown = self::fromChecklistItem($item);

        return self::recommendationFor($dropdown);
    }

    /**
     * Retorna todos os itens dropdown com alias para uso na função tasy.aval().
     *
     * @return array<string, int>  ['alias' => nr_sequencia]
     */
    public static function avalColumns(): array
    {
        return [
            // Dropdowns (Sim/Não)
            'alta_72h'           => self::Previsao72h->value,
            'criterios_clinicos' => self::CriteriosClinicos->value,
            'exames_laudo'       => self::ExamesLaudo->value,
            'procedimentos'      => self::Procedimentos->value,
            'terapias'           => self::Terapias->value,
            'consultorias'       => self::Consultorias->value,
            'orientacao_alta'    => self::OrientacaoAlta->value,
            'transporte'         => self::Transporte->value,

            // Recomendações (texto livre) — mesma ordem dos dropdowns
            'rec_alta_72h'           => self::RecAlta72h->value,
            'rec_criterios_clinicos' => self::RecCriteriosClinicos->value,
            'rec_exames_laudo'       => self::RecExamesLaudo->value,
            'rec_procedimentos'      => self::RecProcedimentos->value,
            'rec_terapias'           => self::RecTerapias->value,
            'rec_consultorias'       => self::RecConsultorias->value,
            'rec_orientacao_alta'    => self::RecOrientacaoAlta->value,
            'rec_transporte'         => self::RecTransporte->value,
        ];
    }
}
