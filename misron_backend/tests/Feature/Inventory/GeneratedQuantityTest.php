<?php

namespace Tests\Feature\Inventory;

use App\Services\Purchasing\BulkPurchaseImporter;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * MYSQL84.2: purchase_order_details.pending_quantity is a GENERATED column (purchase_quantity - recieved_quantity). The application must never write it
 * (MySQL 8.4 rejects that with error 3105; MariaDB silently ignores it), and the generated value must stay right through create, partial receipts,
 * full receipt and cancellation - on both engines. remaining_quantity (a real column) keeps its meaning.
 */
class GeneratedQuantityTest extends InventoryV2TestCase
{
    private function po(array $lines, string $status = 'ordered'): array
    {
        return app(PurchaseOrderService::class)->create('po-' . uniqid(), ['supplier_id' => $this->supplierId, 'status' => $status, 'lines' => $lines]);
    }

    private function line(int $poId, int $productId): object
    {
        return DB::table('purchase_order_details')->where('purchase_order_id', $poId)->where('product_id', $productId)->first();
    }

    private function figures(object $d): array
    {
        return [(float) $d->purchase_quantity, (float) $d->recieved_quantity, (float) $d->pending_quantity, (float) $d->remaining_quantity];
    }

    private function receiveLines(int $poId, array $perLine, string $key): void
    {
        app(GoodsReceiptService::class)->receive($key, ['supplier_id' => $this->supplierId, 'purchase_order_id' => $poId, 'lines' => $perLine]);
    }

