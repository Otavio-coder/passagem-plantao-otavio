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

        if ( $user )
            $user->syncRoles( $request->profile );

        return $user;
    }

    public function change( $request )
    {

        $user = $this->update( $request->user_id, [
            'status' => $request->status,
        ], true );

        if ( $user )
            $user->syncRoles( $request->edit_profile );

        return $user;
    }

    public function checkUserExist( $username )
    {

        return $this->query()
            ->where( 'username', $username )
            ->exists();
    }

}
