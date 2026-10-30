<?php

namespace Tests\Feature\Inventory;

use App\Services\Accounting\JournalService;
use App\Services\Accounting\SystemAccounts;
use App\Services\Inventory\OpeningBalanceService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OpeningCostTest extends InventoryV2TestCase
{
    private function legacyProduct(int $qty = 10)
    {
        // stock that existed before the upgrade: physical units, no cost layer
        $p = $this->productAtZero();
        DB::table('product_stocks')->where('product_id', $p->id)->update(['qty' => $qty]);
        DB::table('products')->where('id', $p->id)->update(['current_stock' => $qty]);

        return $p;
    }

    private function csv(array $rows): string
    {
        return "product_id,variant,sku,name,uncosted_qty,unit_cost,qty_to_cost,evidence\n" . implode("\n", array_map(fn ($r) => implode(',', $r), $rows)) . "\n";
    }

    public function test_uncosted_units_are_listed_and_the_template_leaves_every_cost_blank()
    {
        $p = $this->legacyProduct(10);
        $svc = app(OpeningBalanceService::class);
        $hit = collect($svc->uncostedUnits())->firstWhere('product_id', $p->id);
        $this->assertSame(10, $hit['uncosted']);
        $this->assertStringContainsString('unit_cost', $svc->template());
        $plan = $svc->plan($svc->template());
        $this->assertSame([], $plan['rows'], 'a template with no costs applies nothing: no cost is ever invented');
    }

    public function test_apply_adds_a_layer_and_one_balanced_entry_without_touching_stock_and_is_idempotent()
    {
        $p = $this->legacyProduct(10);
        $svc = app(OpeningBalanceService::class);
        $file = $this->csv([[$p->id, '', '', 'x', 10, '40', 6, 'stock count 2026-01 + invoice 77']]);
        $invBefore = (float) DB::table('chart_of_accounts')->where('id', SystemAccounts::id('inventory'))->value('current_balance');
        $out = $svc->apply($file, 'owner');
        $this->assertSame('240.00', $out['value']);
        $this->assertSame(10, $this->stockQty($p, ''), 'physical stock is not changed');
        $this->assertSame(4, collect($svc->uncostedUnits())->firstWhere('product_id', $p->id)['uncosted'], '6 of 10 units now have a cost, 4 still do not');
        $this->assertEquals($invBefore + 240, (float) DB::table('chart_of_accounts')->where('id', SystemAccounts::id('inventory'))->value('current_balance'));
        $this->assertEquals(240.0, (float) DB::table('chart_of_accounts')->where('id', SystemAccounts::id('opening'))->value('current_balance'), 'equity opening adjustment carries the offset');
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);

        $again = $svc->apply($file, 'owner');
        $this->assertTrue($again['replayed']);
        $this->assertSame(1, DB::table('purchase_products')->where('product_id', $p->id)->count(), 'the same file never adds a second layer');
    }

    public function test_bad_rows_are_refused_as_a_whole()
    {
        $p = $this->legacyProduct(5);
        $svc = app(OpeningBalanceService::class);
        foreach ([[$p->id, '', '', 'x', 5, '-3', '', ''], [$p->id, '', '', 'x', 5, '10', 9, ''], [999999, '', '', 'x', 1, '10', '', '']] as $bad) {
            try {
                $svc->apply($this->csv([$bad]), 'owner');
                $this->fail('a row with a bad cost, an over-large quantity or an unknown product must be refused');
            } catch (InvalidArgumentException $e) {
                $this->assertSame(0, DB::table('purchase_products')->where('product_id', $p->id)->count());
            }
        }
    }

    public function test_the_command_defaults_to_a_dry_run()
    {
        $p = $this->legacyProduct(3);
        $f = tempnam(sys_get_temp_dir(), 'oc');
        file_put_contents($f, $this->csv([[$p->id, '', '', 'x', 3, '12', '', 'evidence']]));
        $this->artisan('inventory:opening-cost', ['action' => 'apply', '--file' => $f])->expectsOutputToContain('DRY RUN')->assertExitCode(0);
        $this->assertSame(0, DB::table('purchase_products')->where('product_id', $p->id)->count());
        $this->artisan('inventory:opening-cost', ['action' => 'apply', '--file' => $f, '--commit' => true])->assertExitCode(2); // no approver
        $this->artisan('inventory:opening-cost', ['action' => 'apply', '--file' => $f, '--commit' => true, '--approved-by' => 'owner'])->assertExitCode(0);
        $this->assertSame(1, DB::table('purchase_products')->where('product_id', $p->id)->count());
        unlink($f);
    }
}
