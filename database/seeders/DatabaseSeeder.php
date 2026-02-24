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

            // Limpa dados antigos
            DB::table('model_has_roles')->delete();
            DB::table('model_has_permissions')->delete();
            DB::table('role_has_permissions')->delete();
            DB::table('roles')->delete();
            DB::table('permissions')->delete();

            // ─── PERMISSÕES ────────────────────────────────────────────────
            //
            //  Gestão de usuários
            //    ver usuarios       → acessa a listagem de usuários
            //    criar usuarios     → cria um novo usuário a partir do AD
            //    editar usuarios    → altera perfis/roles de um usuário
            //    bloquear usuarios  → bloqueia/desbloqueia acesso de um usuário
            //    acessar como       → impersonar outro usuário (admin only)
            //
            //  Gestão de perfis
            //    ver perfis         → acessa a listagem de perfis/roles
            //    criar perfis       → cria novos perfis
            //    editar perfis      → edita permissões de um perfil
            //
            //  Sistema
            //    ver logs           → acessa o LogViewer
            //    configurar sistema → acessa configurações globais (setores whitelist)

            $permissions = [
                // usuários
                'ver usuarios',
                'criar usuarios',
                'editar usuarios',
                'bloquear usuarios',
                'acessar como',
                // perfis
                'ver perfis',
                'criar perfis',
                'editar perfis',
                // sistema
                'ver logs',
                'configurar sistema',
            ];

            foreach ($permissions as $perm) {
                Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            }

            // ─── PERFIS (ROLES) ────────────────────────────────────────────
            //
            //  Administrador  – acesso total ao sistema
            //  Gestor         – gerencia usuários e configurações; não impersona nem mexe em perfis
            //  Coordenador    – acesso de leitura na gestão de usuários e logs; sem ação destrutiva
            //  Usuário Padrão – acesso completo ao SBAR/chat; sem área administrativa
            //  Visualizador   – atribuído automaticamente a novos usuários do AD no primeiro login

            $roleAdmin  = Role::firstOrCreate(['name' => 'Administrador',  'guard_name' => 'web']);
            $roleGestor = Role::firstOrCreate(['name' => 'Gestor',         'guard_name' => 'web']);
            $roleCoord  = Role::firstOrCreate(['name' => 'Coordenador',    'guard_name' => 'web']);
            $roleUser   = Role::firstOrCreate(['name' => 'Usuário Padrão', 'guard_name' => 'web']);
            $roleView   = Role::firstOrCreate(['name' => 'Visualizador',   'guard_name' => 'web']);

            // Administrador → tudo
            $roleAdmin->syncPermissions(Permission::all());

            // Gestor → gerencia usuários (inclusive bloqueio) + configurações + logs; não impersona nem altera perfis
            $roleGestor->syncPermissions([
                'ver usuarios',
                'criar usuarios',
                'editar usuarios',
                'bloquear usuarios',
                'ver perfis',
                'ver logs',
                'configurar sistema',
            ]);

            // Coordenador → leitura de usuários e logs; sem alterações
            $roleCoord->syncPermissions([
                'ver usuarios',
                'ver logs',
            ]);

            // Usuário Padrão → sem permissões administrativas (SBAR/chat é aberto a todos autenticados)
            $roleUser->syncPermissions([]);

            // Visualizador → sem permissões administrativas (mesmo acesso ao SBAR que Usuário Padrão)
            $roleView->syncPermissions([]);

            // ─── USUÁRIO INICIAL ───────────────────────────────────────────

            $user = User::firstOrCreate(
                ['username' => $ldapUser->getFirstAttribute('samaccountname')],
                [
                    'name'   => $ldapUser->getFirstAttribute('displayname'),
                    'email'  => $ldapUser->getFirstAttribute('mail'),
                    'guid'   => $ldapUser->getConvertedGuid(),
                    'domain' => 'default',
                    'status' => 'A',
                ]
            );

            $user->assignRole('Administrador');

            $this->command->info("Sucesso! Perfis e permissões configurados.");
            $this->command->info("Usuário administrador: {$user->username}");
            $this->command->table(
                ['Perfil', 'Permissões'],
                [
                    ['Administrador',  'Todas as permissões'],
                    ['Gestor',         'ver/criar/editar/bloquear usuarios · ver perfis · ver logs · configurar sistema'],
                    ['Coordenador',    'ver usuarios · ver logs'],
                    ['Usuário Padrão', '(sem permissões administrativas — acesso ao SBAR)'],
                    ['Visualizador',   '(sem permissões administrativas — atribuído a novos usuários do AD)'],
                ]
            );

        } catch (\Exception $exception) {
            $this->command->error("Erro ao rodar seeder: " . $exception->getMessage());
        }
    }
}
