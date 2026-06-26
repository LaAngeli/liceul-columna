<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Restructurarea modelului de roluri la specificația §3.2/§3.3 (9 roluri):
 * - redenumește `director-adjunct` → `prim-vicedirector` (păstrează role_id → atribuirile rămân);
 * - adaugă `administrator-operational` și `administrator-tehnic`.
 *
 * `admin` (Super Administrator) și restul rolurilor rămân neschimbate.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', 'director-adjunct')
            ->update(['name' => 'prim-vicedirector']);

        foreach (['administrator-operational', 'administrator-tehnic'] as $name) {
            $exists = DB::table('roles')
                ->where('guard_name', 'web')
                ->where('name', $name)
                ->exists();

            if (! $exists) {
                DB::table('roles')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['administrator-operational', 'administrator-tehnic'])
            ->delete();

        DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', 'prim-vicedirector')
            ->update(['name' => 'director-adjunct']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
