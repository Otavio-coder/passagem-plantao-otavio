<?php

namespace App\Models\EMR\Scales;

class Pain extends BaseScale
{
    protected $table = 'tasy.atendimento_sinal_vital';

    protected static $valueColumn = 'qt_escala_dor';
    protected static $dateColumn = 'dt_sinal_vital';

    public static function classificationFromScore($score)
    {
        $num = self::extractScore($score);
        if ($num === null) return null;
        if ($num == 0) return 'Sem Dor';
        if ($num >= 7) return 'Dor Intensa';
        if ($num >= 4) return 'Dor Moderada';
        return 'Dor Leve';
    }

    public static function stylingFromScore($score)
    {
        return \App\Support\Scales\ScaleStyleHelper::painRisk(self::extractScore($score), false);
    }

}
