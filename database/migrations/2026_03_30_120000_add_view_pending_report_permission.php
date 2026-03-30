<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('ver relatorio pendencias', 'web');

        $adminRole = Role::where('name', 'Administrador')->where('guard_name', 'web')->first();
        $coordRole = Role::where('name', 'Coordenador')->where('guard_name', 'web')->first();

        if ($adminRole) {
            $adminRole->givePermissionTo($permission);
        }

        if ($coordRole) {
            $coordRole->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::where('name', 'ver relatorio pendencias')
            ->where('guard_name', 'web')
            ->first();

        if (! $permission) {
            return;
        }

        $adminRole = Role::where('name', 'Administrador')->where('guard_name', 'web')->first();
        $coordRole = Role::where('name', 'Coordenador')->where('guard_name', 'web')->first();

        if ($adminRole) {
            $adminRole->revokePermissionTo($permission);
        }

        if ($coordRole) {
            $coordRole->revokePermissionTo($permission);
        }

        $permission->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
