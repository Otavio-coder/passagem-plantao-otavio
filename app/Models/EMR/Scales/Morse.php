<?php

namespace App\Models\EMR\Scales;

class Morse extends BaseScale
{
    protected $table = 'tasy.escala_morse';

    protected static $valueColumn = 'qt_pontuacao';
    protected static $dateColumn = 'dt_avaliacao';

    public static function classificationFromScore($score)
    {
        $num = self::extractScore($score);
        if ($num === null) return null;
        if ($num >= 45) return 'Alto Risco';
        if ($num >= 25) return 'Risco Moderado';
        return 'Baixo Risco';
    }

    public static function stylingFromScore($score)
    {
        return \App\Support\Scales\ScaleStyleHelper::morseRisk(self::extractScore($score), false);
    }

}

