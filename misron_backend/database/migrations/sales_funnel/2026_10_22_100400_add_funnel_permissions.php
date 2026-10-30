<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Staff permissions for the funnel module. The role editor lists permissions
 * grouped by "section", so these appear under "Sales Funnel" with no view
 * change. Super Admin passes every check through Gate::before.
 */
class AddFunnelPermissions extends Migration
{
    private $permissions = [
        'view_funnels',
        'add_funnel',
        'edit_funnel',
        'publish_funnel',
        'delete_funnel',
    ];

    public function up()
    {
        foreach ($this->permissions as $name) {
            $exists = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists();
            if (!$exists) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'section' => 'sales_funnel',
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        $ids = DB::table('permissions')->whereIn('name', $this->permissions)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
