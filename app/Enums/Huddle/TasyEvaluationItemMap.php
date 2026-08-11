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
            default             => true,
        };
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
}
