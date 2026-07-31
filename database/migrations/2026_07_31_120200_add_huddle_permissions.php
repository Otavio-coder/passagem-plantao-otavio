<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permissões do módulo Huddle de Gestão de Altas.
     *
     * - 'ver huddle'      → visualizar o painel (equipe assistencial / gestão)
     * - 'conduzir huddle' → editar cor do dia, previsão de alta e motivos (enfermagem)
     *
     * Atribuídas a Administrador e Coordenador na criação. A atribuição ao perfil
     * de enfermagem será feita conforme a definição de perfis do piloto.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $view = Permission::findOrCreate('ver huddle', 'web');
        $conduct = Permission::findOrCreate('conduzir huddle', 'web');

        $adminRole = Role::where('name', 'Administrador')->where('guard_name', 'web')->first();
        $coordRole = Role::where('name', 'Coordenador')->where('guard_name', 'web')->first();

        if ($adminRole) {
            $adminRole->givePermissionTo([$view, $conduct]);
        }

        if ($coordRole) {
            $coordRole->givePermissionTo([$view, $conduct]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['ver huddle', 'conduzir huddle'] as $name) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();

            if ($permission) {
                $permission->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
