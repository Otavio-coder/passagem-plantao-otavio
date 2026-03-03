<?php

namespace App\Support\Scales;

class ScaleStyleHelper
{
    public static function extractScore($scaleValue): ?int
    {
        if ($scaleValue === null || $scaleValue === '') {
            return null;
        }

        if (is_numeric($scaleValue)) {
            return intval($scaleValue);
        }

        if (is_string($scaleValue)) {
            $patterns = [
                '/:\s*(\d+)/',
                '/Score:\s*(\d+)/',
                '/Pontos:\s*(\d+)/',
                '/^(\d+)$/',
                '/\b(\d+)\b/',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $scaleValue, $matches)) {
                    return intval($matches[1]);
                }
            }
        }

        return null;
    }

    /**
     * MEWS Risk Styling
     * ≥5: Crítico (vermelho escuro)
     * 4: Alto (laranja)
     * 3: Alerta (amarelo)
     * 0-2: Normal (cinza claro)
     */
    public static function mewsRisk($score, $forceNormalText = false): array
    {
        $num = self::extractScore($score);
        if ($num === null) {
            return ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
        }

        $textColor = 'text-gray-800';
        if ($num >= 5) return ['bg' => 'bg-red-100',    'border' => 'border-red-300',    'text' => $textColor];
        if ($num == 4) return ['bg' => 'bg-orange-100', 'border' => 'border-orange-300', 'text' => $textColor];
        if ($num == 3) return ['bg' => 'bg-yellow-100', 'border' => 'border-yellow-300', 'text' => $textColor];
        return ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => $textColor];
    }

    /**
     * PEWS Risk Styling (Pediátrico)
     * ≥4: Alto (vermelho)
     * 2-3: Moderado (amarelo)
     * 0-1: Normal (cinza claro)
     */
    public static function pewsRisk($score, $forceNormalText = false): array
    {
        $num = self::extractScore($score);
        if ($num === null) {
            return ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
        }

        $textColor = 'text-gray-800';
        if ($num >= 4) return ['bg' => 'bg-red-100',    'border' => 'border-red-300',    'text' => $textColor];
        if ($num >= 2) return ['bg' => 'bg-yellow-100', 'border' => 'border-yellow-300', 'text' => $textColor];
        return ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => $textColor];
    }

    /**
     * Braden Risk Styling
     * ≤12: Alto Risco (vermelho)
     * 13-14: Moderado (amarelo)
     * 15-18: Leve (cinza)
     * 19-23: Sem risco (verde claro)
     */
    public static function bradenRisk($score, $forceNormalText = false): array
    {
        $num = self::extractScore($score);
        if ($num === null) {
            return ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
        }

        $textColor = 'text-gray-800';
        if ($num <= 12) return ['bg' => 'bg-red-100',    'border' => 'border-red-300',    'text' => $textColor];
        if ($num <= 14) return ['bg' => 'bg-yellow-100', 'border' => 'border-yellow-300', 'text' => $textColor];
        if ($num <= 18) return ['bg' => 'bg-gray-50',    'border' => 'border-gray-300',    'text' => $textColor];
        return ['bg' => 'bg-green-50', 'border' => 'border-green-300', 'text' => $textColor];
    }

    /**
     * Morse Risk Styling
     * ≥45: Alto (vermelho)
     * 25-44: Moderado (amarelo)
     * <25: Baixo (cinza)
     */
    public static function morseRisk($score, $forceNormalText = false): array
    {
        $num = self::extractScore($score);
        if ($num === null) {
            return ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
        }

        $textColor = 'text-gray-800';
        if ($num >= 45) return ['bg' => 'bg-red-100',    'border' => 'border-red-300',    'text' => $textColor];
        if ($num >= 25) return ['bg' => 'bg-yellow-100', 'border' => 'border-yellow-300', 'text' => $textColor];
        return ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => $textColor];
    }

    /**
     * Pain Scale Styling
     * ≥7: Intensa (vermelho)
     * 4-6: Moderada (amarelo)
     * 1-3: Leve (cinza)
     * 0: Sem dor (verde claro)
     */
    public static function painRisk($score, $forceNormalText = false): array
    {
        $num = self::extractScore($score);
        if ($num === null) {
            return ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
        }

        $textColor = 'text-gray-800';
        if ($num >= 7) return ['bg' => 'bg-red-100',    'border' => 'border-red-300',    'text' => $textColor];
        if ($num >= 4) return ['bg' => 'bg-yellow-100', 'border' => 'border-yellow-300', 'text' => $textColor];
        if ($num >= 1) return ['bg' => 'bg-gray-50',    'border' => 'border-gray-300',    'text' => $textColor];
        return ['bg' => 'bg-green-50', 'border' => 'border-green-300', 'text' => $textColor];
    }

    /**
     * TEV Risk Styling
     * ≥7: Alto (vermelho)
     * 3-6: Moderado (amarelo)
     * 0-2: Baixo (cinza)
     */
    public static function tevRisk($score, $forceNormalText = false): array
    {
        $num = self::extractScore($score);
        if ($num === null) {
            return ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
        }

        $textColor = 'text-gray-800';
        if ($num >= 7) return ['bg' => 'bg-red-100',    'border' => 'border-red-300',    'text' => $textColor];
        if ($num >= 3) return ['bg' => 'bg-yellow-100', 'border' => 'border-yellow-300', 'text' => $textColor];
        return ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => $textColor];
    }

    public static function mewsCardGradient($score, $isNewPatient = false): array
    {
        $num = self::extractScore($score);

        if ($isNewPatient) {
            return [
                'gradient' => 'from-green-50 to-green-100',
                'border'   => 'border-2 border-green-400',
                'text'     => 'text-green-800',
            ];
        }

        if ($num === null) {
            return [
                'gradient' => 'from-blue-50 to-blue-100',
                'border'   => 'border border-gray-300',
                'text'     => 'text-gray-800',
            ];
        }

        if ($num >= 5) return ['gradient' => 'from-red-100 to-red-200',    'border' => 'border-2 border-red-500',    'text' => 'text-red-900'];
        if ($num == 4) return ['gradient' => 'from-orange-100 to-orange-200', 'border' => 'border-2 border-orange-500', 'text' => 'text-orange-900'];
        if ($num == 3) return ['gradient' => 'from-yellow-100 to-yellow-200', 'border' => 'border-2 border-yellow-500', 'text' => 'text-yellow-900'];

        return [
            'gradient' => 'from-blue-50 to-blue-100',
            'border'   => 'border border-blue-300',
            'text'     => 'text-gray-800',
        ];
    }
}
