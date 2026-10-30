<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the purchasing, supplier, draft, courier-settlement and stock-adjustment screens. Until now these
 * routes had no permission checks at all (and several had no login check), so any staff account could use them.
 *
 * SECURE BY DEFAULT: the migration creates the permissions and grants them to NO role (Super Admin passes every check through
 * Gate::before). Right after deploying, assign them per role with the reviewable command
 *     php artisan inventory:grant-permissions "<role name>" <profile> [--dry-run]
 * (profiles: purchasing_clerk, warehouse, inventory_viewer, finance, manager, preserve_previous_access), or tick them in
 * Staff > Roles (section "Purchasing Inventory"). `preserve_previous_access` reproduces the old everyone-can-do-everything behaviour
 * for a role if you need a same-day fallback.
 */
class AddPurchasingPermissions extends Migration
{
    private $permissions = [
        'view_suppliers', 'add_supplier', 'edit_supplier', 'delete_supplier', 'make_supplier_payment', 'view_supplier_payments',
        'view_purchases', 'add_purchase', 'edit_purchase', 'delete_purchase', 'manage_purchase_returns',
        'view_purchase_orders', 'manage_purchase_orders', 'receive_goods', 'view_purchase_requirements',
        'view_drafts', 'manage_drafts', 'view_courier_settlements', 'settle_courier_orders',
        'view_stock_adjustments', 'manage_stock_adjustments', 'view_inventory_reports', 'manage_stock_policy', 'manage_returns_receiving',
    ];

    public function up()
    {
        $now = now();
        $newIds = [];
        foreach ($this->permissions as $name) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            if (!$id) {
                $id = DB::table('permissions')->insertGetId(['name' => $name, 'section' => 'purchasing_inventory', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
                $newIds[] = $id;
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
