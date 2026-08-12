<?php

namespace Database\Seeders;

use App\Models\System\User;
use Illuminate\Database\Seeder;
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

            // ─── PERMISSÕES ────────────────────────────────────────────────
            //
            //  Gestão de usuários
            //    ver usuarios             → lista de usuários
            //    criar usuarios           → cria usuário a partir do AD
            //    editar usuarios          → altera perfis/roles e flag is_nurse
            //    bloquear usuarios        → bloqueia/desbloqueia acesso
            //    acessar como             → impersona outro usuário
            //
            //  Gestão de perfis
            //    ver perfis               → lista de perfis/roles
            //    criar perfis             → cria novos perfis
            //    editar perfis            → edita permissões de um perfil
            //
            //  Relatórios e ferramentas
            //    ver relatorio pendencias → relatório tabular de pendências clínicas
            //    ver historico chat       → histórico de avaliações + métricas de passagem
            //
            //  Sistema
            //    ver logs                 → acessa o LogViewer
            //    configurar sistema       → configurações globais (setores whitelist)

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
                // relatórios
                'ver relatorio pendencias',
                'ver historico chat',
                // sistema
                'ver logs',
                'configurar sistema',
                // huddle de gestão de altas
                'ver huddle',
                'conduzir huddle',
            ];

            // Garante que todas as permissões da lista existem
            foreach ($permissions as $perm) {
                Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            }

            // ─── PERFIS (ROLES) ────────────────────────────────────────────
            //
            //  Administrador → acesso total
            //  Coordenador   → gerencia usuários, vê histórico e pendências; sem perfis/logs/config
            //  Enfermeiro    → SBAR/chat + passagem de plantão; sem área administrativa
            //  Usuário       → SBAR/chat; sem área administrativa

            $roleAdmin = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
            $roleCoord = Role::firstOrCreate(['name' => 'Coordenador',   'guard_name' => 'web']);
            $roleNurse = Role::firstOrCreate(['name' => 'Enfermeiro',    'guard_name' => 'web']);
            $roleUser = Role::firstOrCreate(['name' => 'Usuário',       'guard_name' => 'web']);

            // syncPermissions desvincula permissões removidas da lista antes de deletá-las
            $roleAdmin->syncPermissions($permissions);

            // Coordenador → gestão de usuários + relatórios; sem perfis, logs e config de sistema
            $roleCoord->syncPermissions([
                'ver usuarios',
                'criar usuarios',
                'editar usuarios',
                'bloquear usuarios',
                'acessar como',
                'ver relatorio pendencias',
                'ver historico chat',
                // supervisão de enfermagem visualiza e conduz o Huddle
                'ver huddle',
                'conduzir huddle',
            ]);

            // Enfermeiro → SBAR/chat + relatório de pendências; sem histórico de avaliações nem admin
            // A feature de passagem de plantão é controlada pelo flag is_nurse no usuário,
            // não por uma permissão Spatie — qualquer usuário com is_nurse=true e este perfil
            // terá acesso ao botão "Iniciar Passagem".
            // O Huddle de Gestão de Altas é conduzido pela enfermagem.
            $roleNurse->syncPermissions([
                'ver relatorio pendencias',
                'ver huddle',
                'conduzir huddle',
            ]);

            // Usuário → sem permissões administrativas (SBAR/chat aberto a todos autenticados)
            $roleUser->syncPermissions([]);

            // Remove permissões que saíram da lista (o syncPermissions acima já as desvinculou dos roles)
            Permission::where('guard_name', 'web')
                ->whereNotIn('name', $permissions)
                ->delete();

            // ─── USUÁRIO INICIAL ───────────────────────────────────────────

            $user = User::firstOrCreate(
                ['username' => $ldapUser->getFirstAttribute('samaccountname')],
                [
                    'name' => $ldapUser->getFirstAttribute('displayname'),
                    'email' => $ldapUser->getFirstAttribute('mail'),
                    'guid' => $ldapUser->getConvertedGuid(),
                    'domain' => 'default',
                    'status' => 'A',
                ]
            );

            if ($user->roles()->count() === 0) {
                $user->assignRole('Administrador');
            }

            // Perguntas do Round Unidade (idempotente — updateOrCreate)
            $this->call(HuddleUnitQuestionSeeder::class);

            $this->command->info('Sucesso! Perfis e permissões configurados.');
            $this->command->info("Usuário administrador: {$user->username}");
            $this->command->table(
                ['Perfil', 'Permissões / Observações'],
                [
                    ['Administrador', 'Todas as permissões'],
                    ['Coordenador',   'ver/criar/editar/bloquear usuarios · acessar como · ver relatorio pendencias · ver historico chat'],
                    ['Enfermeiro',    'ver relatorio pendencias · SBAR/chat · passagem de plantão (requer is_nurse=true)'],
                    ['Usuário',       'SBAR/chat (sem área administrativa)'],
                ]
            );

        } catch (\Exception $exception) {
            $this->command->error('Erro ao rodar seeder: '.$exception->getMessage());
        }
    }
}
