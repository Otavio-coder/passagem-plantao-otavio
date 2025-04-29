<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProfileRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ProfileController extends Controller
{

    public function index()
    {

        $roles = Role::where('name','<>','Administrador')->get();
        $permissions = Permission::all();

        return view( 'profiles.index', compact( 'roles', 'permissions' ) );
    }

    public function create( CreateProfileRequest $request )
    {

        try {

            $role = Role::create( [
                'name' => $request->name
            ] );

            if ( $role )
                $role->syncPermissions( $request->permissions );

        } catch ( \Exception $exception ) {

            Log::error( "Erro ao tentar criar o perfil {$request->name}: {$exception->getMessage()}" );

            return redirect()->back()->with([
                'status' => 'danger',
                'message' => "Erro ao tentar criar o perfil {$request->name}"
            ]);
        }

        return redirect()->back()->with([
            'status' => 'success',
            'message' => 'Perfil criado com sucesso'
        ]);
    }

    public function edit( UpdateProfileRequest $request )
    {

        try {

            $profile = Role::findById( $request->profile );

            if ( $profile->name != 'Administrador' )
                $profile->update([
                    'name' => $request->name
                ]);

            if ( !$request->permissions )
                $profile->syncPermissions(); //remove todas as permissões

            $profile->syncPermissions( $request->permissions ); //sincroniza com as permissões do request

        } catch ( \Exception $exception ) {

            Log::error( "Erro ao tentar atualizar o perfil '{$request->name}': {$exception->getMessage()}" );

            return redirect()->back()->with([
                'status' => 'danger',
                'message' => "Erro ao tentar atualizar o perfil '{$request->name}'"
            ]);
        }

        return redirect()->back()->with([
            'status' => 'success',
            'message' => 'Perfil atualizado com sucesso'
        ]);

    }

}