    public function test_the_column_really_is_generated_and_no_application_code_writes_it()
    {
        $row = DB::selectOne("SELECT EXTRA AS extra, GENERATION_EXPRESSION AS expr FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_order_details' AND COLUMN_NAME = 'pending_quantity'");
        $this->assertStringContainsString('GENERATED', (string) $row->extra, 'precondition: pending_quantity is a generated column in this schema');
        $this->assertStringContainsString('recieved_quantity', (string) $row->expr);

        $writes = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() === 'php' && preg_match('/[\'"]pending_quantity[\'"]\s*=>/', file_get_contents($f->getPathname()))) {
                $writes[] = str_replace(base_path() . '/', '', $f->getPathname());
            }
        }
        $this->assertSame([], $writes, 'no insert/update array in app/ sets pending_quantity');
    }

    public function test_creating_an_order_leaves_pending_equal_to_the_ordered_quantity_and_remaining_the_same()
    {
        $a = $this->productAtZero(['name' => 'GQ A']);
        $b = $this->productAtZero(['name' => 'GQ B']);
        $po = $this->po([['product_id' => $a->id, 'qty' => 10, 'unit_cost' => '50'], ['product_id' => $b->id, 'qty' => 3, 'unit_cost' => '20']]);
        $this->assertSame([10.0, 0.0, 10.0, 10.0], $this->figures($this->line($po['purchase_order_id'], $a->id)));
        $this->assertSame([3.0, 0.0, 3.0, 3.0], $this->figures($this->line($po['purchase_order_id'], $b->id)));
        $this->assertSame(13, $this->snap($a)['incoming'] + $this->snap($b)['incoming']);
    }

    public function test_partial_then_full_receipt_keeps_the_generated_quantity_exact_and_closes_the_order()
    {
        $p = $this->productAtZero(['name' => 'GQ C']);
        $po = $this->po([['product_id' => $p->id, 'qty' => 10, 'unit_cost' => '50']]);
        $id = $po['purchase_order_id'];
        $d = $this->line($id, $p->id);
        $mk = fn ($q) => [['purchase_order_detail_id' => $d->id, 'product_id' => $p->id, 'qty' => $q, 'unit_cost' => '50']];

        $this->receiveLines($id, $mk(4), 'gq-1');
        $this->assertSame([10.0, 4.0, 6.0, 6.0], $this->figures($this->line($id, $p->id)));
        $this->assertSame('partially_received', DB::table('purchase_orders')->where('id', $id)->value('status'));
        $this->assertSame(6, $this->snap($p)['incoming']);

        try {
            $this->receiveLines($id, $mk(7), 'gq-2'); // more than is still pending
            $this->fail('over-receiving is refused');
        } catch (InvalidArgumentException $e) {
            $this->assertSame([10.0, 4.0, 6.0, 6.0], $this->figures($this->line($id, $p->id)), 'a refused receipt changes nothing');
        }

        $this->receiveLines($id, $mk(6), 'gq-3');
        $this->assertSame([10.0, 10.0, 0.0, 0.0], $this->figures($this->line($id, $p->id)));
        $this->assertSame('received', DB::table('purchase_orders')->where('id', $id)->value('status'));
        $this->assertSame([10, 0], [$this->stockQty($p, ''), $this->snap($p)['incoming']]);
    }

    public function test_cancelling_an_unreceived_order_keeps_history_and_stops_it_counting_anywhere()
    {
        $p = $this->productAtZero(['name' => 'GQ D']);
        $po = $this->po([['product_id' => $p->id, 'qty' => 8, 'unit_cost' => '50']]);
        $id = $po['purchase_order_id'];
        $d = $this->line($id, $p->id);
        $journal = DB::table('journal_entries')->count();

        app(PurchaseOrderService::class)->cancel($id);

        $after = $this->line($id, $p->id);
        $this->assertSame('cancelled', DB::table('purchase_orders')->where('id', $id)->value('status'));
        $this->assertSame([8.0, 0.0, 8.0, 0.0], $this->figures($after), 'the ordered quantity is history and stays; remaining is zeroed; pending is the database\'s own purchase - received');
        $this->assertSame(0, $this->snap($p)['incoming'], 'a cancelled order is not incoming');
        $this->assertSame($journal, DB::table('journal_entries')->count(), 'cancelling posts nothing');
        $this->assertSame(0, $this->stockQty($p, ''));

        // the screens that sum pending quantities do not count it
        $admin = $this->makeAdmin();
        $dash = $this->actingAs($admin)->get(route('purchasing.dashboard'));
        $dash->assertOk();
        $this->assertSame(0, (int) DB::table('purchase_order_details as x')->join('purchase_orders as o', 'o.id', '=', 'x.purchase_order_id')->whereIn('o.status', ['ordered', 'partially_received'])->sum('x.pending_quantity'));
        $this->forgetAuth();
        $this->actingAs($admin)->get(route('purchasing.receiving.index'))->assertOk()->assertDontSee($po['reference_no']);

        try {
            app(GoodsReceiptService::class)->receive('gq-c', ['supplier_id' => $this->supplierId, 'purchase_order_id' => $id, 'lines' => [['purchase_order_detail_id' => $d->id, 'product_id' => $p->id, 'qty' => 1, 'unit_cost' => '50']]]);
            $this->fail('a cancelled order cannot receive goods');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(0, $this->stockQty($p, ''));
        }
        try {
            app(PurchaseOrderService::class)->cancel($id);
            $this->fail('cancelling twice is refused');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('cancelled', DB::table('purchase_orders')->where('id', $id)->value('status'));
        }
    }

    public function test_cancelling_after_a_partial_receipt_keeps_what_arrived_and_drops_the_rest()
    {
        $p = $this->productAtZero(['name' => 'GQ E']);
        $po = $this->po([['product_id' => $p->id, 'qty' => 10, 'unit_cost' => '50']]);
        $id = $po['purchase_order_id'];
        $d = $this->line($id, $p->id);
        $this->receiveLines($id, [['purchase_order_detail_id' => $d->id, 'product_id' => $p->id, 'qty' => 4, 'unit_cost' => '50']], 'gq-p');
        $layers = DB::table('purchase_products')->where('product_id', $p->id)->count();

        app(PurchaseOrderService::class)->cancel($id);

        $this->assertSame('received', DB::table('purchase_orders')->where('id', $id)->value('status'), 'a part-received order is closed as received, not cancelled');
        $this->assertSame([10.0, 4.0, 6.0, 0.0], $this->figures($this->line($id, $p->id)), 'received stays 4; remaining zeroed; the database still computes 10 - 4');
        $this->assertSame([4, 0], [$this->stockQty($p, ''), $this->snap($p)['incoming']], 'stock stays; the 6 that will not come are not incoming');
        $this->assertSame($layers, DB::table('purchase_products')->where('product_id', $p->id)->count(), 'cost layers untouched');
        $this->assertSame(1, DB::table('goods_receipts')->count());
    }

    public function test_bulk_commit_and_the_requirements_screen_create_orders_with_a_correct_generated_pending()
    {
        $p = $this->productAtZero(['name' => 'GQ F']);
        $bulk = app(BulkPurchaseImporter::class)->commit($this->supplierId, "product_id,variant,qty,unit_cost\n{$p->id},,4,25\n", 'ordered');
        $this->assertSame([4.0, 0.0, 4.0, 4.0], $this->figures($this->line($bulk['purchase_order_id'], $p->id)));
        $this->assertSame(4, $this->snap($p)['incoming']);

        $q = $this->productAtZero(['name' => 'GQ G', 'stock_policy' => 'backorder', 'preferred_supplier_id' => $this->supplierId, 'estimated_purchase_cost' => 70]);
        $o = $this->makeOrder([[$q, '', 3]], 'confirmed');
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get(route('purchasing.requirements', ['only_shortage' => 1]))->assertOk()->assertSee($o->code);
        $this->forgetAuth();
        $this->actingAs($admin)->post(route('purchasing.requirements.po'), ['op_key' => 'gq-req', 'supplier_id' => $this->supplierId, 'status' => 'ordered', 'items' => [['product_id' => $q->id, 'variant' => '', 'qty' => 3, 'unit_cost' => '70']]])->assertRedirect();
        $reqPo = DB::table('purchase_orders')->where('id', '!=', $bulk['purchase_order_id'])->orderByDesc('id')->first();
        $this->assertSame([3.0, 0.0, 3.0, 3.0], $this->figures($this->line($reqPo->id, $q->id)));
        $this->assertSame(3, $this->snap($q)['incoming']);

        $this->forgetAuth();
        $this->actingAs($admin)->get(route('purchasing.receiving.form', $reqPo->id))->assertOk()->assertSee('max="3"', false);
    }
}
