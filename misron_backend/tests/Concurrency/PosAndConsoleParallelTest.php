<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;

/** T-5 workflows 13 (POS) and 16 (console jobs): real parallel processes against committed rows. */
class PosAndConsoleParallelTest extends HttpWorkflowCase
{
    use PreparesAutoDepositSchema;

    private function posCart(string $temp, int $product, int $qty, int $owner): void
    {
        DB::table('carts')->insert(['owner_id' => $owner, 'user_id' => null, 'temp_user_id' => $temp, 'product_id' => $product, 'variation' => 'M', 'quantity' => $qty, 'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function posJob(int $user, string $temp): array
    {
        return ['action' => 'pos_order', 'user_id' => $user, 'data' => ['user_id' => null, 'temp_usder_id' => $temp, 'payment_type' => 'cash_on_delivery', 'shippingInfo' => ['name' => 'Walk-in', 'phone' => '01700000000', 'address' => 'Counter']]];
    }

    private function books(): array
    {
        $acc = fn ($n) => (float) DB::table('chart_of_accounts')->where('name', $n)->where('is_deleted', '0')->value('current_balance');

        return ['ar' => $acc('Accounts Receivable'), 'sales' => $acc('Sales'), 'cogs' => $acc('Cost of Goods Sold'), 'inventory' => $acc('Inventory')];
    }

    private function delta(array $a, array $b): array
    {
        return array_map(fn ($k) => round($b[$k] - $a[$k], 2), array_combine(array_keys($a), array_keys($a)));
    }

    public function test_four_pos_sales_for_the_last_six_units_sell_exactly_six_and_book_like_sequential_sales()
    {
        $this->flag(true);
        $this->systemAccounts();
        $admin = $this->admin();
        $owner = (int) DB::table('users')->where('user_type', 'admin')->orderBy('id')->value('id');
        $run = function (bool $parallel) use ($admin, $owner) {
            $p = $this->seedProduct(['M' => 6], 1000, 400, ['M' => [2, 4]]);
            $temps = [];
            for ($i = 0; $i < 4; $i++) {
                $temps[$i] = (string) random_int(800000000, 899999999);
                $this->posCart($temps[$i], $p, 2, $owner);
            }
            $before = $this->books();
            if ($parallel) {
                $res = $this->race(array_map(fn ($t) => $this->posJob($admin, $t), $temps), 120);
            } else {
                $res = array_map(fn ($t) => $this->runOne($this->posJob($admin, $t)), $temps);
            }
            $sold = (int) DB::table('order_details')->where('product_id', $p)->sum('quantity');

            return [$p, $res, $sold, $this->delta($before, $this->books())];
        };
        [$pSeq, , $soldSeq, $deltaSeq] = $run(false);
        [$pPar, $res, $soldPar, $deltaPar] = $run(true);

        $this->assertSame(6, $soldSeq);
        $this->assertSame(6, $soldPar, 'three of the four sales succeed, never more than the six units: ' . json_encode(array_map(fn ($r) => $r['pos']['success'] ?? $r['error'] ?? null, $res)));
        $this->assertSame(0, (int) DB::table('product_stocks')->where('product_id', $pPar)->value('qty'));
        $this->assertSame(0, (int) DB::table('purchase_products')->where('product_id', $pPar)->sum('remaining_qty'), 'layers equal the shelf');
        $this->assertEquals(2 * 400 + 4 * 410 - 0, DB::table('order_details')->where('product_id', $pPar)->get()->sum(fn ($d) => $d->buying_price * $d->quantity) - (410 - 400) * 0, 'cost taken oldest layer first');
        $this->assertEquals($deltaSeq, $deltaPar, 'AR, Sales, COGS and Inventory moved exactly as for sequential sales');
        $this->assertJournalSettles();
    }

    public function test_the_same_pos_cart_submitted_three_times_at_once_makes_one_sale()
    {
        $this->flag(true);
        $this->systemAccounts();
        $admin = $this->admin();
        $owner = (int) DB::table('users')->where('user_type', 'admin')->orderBy('id')->value('id');
        $p = $this->seedProduct(['M' => 10], 1000, 400);
        $temp = (string) random_int(800000000, 899999999);
        $this->posCart($temp, $p, 3, $owner);
        $res = $this->race(array_map(fn () => $this->posJob($admin, $temp), range(1, 3)), 120);

        $this->assertSame(1, DB::table('orders')->where('guest_id', $temp)->count(), json_encode(array_map(fn ($r) => $r['pos']['success'] ?? $r['error'] ?? null, $res)));
        $this->assertSame(7, (int) DB::table('product_stocks')->where('product_id', $p)->value('qty'));
        $this->assertSame(7, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'));
        $this->assertJournalSettles();
    }

    public function test_the_scheduled_auto_deposit_run_twice_at_once_and_alongside_a_transfer_keeps_the_books_exact()
    {
        $this->flag(true);
        // With no auto method configured the (ready) command is a clean no-op in both processes and must not disturb a manual transfer at the same moment.
        $this->adWiden();
        try {
            $cfg = ['accounts.auto_deposit_approved' => true, 'accounts.auto_deposit_user_id' => $this->adUser()];
            $a = $this->cashAccount(500);
            $b = $this->cashAccount(0);
            $before = DB::table('balance_transfers')->count();
            $res = $this->race([
                ['action' => 'console', 'command' => 'accounts:auto-deposit', 'config' => $cfg],
                ['action' => 'console', 'command' => 'accounts:auto-deposit', 'config' => $cfg],
                $this->req($this->admin(), route('accounts.balance-transfers.store', [], false), ['transaction_type' => 'transfer', 'date' => now()->toDateString(), 'from_account_id' => $a, 'to_account_id' => $b, 'amount' => 100, 'charge' => 0]),
            ], 120);

            $this->assertSame([0, 0], [$res[0]['exit'] ?? -1, $res[1]['exit'] ?? -1], json_encode([$res[0]['output'] ?? $res[0]['error'] ?? null, $res[1]['output'] ?? $res[1]['error'] ?? null]));
            $this->assertEquals(400.0, $this->bal($a));
            $this->assertEquals(100.0, $this->bal($b));
            $this->assertCount(DB::table('balance_transfers')->count() - $before, DB::table('balance_transfers')->orderByDesc('id')->limit(DB::table('balance_transfers')->count() - $before)->pluck('voucher_no')->unique(), 'distinct voucher numbers');
            $this->assertJournalSettles();
        } finally {
            $this->adRestore();
        }
    }

    /** With the database prepared (ENUM values, system user) two simultaneous runs move the balance exactly once and leave a full trail. */
    public function test_two_auto_deposit_runs_at_once_move_the_balance_exactly_once()
    {
        $this->flag(true);
        $this->adWiden();
        try {
            $uid = $this->adUser();
            $cfg = ['accounts.auto_deposit_approved' => true, 'accounts.auto_deposit_user_id' => $uid];
            $source = $this->cashAccount(300);
            $bank = $this->account('asset', 'bank', 0);
            $method = (int) DB::table('transaction_methods')->insertGetId(['name' => 'PC ' . uniqid(), 'type' => 'mobile', 'deposit_type' => 'auto', 'charge' => 0, 'bank_account_id' => $bank, 'source_account_id' => $source, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $res = $this->race([['action' => 'console', 'command' => 'accounts:auto-deposit', 'config' => $cfg], ['action' => 'console', 'command' => 'accounts:auto-deposit', 'config' => $cfg]], 120);

            $this->assertSame([0, 0], [$res[0]['exit'] ?? -1, $res[1]['exit'] ?? -1], json_encode(array_column($res, 'output')));
            $row = DB::table('balance_transfers')->where('from_account_id', $source)->get();
            $this->assertCount(1, $row, 'one auto deposit: ' . json_encode(array_column($res, 'output')));
            $this->assertSame(['auto_deposit', 'completed', $uid], [$row[0]->transaction_type, $row[0]->status, (int) $row[0]->created_by], 'legal, non-blank values and the system user as creator');
            $this->assertEquals(0.0, $this->bal($source), 'the source is emptied once and never goes negative');
            $this->assertEquals(300.0, $this->bal($bank), 'the destination received the balance once');
            $lines = DB::table('account_statement_logs')->where('reference_no', $row[0]->voucher_no)->get();
            $this->assertCount(2, $lines, 'a statement line on each account (the old job wrote none)');
            $this->assertEquals([300.0, 0.0], [(float) $lines->where('account_id', $source)->first()->credit, (float) $lines->where('account_id', $source)->first()->debit]);
            $this->assertEquals([300.0, 0.0], [(float) $lines->where('account_id', $bank)->first()->debit, (float) $lines->where('account_id', $bank)->first()->credit]);
            $this->assertJournalSettles();
            DB::table('transaction_methods')->where('id', $method)->delete();
        } finally {
            $this->adRestore();
        }
    }

    /** R-03/R-04: switched on but the database is not prepared (the shipped schema): the job refuses, posts nothing, exits non-zero. */
    public function test_the_job_switched_on_but_not_ready_refuses_and_posts_nothing()
    {
        $this->flag(true);
        $source = $this->cashAccount(300);
        $bank = $this->account('asset', 'bank', 0);
        $method = (int) DB::table('transaction_methods')->insertGetId(['name' => 'PC ' . uniqid(), 'type' => 'mobile', 'deposit_type' => 'auto', 'charge' => 0, 'bank_account_id' => $bank, 'source_account_id' => $source, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $transfers = DB::table('balance_transfers')->count();
        $r = $this->runOne(['action' => 'console', 'command' => 'accounts:auto-deposit', 'config' => ['accounts.auto_deposit_approved' => true]]);
        $this->assertSame(1, $r['exit'] ?? -1);
        $this->assertStringContainsString('AUTO_DEPOSIT_BLOCKED_NOT_READY', (string) ($r['output'] ?? ''));
        $this->assertStringContainsString('AUTO_DEPOSIT_USER_ID is not set', (string) ($r['output'] ?? ''));
        $this->assertStringContainsString("not allowed", (string) ($r['output'] ?? ''), 'names the missing ENUM values');
        $this->assertEquals([300.0, 0.0], [$this->bal($source), $this->bal($bank)], 'nothing moved');
        $this->assertSame($transfers, DB::table('balance_transfers')->count());
        DB::table('transaction_methods')->where('id', $method)->delete();
    }
}
