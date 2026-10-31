<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/** Assigns the purchasing/inventory permissions to ONE role, from a named profile, with a dry run. Never removes anything. */
class InventoryGrantPermissions extends Command
{
    protected $signature = 'inventory:grant-permissions {role : exact role name} {profile : purchasing_clerk|warehouse|inventory_viewer|finance|manager|preserve_previous_access} {--dry-run}';
    protected $description = 'Grant a recommended set of purchasing/inventory permissions to one role (additive only).';

    public const PROFILES = [
        // buys goods, no money out, no deletions
        'purchasing_clerk' => ['view_suppliers', 'add_supplier', 'view_purchases', 'add_purchase', 'view_purchase_orders', 'manage_purchase_orders', 'view_purchase_requirements', 'receive_goods', 'view_drafts'],
        // receives goods and returns, adjusts stock
        'warehouse' => ['receive_goods', 'manage_returns_receiving', 'view_inventory_reports', 'view_stock_adjustments', 'manage_stock_adjustments', 'view_purchase_orders'],
        // read-only stock visibility
        'inventory_viewer' => ['view_inventory_reports', 'view_stock_adjustments', 'view_purchase_requirements', 'view_purchases'],
        // money: pays suppliers and settles couriers
        'finance' => ['view_financial_reports', 'manage_accounting', 'view_suppliers', 'view_supplier_payments', 'make_supplier_payment', 'view_purchases', 'view_inventory_reports', 'view_courier_settlements', 'settle_courier_orders'],
        // everything operational plus stock policy, but still reviewed deliberately
        'manager' => ['view_financial_reports', 'view_suppliers', 'add_supplier', 'edit_supplier', 'view_supplier_payments', 'view_purchases', 'add_purchase', 'edit_purchase', 'manage_purchase_returns', 'view_purchase_orders', 'manage_purchase_orders',
            'receive_goods', 'view_purchase_requirements', 'view_drafts', 'manage_drafts', 'view_stock_adjustments', 'manage_stock_adjustments', 'view_inventory_reports', 'manage_stock_policy', 'manage_returns_receiving', 'view_courier_settlements'],
        'preserve_previous_access' => null, // every permission of the section
    ];

    public function handle()
    {
        $role = DB::table('roles')->where('name', $this->argument('role'))->first();
        if (!$role) {
            $this->error('No such role. Roles: ' . DB::table('roles')->pluck('name')->implode(', '));

            return 1;
        }
        if (!array_key_exists($this->argument('profile'), self::PROFILES)) {
            $this->error('Unknown profile. Use: ' . implode(', ', array_keys(self::PROFILES)));

            return 1;
        }
        $names = self::PROFILES[$this->argument('profile')] ?? DB::table('permissions')->where('section', 'purchasing_inventory')->pluck('name')->all();
        $perms = DB::table('permissions')->whereIn('name', $names)->where('guard_name', 'web')->get(['id', 'name']);
        $missing = array_diff($names, $perms->pluck('name')->all());
        if ($missing) {
            $this->error('Run the inventory_v2 migrations first; missing: ' . implode(', ', $missing));

            return 1;
        }
        $have = DB::table('role_has_permissions')->where('role_id', $role->id)->pluck('permission_id')->all();
        $new = $perms->reject(fn ($p) => in_array($p->id, $have))->values();
        $this->line(($this->option('dry-run') ? '[dry run] ' : '') . "Role '{$role->name}' gets " . $new->count() . ' new permission(s): ' . $new->pluck('name')->implode(', '));
        if (!$this->option('dry-run')) {
            foreach ($new as $p) {
                DB::table('role_has_permissions')->insert(['permission_id' => $p->id, 'role_id' => $role->id]);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return 0;
    }
}
