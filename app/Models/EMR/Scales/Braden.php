<?php

namespace App\Models\EMR\Scales;

class Braden extends BaseScale
{
    protected $table = 'tasy.atend_escala_braden';

    protected static $valueColumn = 'qt_ponto';
    protected static $dateColumn = 'dt_avaliacao';

    public static function classificationFromScore($score)
    {
        $num = self::extractScore($score);
        if ($num === null) return null;
        if ($num <= 12) return 'Alto Risco';
        if ($num <= 14) return 'Risco Moderado';
        return 'Baixo Risco';
    }

    public static function stylingFromScore($score)
    {
        return \App\Support\Scales\ScaleStyleHelper::bradenRisk(self::extractScore($score), false);
    }

}
