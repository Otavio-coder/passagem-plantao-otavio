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
            //    acessar como       → impersonar outro usuário
            //
            //  Gestão de perfis
            //    ver perfis         → acessa a listagem de perfis/roles
            //    criar perfis       → cria novos perfis
            //    editar perfis      → edita permissões de um perfil
            //
            //  Sistema
            //    ver logs           → acessa o LogViewer
            //    configurar sistema → acessa configurações globais (setores whitelist)
            //    ver historico chat → acessa o histórico de anotações
            //    ver relatorio pendencias → acessa o relatório tabular de pendências

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
                'ver historico chat',
                'ver relatorio pendencias',
            ];

            foreach ($permissions as $perm) {
                Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            }

            // ─── PERFIS (ROLES) ────────────────────────────────────────────
            //
            //  Administrador → acesso total ao sistema
            //  Coordenador   → gerencia usuários e histórico; sem perfis/logs/configurações
            //  Usuário       → acesso ao SBAR/chat; sem área administrativa

            $roleAdmin = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
            $roleCoord = Role::firstOrCreate(['name' => 'Coordenador',   'guard_name' => 'web']);
            $roleUser  = Role::firstOrCreate(['name' => 'Usuário',       'guard_name' => 'web']);

            // Administrador → tudo
            $roleAdmin->syncPermissions(Permission::all());

            // Coordenador → gerencia usuários + histórico; sem perfis, logs e configurações de sistema
            $roleCoord->syncPermissions([
                'ver usuarios',
                'criar usuarios',
                'editar usuarios',
                'bloquear usuarios',
                'acessar como',
                'ver historico chat',
                'ver relatorio pendencias',
            ]);

            // Usuário → sem permissões administrativas (SBAR/chat aberto a todos autenticados)
            $roleUser->syncPermissions([]);

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
                    ['Administrador', 'Todas as permissões'],
                    ['Coordenador',   'ver/criar/editar/bloquear usuarios · acessar como · ver historico chat'],
                    ['Usuário',       '(sem permissões administrativas — acesso ao SBAR)'],
                ]
            );

        } catch (\Exception $exception) {
            $this->command->error("Erro ao rodar seeder: " . $exception->getMessage());
        }
    }
}
