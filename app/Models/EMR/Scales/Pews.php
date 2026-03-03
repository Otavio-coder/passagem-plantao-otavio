<?php

namespace App\Models\EMR\Scales;

class Pews extends BaseScale
{
    protected $table = 'tasy.escala_pews';

    protected static $valueColumn = 'qt_pontuacao';
    protected static $dateColumn = 'dt_avaliacao';

    public static function classificationFromScore($score)
    {
        $num = self::extractScore($score);
        if ($num === null) return null;
        if ($num >= 7) return 'Crítico';
        if ($num >= 4) return 'Alerta';
        return 'Normal';
    }

    public static function stylingFromScore($score)
    {
        return \App\Support\Scales\ScaleStyleHelper::pewsRisk(self::extractScore($score), false);
    }

}


