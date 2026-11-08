<?php

namespace Tests\Feature\Inventory;

use App\Support\TransactionalEngines;
use Illuminate\Support\Facades\DB;

/**
 * R-01 against the REAL schema shape of the supplied backup (purchases, purchase_products, suppliers, purchase_returns, purchase_return_products on MyISAM).
 * It only runs on a disposable database that still has those tables on MyISAM (e.g. the backup restored into a *_test database before the conversion);
 * on an InnoDB schema it is skipped. Proves that nothing is posted through the HTTP kernel while the tables cannot roll back.
 */
class ProductionShapeEngineGateTest extends InventoryV2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TransactionalEngines::forget();
        if (TransactionalEngines::offenders(TransactionalEngines::PURCHASING) === []) {
            $this->markTestSkipped('the purchasing tables are already InnoDB in this database; this test needs the backup\'s MyISAM shape');
        }
        config(['inventory.financial_engine_policy' => 'enforce']);
    }

    public function test_purchasing_writes_are_refused_through_the_real_routes_and_nothing_is_written()
    {
        $this->actingAs($this->makeAdmin());
        $counts = fn () => [DB::table('purchases')->count(), DB::table('purchase_products')->count(), DB::table('suppliers')->count(), DB::table('supplier_payments')->count(), DB::table('journal_entries')->count()];
        $before = $counts();
        $supplierDue = DB::table('suppliers')->sum('due_amount');

        foreach ([['post', route('purchase.store')], ['post', route('purchase.payment.update')], ['post', route('purchase-return.store')], ['post', route('supplier.payment.process')]] as [$method, $url]) {
            $r = $this->json('POST', $url, ['supplier_id' => $this->supplierId, 'amount' => 10]);
            $this->assertSame(503, $r->getStatusCode(), "$url is refused: " . substr($r->getContent(), 0, 160));
            $this->assertTrue($r->json('engine_gate'));
            $this->assertStringContainsString('MyISAM', (string) $r->json('message'));
        }
        $this->assertSame($before, $counts(), 'nothing was written');
        $this->assertEquals($supplierDue, DB::table('suppliers')->sum('due_amount'));
    }

    public function test_reads_still_work_and_deadlock_retry_is_off()
    {
        $this->actingAs($this->makeAdmin());
        $this->assertSame(200, $this->get(route('purchase.index'))->getStatusCode(), 'reading purchases is not blocked');
        $this->assertFalse(TransactionalEngines::retryAllowed(), 'a retry would repeat MyISAM writes: disabled');
    }

    public function test_warn_policy_runs_with_a_header_and_off_runs_plain()
    {
        $this->actingAs($this->makeAdmin());
        config(['inventory.financial_engine_policy' => 'warn']);
        $r = $this->json('POST', route('purchase.store'), []);
        $this->assertNotSame(503, $r->getStatusCode(), 'warn mode lets the request through (it then fails validation, which is fine here)');
        $this->assertSame('purchasing tables are not InnoDB', $r->headers->get('X-Engine-Warning'));
        config(['inventory.financial_engine_policy' => 'off']);
        $r = $this->json('POST', route('purchase.store'), []);
        $this->assertNotSame(503, $r->getStatusCode());
        $this->assertNull($r->headers->get('X-Engine-Warning'));
    }
}
