<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission to open the funnel analytics dashboard. Separate from
 * view_funnels because the dashboard shows revenue, which not every member of
 * staff who edits pages needs to see.
 */
class AddFunnelAnalyticsPermission extends Migration
{
    public function up()
    {
        $exists = DB::table('permissions')->where('name', 'view_funnel_analytics')->where('guard_name', 'web')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'name' => 'view_funnel_analytics',
                'section' => 'sales_funnel',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        $ids = DB::table('permissions')->where('name', 'view_funnel_analytics')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
