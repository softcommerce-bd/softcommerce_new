<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Refund postings: one row per refund actually paid, whatever the channel, with unique keys that make a duplicate impossible. Plus the three system accounts refunds post to. */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('refund_postings')) {
            Schema::create('refund_postings', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('refund_request_id')->nullable()->unique('uq_refund_postings_request');  // a refund request is paid once
                $t->unsignedBigInteger('order_id')->nullable();
                $t->unsignedBigInteger('order_detail_id')->nullable()->index();
                $t->string('method', 10);                                    // wallet | cash | gateway
                $t->decimal('amount', 20, 2);
                $t->unsignedBigInteger('account_id')->nullable();            // cash/bank account for a cash refund
                $t->string('external_ref', 120)->nullable()->unique('uq_refund_postings_external'); // gateway refund / transaction id
                $t->string('idempotency_key', 120)->unique('uq_refund_postings_key');
                $t->unsignedBigInteger('journal_entry_id')->nullable();
                $t->unsignedInteger('created_by')->nullable();
                $t->string('note', 255)->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
        foreach (\App\Services\Accounting\SystemAccounts::definitions() as [$name, $type, $sub, $code]) {
            $exists = DB::table('chart_of_accounts')->where(fn ($q) => $q->where('name', $name)->where('type', $type))->orWhere('code', $code)->exists();
            if (!$exists) {
                DB::table('chart_of_accounts')->insert(['code' => $code, 'name' => $name, 'type' => $type, 'sub_type' => $sub, 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0,
                    'description' => 'Created by the inventory/purchasing upgrade.', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('refund_postings');
    }
};
