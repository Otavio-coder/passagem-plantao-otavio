<?php

namespace App\Http\Controllers;

use Adldap\Laravel\Facades\Adldap;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\Logger;
use App\Services\UsesRepositories;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LdapRecord\Models\ActiveDirectory\User;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{

    use UsesRepositories;

    public function index()
    {

        $users = $this->users()->all();

        $roles = auth()->user()->hasRole('Administrador') ? Role::all() :
            Role::where( 'name', '<>', 'Administrador' )->get();

        return view( 'users.index', compact('users','roles') );
    }

    public function createUser( StoreUserRequest $request )
    {

        try {

            if ( !$this->users()->create( $request ) )
                return redirect()->back()->with([
                    'status' => 'danger',
                    'message' => 'Não foi possível criar o usuário. Favor contatar o administrador'
                ]);

        } catch ( \Exception $exception ) {

            Log::error( "Ocorreu um erro ao criar o usuário: {$exception->getMessage()}" );

            return redirect()->back()->with([
                'status' => 'danger',
                'message' => 'Ocorreu um erro ao criar o usuário. Favor contatar o administrador'
            ]);

        }

        return redirect()->back()->with([
            'status' => 'success',
            'message' => 'Usuário criado com sucesso'
        ]);

    }

    public function updateUser( UpdateUserRequest $request )
    {

        try {

            $this->users()->change( $request );

        } catch ( QueryException $exception ) {

            Log::error( "Ocorreu um erro ao atualizar o usuário: {$exception->getMessage()}" );

            return redirect()->back()->with([
                'status' => 'danger',
                'message' => 'Ocorreu um erro ao atualizar o usuário. Favor contatar o administrador'
            ]);

        }

        return redirect()->back()->with([
            'status' => 'success',
            'message' => 'Usuário atualizado com sucesso'
        ]);

    }

    public function searchUserAD( Request $request )
    {

        $findUsers = User::query()
            ->select( ['userprincipalname','samaccountname','displayname'] )
            ->where( 'cn', 'contains', $request->q )
            ->limit(10)
            ->get();

        $users = [];

        if ( count($findUsers) > 0 ){

            foreach ( $findUsers as $findUser ) {

                $users[] = [
                    'name'      => ucwords(strtolower($findUser->displayname[0])),
                    'username'  => strtolower($findUser->samaccountname[0]),
                    'email'     => strtolower($findUser->userprincipalname[0])
                ];

            }

        }

        return response()->json( $users );

    }

    public function accessAs( Request $request )
    {

        $userAccess = $this->users()->findByPk( $request->access_user_id );
        $authUser = auth()->user();
        $detailsLog = [
            'auth_user' => $authUser->id,
            'user_access' => $userAccess->id
        ];

        auth()->login( $userAccess );

        Logger::info( "Sistema acessado com outro usuário", json_encode($detailsLog) );

        return redirect()->route( 'home' );
    }

}
