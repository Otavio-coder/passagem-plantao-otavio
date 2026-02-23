<?php

namespace App\Models\EMR\Scales;

class VteThrombosis extends BaseScale
{
    protected $table = 'tasy.escala_tev';

    protected static $valueColumn = 'qt_pontuacao';
    protected static $dateColumn = 'dt_avaliacao';

    public static function classificationFromScore($score)
    {
        $num = self::extractScore($score);
        if ($num === null) return null;
        if ($num >= 5) return 'Alto Risco';
        if ($num >= 2) return 'Risco Moderado';
        return 'Baixo Risco';
    }

    public static function stylingFromScore($score)
    {
        if (function_exists('getTevRiskStyling')) {
            return getTevRiskStyling(self::extractScore($score), false);
        }
        return self::stylingFallback();
    }

}
