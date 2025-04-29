<?php

namespace App\Services;

use App\Models\System\Log;

class Logger
{

    /**
     * Registra um log
     *
     * @param $type
     * @param $message
     */
    public static function write( $type, $message, $details = null )
    {

        Log::create([
            'type'    => $type,
            'message' => $message,
            'details' => $details
        ]);
    }

    /**
     * Regirstra um log de informação
     *
     * @param $message
     */
    public static function info( $message, $details = null )
    {

        self::write( 'I', $message, $details );
    }

    /**
     * Regirstra um log de aviso
     *
     * @param $message
     */
    public static function warning( $message, $details = null )
    {

        self::write( 'W', $message );
    }

    /**
     * Regirstra um log de erro
     *
     * @param $message
     */
    public static function error( $message, $details = null )
    {

        self::write( 'E', $message, $details );
    }

    /**
     * Limpa os registros de log da base, mantendo sempre os últimos 60 dias.
     *
     * @return void
     */
    public static function erase()
    {

        Log::where( 'created_at', '<=', now()->addDays(-60)->startOfDay() )
            ->delete();
    }

}
