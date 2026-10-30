<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the purchasing, supplier, draft, courier-settlement and stock-adjustment screens. Until now these
 * routes had no permission checks at all (and several had no login check), so any staff account could use them.
 *
 * To avoid locking out current staff on deployment, every EXISTING role receives the new permissions once, which
 * is exactly the access those roles had before. Roles created later start empty. Tighten roles afterwards in
 * Staff > Roles (section "Purchasing & Inventory"). Super Admin passes every check through Gate::before.
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
        // Only permissions created by THIS run are granted, so re-running never re-grants something an admin revoked.
        foreach (DB::table('roles')->where('name', '!=', 'Super Admin')->pluck('id') as $roleId) {
            foreach ($newIds as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $roleId], []);
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
