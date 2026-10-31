<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Inventory\InventoryV2TestCase;

/** Every purchasing / report route: anonymous -> login, staff without permission -> 403, CSRF enforced on writes, profiles additive. */
class PurchasingPermissionsMatrixTest extends InventoryV2TestCase
{
    private function purchasingRoutes()
    {
        return collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with((string) $r->getName(), 'purchasing.'));
    }

    private function fill(string $uri): string
    {
        return '/' . preg_replace('/\{[^}]+\}/', '1', $uri);
    }

    public function test_anonymous_requests_never_reach_a_purchasing_route()
    {
        $this->assertGreaterThan(20, $this->purchasingRoutes()->count());
        foreach ($this->purchasingRoutes() as $r) {
            $method = in_array('GET', $r->methods(), true) ? 'get' : 'post';
            $res = $this->{$method . 'Json'}($this->fill($r->uri()), []);
            $this->assertContains($res->getStatusCode(), [401, 302], "{$r->getName()} must not answer an anonymous visitor (got {$res->getStatusCode()})");
        }
    }

    public function test_staff_without_any_purchasing_permission_get_403_on_every_route()
    {
        $staff = $this->makeUser('staff');
        $this->actingAs($staff);
        foreach ($this->purchasingRoutes() as $r) {
            $method = in_array('GET', $r->methods(), true) ? 'get' : 'post';
            $res = $this->{$method . 'Json'}($this->fill($r->uri()), ['op_key' => 'x', 'lines' => []]);
            $this->assertSame(403, $res->getStatusCode(), "{$r->getName()} ({$r->uri()}) should refuse staff without permission, got {$res->getStatusCode()}");
        }
    }

    public function test_a_single_permission_opens_only_its_own_screens()
    {
        $viewer = $this->makeUser('staff', ['view_inventory_reports']);
        $this->actingAs($viewer);
        $this->get(route('purchasing.stock'))->assertOk();
        $this->get(route('purchasing.valuation'))->assertOk();
        $this->postJson(route('purchasing.quick.submit'), [])->assertStatus(403);
        $this->post(route('purchasing.returns.receive'), [])->assertStatus(403);
        $this->get(route('purchasing.report', ['name' => 'supplier-statement']))->assertOk();
    }

    public function test_money_writes_enforce_csrf_when_the_real_middleware_runs()
    {
        // the framework skips CSRF under PHPUnit; run the project's own middleware as production does
        $this->app->bind(VerifyCsrfToken::class, CsrfEnforcing::class);
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $cases = [
            ['post', route('purchasing.quick.submit')], ['post', route('purchasing.returns.receive')], ['post', route('bulk-order-status')],
            ['post', route('accounts.supplier-payments.store')], ['post', route('purchase.store')], ['post', route('purchase_history.destroy', 1)],
        ];
        foreach ($cases as [$m, $url]) {
            $this->assertSame(419, $this->{$m}($url, ['x' => 1])->getStatusCode(), "$url must reject a request without a CSRF token");
        }
        $token = 'tok-' . uniqid();
        $this->withSession(['_token' => $token]);
        $res = $this->post(route('bulk-order-status'), ['_token' => $token, 'id' => [], 'bulk_delivery_status' => 'confirmed']);
        $this->assertNotSame(419, $res->getStatusCode(), 'with a valid token the request is processed');
    }

    public function test_default_permission_profiles_are_additive_and_leave_other_roles_alone()
    {
        $role = \Spatie\Permission\Models\Role::create(['name' => 'Clerk ' . uniqid(), 'guard_name' => 'web']);
        $other = \Spatie\Permission\Models\Role::create(['name' => 'Other ' . uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(DB::table('permissions')->where('section', '!=', 'purchasing_inventory')->limit(3)->pluck('name')->all());
        $before = $role->permissions()->count();
        $this->artisan('inventory:grant-permissions', ['role' => $role->name, 'profile' => 'purchasing_clerk', '--dry-run' => true])->assertExitCode(0);
        $this->assertSame($before, $role->fresh()->permissions()->count(), 'dry run changes nothing');
        $this->artisan('inventory:grant-permissions', ['role' => $role->name, 'profile' => 'purchasing_clerk'])->assertExitCode(0);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = $role->fresh();
        $this->assertGreaterThan($before, $role->permissions()->count());
        $this->assertTrue($role->hasPermissionTo('add_purchase'));
        $this->assertFalse($role->hasPermissionTo('make_supplier_payment'), 'a clerk profile does not carry money-out permission');
        $this->assertFalse($role->hasPermissionTo('manage_stock_adjustments'));
        $this->assertSame(0, $other->permissions()->count(), 'other roles are untouched');
        $this->assertSame(0, DB::table('role_has_permissions as rp')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('p.section', 'purchasing_inventory')->where('rp.role_id', $other->id)->count());
    }
}

class CsrfEnforcing extends VerifyCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}
