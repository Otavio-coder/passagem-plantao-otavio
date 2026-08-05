<?php

namespace App\Enums\Huddle;

/**
 * Classificação de risco da unidade no Huddle de Segurança (Eixo 4).
 *
 * Segue o modelo de gestão à vista: verde (estável), amarelo (atenção) e
 * vermelho (crítico). O `value` string é persistido direto na coluna
 * huddle_safety_assessments.unit_classification (cast nativo do Eloquent).
 */
enum UnitClassification: string
{
    case Verde = 'verde';
    case Amarelo = 'amarelo';
    case Vermelho = 'vermelho';

    /**
     * Rótulo em português para exibição.
     */
    public function label(): string
    {
        return match ($this) {
            self::Verde => 'Verde',
            self::Amarelo => 'Amarelo',
            self::Vermelho => 'Vermelho',
        };
    }

    /**
     * Classes Tailwind do botão/badge de cada classificação (estado selecionado).
     */
    public function buttonStyling(): string
    {
        return match ($this) {
            self::Verde => 'bg-green-500 text-white border-green-500',
            self::Amarelo => 'bg-amber-400 text-amber-950 border-amber-400',
            self::Vermelho => 'bg-red-500 text-white border-red-500',
        };
    }
}
