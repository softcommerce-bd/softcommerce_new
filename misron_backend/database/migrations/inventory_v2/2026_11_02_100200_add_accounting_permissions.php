<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The accounts area (balance sheet, P&L, trial balance, customer payments, vouchers, transfers, ...) had no permission check: any staff
 * login could open and post to it. Two permissions now guard it. Like the purchasing permissions they are granted to NO role here;
 * assign with `php artisan inventory:grant-permissions "<role>" finance` (or tick them in Staff > Roles) BEFORE staff need them, or
 * only Super Admin can open the accounts area.
 */
class AddAccountingPermissions extends Migration
{
    private $permissions = ['view_financial_reports', 'manage_accounting'];

    public function up()
    {
        $now = now();
        foreach ($this->permissions as $name) {
            if (!DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                DB::table('permissions')->insert(['name' => $name, 'section' => 'purchasing_inventory', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
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
