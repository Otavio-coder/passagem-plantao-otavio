<?php

namespace App\Services\MSGraph;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AccessTokenRequest
{

    /**
     * Requisita um access token
     *
     * @return string|null
     */
    public static function accessToken()
    {

        $response = Http::asForm()->post(
            config( 'general.ms_graph_oauth_url' ),
            self::getBody()
        );

        $token = ( object ) $response->json();

        self::storeTokenInCache( $token );

        return $token->access_token;
    }

    /**
     * Retorna o corpo da requisição
     *
     * @return array
     */
    protected static function getBody()
    {

        return $body = [
            'client_id'     => config( 'general.ms_graph_client_id' ),
            'grant_type'    => 'password',
            'resource'      => 'https://graph.microsoft.com',
            'client_secret' => config( 'general.ms_graph_client_secret' ),
            'username'      => config( 'general.ms_graph_username' ),
            'password'      => config( 'general.ms_graph_password' )
        ];
    }

    /**
     * Salva o token no cache
     *
     * @param $token
     */
    protected static function storeTokenInCache( $token )
    {

        Cache::put( config( 'general.ms_graph_token_name' ), $token );

    }

}
