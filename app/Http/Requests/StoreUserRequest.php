<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name'      => 'required',
            'username'  => 'required|unique:users',
            'email'     => 'required',
            'profile'   => 'required'
        ];
    }

    public function attributes()
    {
        return [
            'name'      => ':Nome',
            'username'  => ':Usuário',
            'email'     => ':E-mail',
            'profile'   => ':Perfil',
        ];
    }
}
