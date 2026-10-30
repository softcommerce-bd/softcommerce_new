<?php

namespace Tests\Feature\Chat;

use App\Models\ChatAccount;
use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Chat tests run on a private in-memory SQLite database (never the store's MySQL), built from the
 * real unified_chat migrations plus the minimum of the existing schema they touch.
 */
abstract class ChatTestCase extends TestCase
{
    private static $dbFile;

    /**
     * The store's CategoryServiceProvider reads `categories` while booting, so the app cannot boot without
     * that table. Point the app at a private SQLite file that has it, before anything boots.
     */
    public function createApplication()
    {
        if (!self::$dbFile) {
            self::$dbFile = sys_get_temp_dir() . '/chat-test-' . getmypid() . '.sqlite';
            @unlink(self::$dbFile);
            $pdo = new \PDO('sqlite:' . self::$dbFile);
            $pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, parent_id INTEGER DEFAULT 0, name TEXT)');
        }
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => self::$dbFile] as $k => $v) {
            putenv("$k=$v");
            $_ENV[$k] = $_SERVER[$k] = $v;
        }

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropAllTables();
        Schema::create('categories', function ($t) { $t->increments('id'); $t->integer('parent_id')->default(0); $t->string('name')->nullable(); });
        Schema::create('translations', function ($t) { $t->increments('id'); $t->string('lang')->nullable(); $t->string('lang_key')->nullable(); $t->text('lang_value')->nullable(); $t->timestamps(); });
        Schema::create('business_settings', function ($t) { $t->increments('id'); $t->string('type')->nullable(); $t->text('value')->nullable(); });
        config(['unified_chat.mode' => 'direct',
            'unified_chat.meta.app_secret' => 'appsecret', 'unified_chat.meta.verify_token' => 'vtoken',
            'unified_chat.gateway' => ['url' => 'https://gw.test', 'installation_id' => 'inst_a', 'secret' => 'secret-a', 'tolerance' => 300]]);
        Cache::flush();

        Schema::create('users', function ($t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('password')->nullable();
            $t->string('user_type')->default('customer');
            $t->boolean('banned')->default(0);
            $t->string('last_ip')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        foreach (['permissions' => 'name', 'roles' => 'name'] as $table => $col) {
            Schema::create($table, function ($t) use ($table) {
                $t->bigIncrements('id');
                $t->string('name');
                if ($table === 'permissions') $t->string('section')->nullable();
                $t->string('guard_name');
                $t->timestamps();
            });
        }
        Schema::create('model_has_permissions', function ($t) { $t->unsignedBigInteger('permission_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); });
        Schema::create('model_has_roles', function ($t) { $t->unsignedBigInteger('role_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); });
        Schema::create('role_has_permissions', function ($t) { $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id'); });
        Schema::create('combined_orders', fn ($t) => $t->increments('id'));
        Schema::create('orders', function ($t) { $t->increments('id'); $t->unsignedInteger('combined_order_id')->nullable(); $t->unsignedInteger('user_id')->nullable(); $t->string('code')->nullable(); $t->double('grand_total')->default(0); $t->string('delivery_status')->default('pending'); $t->string('payment_status')->default('unpaid'); $t->integer('date')->nullable(); });

        $this->artisan('migrate', ['--path' => 'database/migrations/unified_chat', '--realpath' => false, '--force' => true])->assertExitCode(0);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
    }

    protected function staff(array $permissions = [], string $type = 'staff'): User
    {
        $u = new User();
        $u->forceFill(['name' => 'Staff ' . uniqid(), 'email' => uniqid() . '@t.test', 'password' => 'x', 'user_type' => $type, 'banned' => 0])->save();
        if ($permissions) {
            $r = Role::create(['name' => 'r' . uniqid(), 'guard_name' => 'web']);
            $r->givePermissionTo($permissions);
            $u->assignRole($r);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    protected function superAdmin(): User
    {
        $u = $this->staff([], 'admin');
        $u->assignRole('Super Admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    protected function account(string $platform = 'facebook', string $ext = null, array $staff = []): ChatAccount
    {
        $a = new ChatAccount(['platform' => $platform, 'external_id' => $ext ?: 'ACC' . uniqid(), 'name' => ucfirst($platform) . ' page', 'status' => 'connected']);
        $a->credentials = ['access_token' => 'tok-' . $platform];
        $a->save();
        $a->staff()->sync(array_map(fn ($u) => $u->id, $staff));

        return $a;
    }

    protected function conversation(ChatAccount $a, array $attrs = []): ChatConversation
    {
        return ChatConversation::create($attrs + ['chat_account_id' => $a->id, 'platform' => $a->platform, 'external_id' => 'C' . uniqid(), 'customer_name' => 'Customer', 'last_message_at' => now(), 'last_inbound_at' => now(), 'status' => 'open']);
    }

    protected function messengerPayload(string $page, string $mid, string $text = 'hello', string $psid = 'PSID1', bool $echo = false): array
    {
        return ['object' => 'page', 'entry' => [['id' => $page, 'time' => 1, 'messaging' => [[
            'sender' => ['id' => $echo ? $page : $psid], 'recipient' => ['id' => $echo ? $psid : $page], 'timestamp' => 1700000000000,
            'message' => ['mid' => $mid, 'text' => $text] + ($echo ? ['is_echo' => true] : []),
        ]]]]];
    }

    protected function sign(string $body): string
    {
        return 'sha256=' . hash_hmac('sha256', $body, 'appsecret');
    }
}
