<?php

namespace Database\Seeders;

use App\Models\System\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        try {

            $userFirst = env('USER_FIRST');

            if (! $userFirst) {
                $this->command->alert("Por favor, informe um usuário válido do AD no atributo 'USER_FIRST' no arquivo .env");
                return;
            }

            $ldapUser = \LdapRecord\Models\ActiveDirectory\User::query()->findBy('samaccountname', $userFirst);

            if (! $ldapUser) {
                $this->command->alert("Usuário '{$userFirst}' não encontrado no AD!");
                return;
            }

            // Limpa dados antigos (desabilita foreign key checks temporariamente)
            DB::table('model_has_roles')->delete();
            DB::table('model_has_permissions')->delete();
            DB::table('role_has_permissions')->delete();
            DB::table('roles')->delete();
            DB::table('permissions')->delete();

            // Define as permissões disponíveis no sistema
            $permissions = [
                'ver usuarios',
                'criar usuarios',
                'editar usuarios',
                'acessar como',
                'ver perfis',
                'criar perfis',
                'editar perfis',
                'ver logs',
                'configurar sistema',
            ];

            foreach ($permissions as $perm) {
                Permission::firstOrCreate(['name' => $perm]);
            }

            // Cria papéis (roles)
            $roleAdmin = Role::firstOrCreate(['name' => 'Administrador']);
            $roleGestor = Role::firstOrCreate(['name' => 'Gestor']);
            $roleUsuario = Role::firstOrCreate(['name' => 'Usuário Padrão']);

            // Permissões por papel
            $roleAdmin->syncPermissions(Permission::all());
            $roleGestor->syncPermissions([
                'ver usuarios',
                'editar usuarios',
                'ver perfis',
                'editar perfis',
                'ver logs',
                'configurar sistema',
            ]);

            // Usuário Padrão → sem permissões
            $roleUsuario->syncPermissions([]);

            // Cria usuário inicial administrador
            $user = User::firstOrCreate(
                ['username' => $ldapUser->getFirstAttribute('samaccountname')],
                [
                    'name'   => $ldapUser->getFirstAttribute('displayname'),
                    'email'  => $ldapUser->getFirstAttribute('mail'),
                    'guid'   => $ldapUser->getConvertedGuid(),
                    'domain' => 'default',
                ]
            );

            $user->assignRole('Administrador');

            $this->command->info("Sucesso! Você já pode logar na aplicação com o usuário: {$user->username}");

        } catch (\Exception $exception) {
            $this->command->error("Erro ao rodar seeder: " . $exception->getMessage());
        }
    }
}
