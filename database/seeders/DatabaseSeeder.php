<?php

namespace Database\Seeders;

use App\Models\System\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        try {

            Cache::clear();

            $userFirst = env('USER_FIRST');

            if ( $userFirst ) {

                $ldapUser = \LdapRecord\Models\ActiveDirectory\User::query()->findBy('samaccountname', $userFirst);

                if ( $ldapUser ) {

                    DB::table('roles')->delete();
                    DB::table('permissions')->delete();

                    $roleAdmin = Role::create(['name' => 'Administrador']);
                    Permission::create(['name' => 'ver usuarios']);
                    Permission::create(['name' => 'criar usuarios']);
                    Permission::create(['name' => 'editar usuarios']);
                    Permission::create(['name' => 'ver perfis']);
                    Permission::create(['name' => 'criar perfis']);
                    Permission::create(['name' => 'editar perfis']);
                    Permission::create(['name' => 'ver logs']);
                    Permission::create(['name' => 'acessar como']);

                    $permissions = Permission::all()->pluck('name')->toArray();

                    $roleAdmin->syncPermissions($permissions);


                    $user = User::create([
                        'username'  => $ldapUser->getFirstAttribute('samaccountname'),
                        'name'      => $ldapUser->getFirstAttribute('displayname'),
                        'email'     => $ldapUser->getFirstAttribute('mail'),
                        'guid'      => $ldapUser->getConvertedGuid(),
                        'domain'    => 'default',
                    ]);

                    $user->assignRole('Administrador');

                    $this->command->info( "Sucesso! Você já pode logar na aplicação com o usuário: {$user->username}" );
                } else {
                    $this->command->alert( "Usuário '{$userFirst}' não encontrado no AD!" );
                }


            } else {
                $this->command->alert( "Por favor informe um usuário válido do AD no atributo 'USER_FIRST' no arquivo .env" );
            }

        } catch ( \Exception $exception ) {
            $this->command->error( $exception->getMessage() );
        }
    }
}
