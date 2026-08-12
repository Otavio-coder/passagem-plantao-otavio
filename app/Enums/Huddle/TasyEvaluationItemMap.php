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
    // Mapeamento confirmado via formulário Tasy (dropdown → recomendação):
    case RecAlta72h = 122017;            // Recomendação de Previsao72h (122013)
    case RecProcedimentos = 122018;      // Recomendação de Procedimentos (122024)
    case RecCriteriosClinicos = 122027;  // Recomendação de CriteriosClinicos (122014)
    case RecExamesLaudo = 122028;        // Recomendação de ExamesLaudo (122023)

    // Recomendações cujo pai ainda precisa ser confirmado no formulário Tasy.
    // Pertencem a: Consultorias(122015), Transporte(122016), Terapias(122025)
    //              ou OrientacaoAlta(122026) — verificar via nr_seq_superior.
    case Recomendacao19 = 122019;
    case Recomendacao20 = 122020;
    case Recomendacao21 = 122021;
    case Recomendacao22 = 122022;

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
            self::RecCriteriosClinicos,
            self::RecExamesLaudo,
            self::Recomendacao19,
            self::Recomendacao20,
            self::Recomendacao21,
            self::Recomendacao22 => false,
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
     * Mapeamento confirmado via formulário Tasy:
     *   Previsao72h (122013)      → RecAlta72h (122017)
     *   CriteriosClinicos (122014) → RecCriteriosClinicos (122027)
     *   ExamesLaudo (122023)       → RecExamesLaudo (122028)
     *   Procedimentos (122024)     → RecProcedimentos (122018)
     *
     * Os itens abaixo aguardam confirmação do mapeamento via nr_seq_superior:
     *   Consultorias (122015)      → Recomendacao19|20|21|22 ?
     *   Transporte (122016)        → Recomendacao19|20|21|22 ?
     *   Terapias (122025)          → Recomendacao19|20|21|22 ?
     *   OrientacaoAlta (122026)    → Recomendacao19|20|21|22 ?
     */
    public static function recommendationFor(self $dropdown): ?self
    {
        return match ($dropdown) {
            self::Previsao72h       => self::RecAlta72h,
            self::CriteriosClinicos => self::RecCriteriosClinicos,
            self::ExamesLaudo       => self::RecExamesLaudo,
            self::Procedimentos     => self::RecProcedimentos,
            // TODO: confirmar mapeamento dos 4 restantes via query:
            // SELECT nr_sequencia, ds_item, nr_seq_superior FROM tasy.med_item_avaliar
            // WHERE nr_seq_tipo = 9291 AND ie_situacao = 'A' ORDER BY qt_tab_order
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
            'consultorias'       => self::Consultorias->value,
            'transporte'         => self::Transporte->value,
            'exames_laudo'       => self::ExamesLaudo->value,
            'procedimentos'      => self::Procedimentos->value,
            'terapias'           => self::Terapias->value,
            'orientacao_alta'    => self::OrientacaoAlta->value,

            // Recomendações (texto livre)
            'rec_alta_72h'           => self::RecAlta72h->value,
            'rec_criterios_clinicos' => self::RecCriteriosClinicos->value,
            'rec_exames_laudo'       => self::RecExamesLaudo->value,
            'rec_procedimentos'      => self::RecProcedimentos->value,
            'rec_19'                 => self::Recomendacao19->value,
            'rec_20'                 => self::Recomendacao20->value,
            'rec_21'                 => self::Recomendacao21->value,
            'rec_22'                 => self::Recomendacao22->value,
        ];
    }
}
