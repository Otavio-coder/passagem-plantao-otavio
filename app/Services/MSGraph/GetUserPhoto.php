<?php

namespace App\Services\MSGraph;

use Illuminate\Support\Facades\Http;

trait GetUserPhoto
{
    /**
     * Retorna a foto do usuário
     *
     * @param string $size
     * @return string
     */
    public function photo($size = "64x64")
    {
        // Se já tem foto válida no banco, retorna
        if (!empty($this->photo) && $this->hasValidPhoto()) {
            return $this->photo;
        }

        $token = AccessTokenGetter::get();

        $photo = \Illuminate\Support\Facades\Http::withToken($token)->get(
            "https://graph.microsoft.com/v1.0/users/{$this->username}@santacasa.org.br/photos/$size/\$value"
        );

        if ($photo->status() === 404) {
            return "";
        }

        // Check if response is successful and contains valid image data
        if ($photo->status() !== 200) {
            return "";
        }

        $responseBody = $photo->body();
        
        // Check if response contains error JSON instead of image data
        $jsonData = json_decode($responseBody, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($jsonData['error'])) {
            // It's an error response, don't save it
            \Illuminate\Support\Facades\Log::warning('MS Graph photo error for user', [
                'username' => $this->username,
                'error' => $jsonData['error']
            ]);
            
            // Clear invalid photo data if it exists
            if ($this instanceof \Illuminate\Database\Eloquent\Model && !empty($this->photo)) {
                $this->photo = null;
                $this->save();
            }
            
            return "";
        }

        $base64 = base64_encode($responseBody);

        if ($this instanceof \Illuminate\Database\Eloquent\Model) {
            $this->photo = $base64;
            $this->save();
        }

        return $base64;
    }
}