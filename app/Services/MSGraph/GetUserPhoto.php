<?php

namespace App\Services\MSGraph;

use Illuminate\Support\Facades\Http;

/**
 * Trait GetUserPhoto
 *
 * Para o correto funcionamento da trait, é preciso que toda classe que a use tenha um atributo chamado username.
 * Esse atributo pode ser um atributo de banco de dados ou um accessor do Laravel.
 *
 * @package App\Services\MSGraph
 */
trait GetUserPhoto
{

    /**
     * Retorna a foto do usuário
     *
     * @param string $size
     * @return string
     */
    public function photo( $size = "64x64" )
    {

        $token = AccessTokenGetter::get();

        $photo = Http::withToken( $token )->get(
            "https://graph.microsoft.com/v1.0/users/{$this->username}@santacasa.org.br/photos/$size/\$value"
        );

        if ( $photo->status() === 404 )
            return "";

        return base64_encode( $photo->body() );
    }

}
