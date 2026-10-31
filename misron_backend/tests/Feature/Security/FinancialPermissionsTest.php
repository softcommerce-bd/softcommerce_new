<?php

namespace Tests\Feature\Security;

use Tests\Feature\Inventory\InventoryV2TestCase;

/** Staff with no permissions at all, against the legacy money / stock / order-status screens. */
class FinancialPermissionsTest extends InventoryV2TestCase
{
    public function test_staff_without_permissions_are_refused_on_money_stock_and_status_routes()
    {
        $this->actingAs($this->makeUser('staff'));
        $routes = [
            ['get', 'purchase.index'], ['get', 'purchase.create'], ['post', 'purchase.store'], ['get', 'accounts.supplier-payments.index'], ['post', 'accounts.supplier-payments.store'],
            ['get', 'accounts.reports.balance-sheet'], ['get', 'accounts.reports.profit-loss'], ['get', 'accounts.reports.trial-balance'], ['get', 'accounts.customer-payments.index'], ['post', 'accounts.customer-payments.store'],
            ['post', 'courier-settlement.settle'], ['post', 'bulk-order-status'], ['post', 'orders.update-items-status'],
        ];
        $open = [];
        foreach ($routes as [$m, $name]) {
            if (!\Illuminate\Support\Facades\Route::has($name)) {
                $this->fail("route $name does not exist");
            }
            $res = $this->{$m . 'Json'}(route($name), ['order_id' => 1, 'updates' => []]);
            if (!in_array($res->getStatusCode(), [403, 404, 401], true)) {
                $open[] = "$m $name => " . $res->getStatusCode();
            }
        }
        $this->assertSame([], $open, 'reachable by staff with no permission: ' . implode('; ', $open));
    }

    public function test_reading_reports_and_writing_accounts_are_separate_permissions_and_status_changes_need_theirs()
    {
        $this->actingAs($this->makeUser('staff', ['view_financial_reports']));
        $this->get(route('accounts.reports.balance-sheet'))->assertOk();
        $this->postJson(route('accounts.customer-payments.store'), [])->assertStatus(403);

        $this->actingAs($this->makeUser('staff', ['manage_accounting']));
        $this->postJson(route('accounts.customer-payments.store'), [])->assertStatus(422); // allowed in, then validation

        $this->actingAs($this->makeUser('staff', ['view_all_orders']));
        $this->postJson(route('bulk-order-status'), ['id' => [1], 'bulk_delivery_status' => 'confirmed'])->assertStatus(403);
        $this->actingAs($this->makeUser('staff', ['update_order_delivery_status']));
        $this->assertNotSame(403, $this->postJson(route('bulk-order-status'), ['id' => [1], 'bulk_delivery_status' => 'confirmed'])->getStatusCode());
    }
}
