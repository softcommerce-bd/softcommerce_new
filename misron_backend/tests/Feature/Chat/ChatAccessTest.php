<?php

namespace Tests\Feature\Chat;

use App\Services\Chat\ChatAccess;

class ChatAccessTest extends ChatTestCase
{
    private function access(): ChatAccess { return app(ChatAccess::class); }

    public function test_permissions_are_registered_in_their_own_section_and_granted_to_nobody()
    {
        $names = \DB::table('permissions')->where('section', 'unified_chat_management')->pluck('name')->all();
        foreach (['view_unified_chat', 'view_all_chats', 'view_assigned_chats', 'reply_unified_chat', 'assign_unified_chat', 'manage_chat_accounts', 'manage_chat_settings', 'manage_chat_automation', 'view_chat_analytics', 'create_order_from_chat', 'manage_chat_notes', 'export_chat_data'] as $p) {
            $this->assertContains($p, $names);
        }
        $this->assertSame(0, \DB::table('role_has_permissions')->count(), 'migration must not grant chat access to any role');
        $this->assertSame(0, \DB::table('model_has_permissions')->count());
    }

    public function test_user_without_the_permission_sees_nothing_even_with_account_access()
    {
        $u = $this->staff([]);
        $a = $this->account('facebook', null, [$u]);
        $this->conversation($a);
        $this->assertSame([], $this->access()->accountIds($u));
        $this->assertSame(0, $this->access()->conversations($u)->count());
    }

    public function test_account_level_restriction()
    {
        $s1 = $this->staff(['view_unified_chat', 'view_all_chats']);
        $s2 = $this->staff(['view_unified_chat', 'view_all_chats']);
        $a1 = $this->account('facebook', null, [$s1]);
        $a2 = $this->account('whatsapp', null, [$s2]);
        $c1 = $this->conversation($a1);
        $c2 = $this->conversation($a2);

        $this->assertTrue($this->access()->canSee($s1, $c1));
        $this->assertFalse($this->access()->canSee($s1, $c2), 'staff A must not reach staff B account by id');
        $this->assertNull($this->access()->find($s1, $c2->id));
        $this->assertSame([$c2->id], $this->access()->conversations($s2)->pluck('id')->all());
    }

    public function test_assigned_only_role_sees_only_own_conversations()
    {
        $u = $this->staff(['view_unified_chat', 'view_assigned_chats']);
        $a = $this->account('instagram', null, [$u]);
        $mine = $this->conversation($a, ['assigned_to' => $u->id]);
        $this->conversation($a);
        $this->assertSame([$mine->id], $this->access()->conversations($u)->pluck('id')->all());
    }

    public function test_super_admin_sees_every_account_and_regular_admin_with_no_role_sees_none()
    {
        $su = $this->superAdmin();
        $plain = $this->staff([], 'admin');
        $a = $this->account();
        $c = $this->conversation($a);
        $this->assertTrue($this->access()->canSee($su, $c));
        $this->assertFalse($this->access()->canSee($plain, $c), 'existing administrators are not auto-granted chat access');
    }

    public function test_assignee_must_have_the_account()
    {
        $owner = $this->staff(['view_unified_chat', 'view_all_chats']);
        $other = $this->staff(['view_unified_chat', 'view_all_chats']);
        $a = $this->account('facebook', null, [$owner]);
        $c = $this->conversation($a);
        $this->assertTrue($this->access()->canBeAssigned($owner, $c));
        $this->assertFalse($this->access()->canBeAssigned($other, $c));
    }

    public function test_channel_authorization_follows_account_access_and_revocation()
    {
        $u = $this->staff(['view_unified_chat', 'view_all_chats']);
        $a = $this->account('facebook', null, [$u]);
        $this->assertTrue($this->access()->canListenToAccount($u, $a->id));
        $a->staff()->detach($u->id);
        $this->assertFalse($this->access()->canListenToAccount($u, $a->id), 'revoked access is refused on the next subscription');
    }
}
