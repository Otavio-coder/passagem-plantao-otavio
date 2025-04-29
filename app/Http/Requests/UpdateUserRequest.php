<?php

namespace App\Http\Requests;

use App\Services\UsesRepositories;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{

    use UsesRepositories;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {

        $user = $this->users()->findByPk( request('user_id') );

        return [
            'user_id'       => [
                'required',
                function( $attribute, $value, $fail ) use ($user) {
                    if ( $user->hasRole('Administrador') and !auth()->user()->hasRole('Administrador') )
                        $fail('Você não tem permissão suficiente para editar este usuário');
                }
            ],
            'edit_profile' => [ 'required' ]
        ];
    }

    public function messages()
    {
        return [
            'edit_profile.required' => 'Você deve selecionar um perfil para o usuário'
        ];
    }

}
