<?php

namespace App\Enums\Huddle;

/**
 * Cor do dia na metodologia Red2Green (gestão de altas).
 *
 * Todo paciente inicia o dia em RED; só vira GREEN quando o plano do dia avança
 * a jornada de alta e o paciente ainda necessita de leito agudo.
 *
 * O `value` string é persistido diretamente na coluna `huddle_patient_days.color`
 * (cast nativo do Eloquent), por isso os valores são estáveis e não devem mudar.
 */
enum DayColor: string
{
    case Red = 'red';
    case Green = 'green';

    /**
     * Rótulo em português para exibição.
     */
    public function label(): string
    {
        return match ($this) {
            self::Red => 'Dia vermelho',
            self::Green => 'Dia verde',
        };
    }

    /**
     * Rótulo curto para badge do card.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Red => 'Red',
            self::Green => 'Green',
        };
    }

    /**
     * Classes Tailwind do card, no mesmo padrão do TasyFormatter (gradiente + borda).
     *
     * @return array{gradient: string, border: string, badge: string, text: string}
     */
    public function cardStyling(): array
    {
        return match ($this) {
            self::Green => [
                'gradient' => 'from-green-50 to-green-200',
                'border' => 'border-2 border-green-400',
                'badge' => 'bg-green-500 text-white',
                'text' => 'text-green-700',
            ],
            self::Red => [
                'gradient' => 'from-red-50 to-red-100',
                'border' => 'border-2 border-red-400',
                'badge' => 'bg-red-500 text-white',
                'text' => 'text-red-700',
            ],
        };
    }

    /**
     * A cor oposta — usada na alternância manual da cor do dia pelo enfermeiro.
     */
    public function toggle(): self
    {
        return $this === self::Red ? self::Green : self::Red;
    }
}
