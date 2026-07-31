<?php

namespace App\Enums\Huddle;

/**
 * Categorias de motivo de "dia vermelho" (red day) na metodologia Red2Green.
 *
 * Baseadas na Tabela 3 do estudo de referência (BMJ Open Quality 2024), que
 * agrupa as causas de permanência injustificada. Cada motivo individual
 * (enum RedReason) pertence a uma destas categorias, permitindo agregar as
 * métricas por origem do atraso (ex.: quanto do gargalo é da equipe vs. exames).
 *
 * O `value` string é persistido em huddle_red_reasons.category (cast do Eloquent),
 * portanto os valores são estáveis e não devem mudar.
 */
enum RedReasonCategory: string
{
    case MultidisciplinaryTeam = 'multidisciplinary_team';
    case Tests = 'tests';
    case External = 'external';
    case Surgery = 'surgery';
    case Misc = 'misc';

    /**
     * Rótulo em português para exibição em cards, filtros e relatórios.
     */
    public function label(): string
    {
        return match ($this) {
            self::MultidisciplinaryTeam => 'Equipe multidisciplinar',
            self::Tests => 'Exames e laudos',
            self::External => 'Externo à instituição',
            self::Surgery => 'Cirurgia e procedimentos',
            self::Misc => 'Diversos',
        };
    }

    /**
     * Cor de destaque (Tailwind) para o chip da categoria, apoiando a leitura
     * rápida de prioridades no painel.
     */
    public function accentClass(): string
    {
        return match ($this) {
            self::MultidisciplinaryTeam => 'bg-blue-100 text-blue-800',
            self::Tests => 'bg-amber-100 text-amber-800',
            self::External => 'bg-purple-100 text-purple-800',
            self::Surgery => 'bg-rose-100 text-rose-800',
            self::Misc => 'bg-gray-100 text-gray-800',
        };
    }
}
