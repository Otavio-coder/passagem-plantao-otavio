<?php

namespace App\Models\EMR\Scales;

class Mews extends BaseScale
{
    protected $table = 'tasy.escala_mews';

    protected static $valueColumn = 'qt_pontuacao';
    protected static $dateColumn = 'dt_avaliacao';

    public static function classificationFromScore($score)
    {
        $num = self::extractScore($score);
        if ($num === null) return null;
        if ($num >= 5) return 'Crítico';
        if ($num >= 3) return 'Alerta';
        return 'Normal';
    }

    public static function stylingFromScore($score)
    {
        return \App\Support\Scales\ScaleStyleHelper::mewsRisk(self::extractScore($score), false);
    }

}
