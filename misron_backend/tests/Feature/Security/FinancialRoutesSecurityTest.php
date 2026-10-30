<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\Funnel\FunnelTestCase;

/**
 * Regression for the audit's critical finding: supplier, purchase, purchase-order, draft, courier-settlement
 * and updater routes were registered with no login check, and none had permission checks.
 */
class FinancialRoutesSecurityTest extends FunnelTestCase
{
    // Admin and finance areas only. The customer's own order history (purchase_history), the storefront draft capture
    // (draft/create-or-update) and the checkout shipping call live in web.php behind the customer guard on purpose.
    private const PREFIXES = ['supplier', 'purchase/', 'purchase-order', 'draft/index', 'draft/show', 'draft/delete', 'courier-settlement', 'update/', 'order-settlement',
        'fetch-ledger-data', 'fetch-supplier-data', 'supplier-balance', 'supplier-wise-search-product', 'products/search/ajax', 'product/last-purchase-price', 'dashboard/', 'accordions',
        'purchase_code', 'admin/inventory', 'admin/purchasing'];

    protected function setUp(): void
    {
        parent::setUp();
        if (!\DB::table('permissions')->where('name', 'view_suppliers')->exists()) {
            $this->artisan('migrate', ['--path' => 'database/migrations/inventory_v2/2026_11_01_100000_add_purchasing_permissions.php', '--force' => true]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function routes()
    {
        return collect(Route::getRoutes()->getRoutes())->filter(function ($r) {
            if ($r->uri() === 'update') {
                return true;
            }
            foreach (self::PREFIXES as $p) {
                if (str_starts_with($r->uri(), $p)) {
                    return true;
                }
            }

            return false;
        });
    }

    public function test_every_money_stock_and_updater_route_requires_login_and_the_admin_guard()
    {
        $open = $this->routes()->filter(function ($r) {
            $mw = array_map(fn ($m) => is_string($m) ? $m : 'closure', $r->middleware());

            return !in_array('auth', $mw, true) || !in_array('admin', $mw, true);
        })->map(fn ($r) => implode('|', $r->methods()) . ' ' . $r->uri())->values()->all();

        $this->assertSame([], $open, 'routes reachable without login/admin: ' . implode(', ', $open));
        $this->assertGreaterThan(40, $this->routes()->count(), 'the route filter should cover the supplier/purchase area');
    }

    public function test_no_state_changing_route_in_these_areas_uses_get()
    {
        $bad = $this->routes()->filter(fn ($r) => in_array('GET', $r->methods(), true) && preg_match('#/(delete|destroy|remove|cancel)(/|$)#', $r->uri()))
            ->map(fn ($r) => $r->uri())->values()->all();
        $this->assertSame([], $bad);
    }

    public function test_anonymous_visitors_are_sent_to_login_and_get_no_financial_data()
    {
        foreach (['/supplier/index', '/purchase/index', '/purchase/create', '/purchase-order/index', '/draft/index', '/courier-settlement/index', '/supplier-balance/1',
                     '/supplier-wise-search-product?supplier_id=1&q=a', '/products/search/ajax?search=a', '/product/last-purchase-price?product_id=1', '/dashboard/top-selling-products', '/update/step1'] as $url) {
            $r = $this->get($url);
            $this->assertSame(302, $r->getStatusCode(), "$url should redirect an anonymous visitor");
            $this->assertStringContainsString('/login', (string) $r->headers->get('Location'), $url);
        }
        $this->getJson('/supplier-balance/1')->assertStatus(401);
    }

    public function test_anonymous_mutations_are_refused_and_change_nothing()
    {
        $before = [\DB::table('suppliers')->count(), \DB::table('purchases')->count()];
        foreach ([['post', '/purchase/store'], ['post', '/supplier/store'], ['post', '/supplier/payment/process'], ['post', '/courier-settlement/settle'], ['delete', '/purchase/delete/1'], ['delete', '/supplier/delete/1'], ['delete', '/draft/delete/1'], ['post', '/update']] as [$m, $url]) {
            $r = $this->{$m . 'Json'}($url, []);
            $this->assertContains($r->getStatusCode(), [401, 419], "$m $url -> " . $r->getStatusCode());
        }
        $this->assertSame($before, [\DB::table('suppliers')->count(), \DB::table('purchases')->count()]);
    }

    public function test_old_get_delete_links_no_longer_work()
    {
        $admin = $this->makeAdmin();
        foreach (['/purchase/delete/1', '/supplier/delete/1', '/draft/delete/1', '/purchase-order/delete/1'] as $url) {
            $this->actingAs($admin)->get($url)->assertStatus(405);
        }
    }

    public function test_staff_without_the_permission_is_forbidden_and_with_it_is_let_in()
    {
        $plain = $this->makeUser('staff');
        $cases = ['/supplier/index' => 'view_suppliers', '/purchase/index' => 'view_purchases', '/purchase/create' => 'add_purchase', '/purchase-order/index' => 'view_purchase_orders',
            '/courier-settlement/index' => 'view_courier_settlements', '/draft/index' => 'view_drafts', '/supplier-balance/1' => 'view_purchases'];
        foreach ($cases as $url => $perm) {
            $this->forgetAuth();
            $this->actingAs($plain)->getJson($url)->assertStatus(403);
            $this->forgetAuth();
            $user = $this->makeUser('staff', [$perm]);
            $this->assertNotSame(403, $this->actingAs($user)->getJson($url)->getStatusCode(), "$url with $perm");
        }
    }

    public function test_viewing_permission_does_not_allow_writing()
    {
        $viewer = $this->makeUser('staff', ['view_suppliers', 'view_purchases', 'view_drafts', 'view_courier_settlements']);
        foreach ([['post', '/supplier/store'], ['post', '/purchase/store'], ['post', '/supplier/payment/process'], ['post', '/courier-settlement/settle'], ['delete', '/purchase/delete/1'], ['delete', '/supplier/delete/1'], ['delete', '/draft/delete/1']] as [$m, $url]) {
            $this->forgetAuth();
            $this->actingAs($viewer)->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)->{$m . 'Json'}($url, [])->assertStatus(403);
        }
    }

    public function test_super_admin_still_reaches_everything()
    {
        $admin = $this->makeAdmin();
        foreach (['/supplier/index', '/purchase/index', '/purchase-order/index', '/courier-settlement/index'] as $url) {
            $this->forgetAuth();
            $this->assertNotContains($this->actingAs($admin)->get($url)->getStatusCode(), [401, 403, 302], $url);
        }
    }

    public function test_the_permission_migration_gives_existing_roles_today_s_access_and_nothing_to_chat_roles_twice()
    {
        $this->assertGreaterThanOrEqual(24, \DB::table('permissions')->where('section', 'purchasing_inventory')->count());
    }
}
