<?php

namespace Tests\Feature\Inventory;

use App\Services\Accounting\AutoDepositReadiness;
use Illuminate\Support\Facades\DB;

/** R-03/R-04: the readiness rules that stand between "someone set the switch" and money moving. */
class AutoDepositReadinessTest extends InventoryV2TestCase
{
    private function user(string $type, int $banned): int
    {
        return (int) DB::table('users')->insertGetId(['name' => 'u', 'email' => uniqid() . '@r.invalid', 'password' => '!', 'user_type' => $type, 'banned' => $banned, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function has(array $problems, string $needle): bool
    {
        foreach ($problems as $p) {
            if (str_contains($p, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function test_no_user_configured_is_a_problem()
    {
        config(['accounts.auto_deposit_user_id' => null]);
        $this->assertTrue($this->has(AutoDepositReadiness::problems(), 'AUTO_DEPOSIT_USER_ID is not set'));
    }

    public function test_the_creator_must_exist_and_must_not_be_able_to_sign_in()
    {
        config(['accounts.auto_deposit_user_id' => 999999999]);
        $this->assertTrue($this->has(AutoDepositReadiness::problems(), 'does not exist'));
        foreach ([['admin', 0], ['admin', 1], ['staff', 1], ['customer', 0]] as [$type, $banned]) {
            config(['accounts.auto_deposit_user_id' => $this->user($type, $banned)]);
            $this->assertTrue($this->has(AutoDepositReadiness::problems(), 'can sign in'), "$type banned=$banned is refused");
        }
        foreach ([['system', 0], ['customer', 1]] as [$type, $banned]) {
            config(['accounts.auto_deposit_user_id' => $this->user($type, $banned)]);
            $this->assertFalse($this->has(AutoDepositReadiness::problems(), 'AUTO_DEPOSIT_USER_ID'), "$type banned=$banned is an acceptable non-login creator");
            $this->assertFalse($this->has(AutoDepositReadiness::problems(), 'can sign in'));
        }
    }

    public function test_the_shipped_schema_lacks_the_enum_values_the_job_writes()
    {
        $p = AutoDepositReadiness::problems();
        $this->assertTrue($this->has($p, "balance_transfers.transaction_type"), json_encode($p));
        $this->assertTrue($this->has($p, "'auto_deposit' is not allowed"));
        $this->assertTrue($this->has($p, "balance_transfers.status"));
        $this->assertTrue($this->has($p, "expense_payments.status"));
    }

    public function test_a_charged_method_makes_the_job_not_ready_whatever_is_configured()
    {
        $mk = fn (string $type) => DB::table('chart_of_accounts')->insertGetId(['code' => 'RD-' . uniqid(), 'name' => 'RD ' . uniqid(), 'type' => $type, 'sub_type' => $type === 'expense' ? 'expense' : 'bank', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $src = $mk('asset');
        $dst = $mk('asset');
        DB::table('transaction_methods')->insert(['name' => 'RD ' . uniqid(), 'type' => 'mobile', 'deposit_type' => 'auto', 'charge' => 1.5, 'bank_account_id' => $dst, 'source_account_id' => $src, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertTrue($this->has(AutoDepositReadiness::problems(), 'charged methods are not supported'));
    }
}
