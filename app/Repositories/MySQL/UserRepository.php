<?php

namespace App\Repositories\MySQL;

use LdapRecord\Models\ActiveDirectory\User;

class UserRepository extends BaseRepository
{

    public function create( $request )
    {

	$ldapUser = User::query()->findBy( 'samaccountname', $request->username );

        $user = $this->store( [
            'name'          => $request->name,
            'username'      => $request->username,
            'email'         => $request->email,
            'created_by'    => auth()->user()->id,
	    'guid'          => $ldapUser->getConvertedGuid(),
	    'domain'        => 'default'
        ], true );

        if ( is_object($user) && method_exists($user, 'syncRoles') ) {
            $user->syncRoles( $request->profile );
        }

        return $user;
    }

    public function change( $request )
    {
        // Só atualiza status se vier explicitamente no request (evita null corrupting a coluna)
        $data = [];
        if ($request->filled('status') && in_array($request->status, ['A', 'I'])) {
            $data['status'] = $request->status;
        }

        if (! empty($data)) {
            $user = $this->update($request->user_id, $data, true);
        } else {
            $user = $this->findByPk($request->user_id);
        }

        if ( is_object($user) && method_exists($user, 'syncRoles') && $request->has('edit_profile_present') ) {
            $roles = $request->input('edit_profile', []);
            $user->syncRoles( $roles );
        }

        return $user;
    }

    public function checkUserExist( $username )
    {

        return $this->query()
            ->where( 'username', $username )
            ->exists();
    }

}
