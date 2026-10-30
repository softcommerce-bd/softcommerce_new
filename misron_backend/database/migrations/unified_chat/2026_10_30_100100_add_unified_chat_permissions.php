<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Staff permissions for Unified Chat. The role editor groups permissions by
 * "section", so these appear under "Unified Chat Management" with no change to
 * the Role & Permission page. Nothing is granted to any existing role; Super
 * Admin passes every check through Gate::before.
 */
class AddUnifiedChatPermissions extends Migration
{
    private $permissions = [
        'view_unified_chat', 'view_all_chats', 'view_assigned_chats', 'reply_unified_chat', 'assign_unified_chat',
        'manage_chat_accounts', 'manage_chat_settings', 'manage_chat_automation', 'view_chat_analytics',
        'create_order_from_chat', 'manage_chat_notes', 'export_chat_data',
    ];

    public function up()
    {
        foreach ($this->permissions as $name) {
            if (!DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                DB::table('permissions')->insert([
                    'name' => $name, 'section' => 'unified_chat_management', 'guard_name' => 'web',
                    'created_at' => now(), 'updated_at' => now(),
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
