<?php

namespace App\Services\MSGraph;

use Illuminate\Support\Facades\Cache;

class AccessTokenGetter
{

    /**
     * Retorna o access token
     *
     * @return string
     */
    public static function get()
    {

        $tokenName = config( 'config.ms_graph_token_name' );

        if ( Cache::has( $tokenName ) ) {

            $token = Cache::get( $tokenName );

            if ( $token->expires_on > now()->timestamp )
                return $token->access_token;

        }

        return AccessTokenRequest::accessToken();
    }
}
