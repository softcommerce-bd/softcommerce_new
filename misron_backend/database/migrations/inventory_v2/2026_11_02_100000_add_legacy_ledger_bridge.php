<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy-ledger bridge: journal lines can be marked "mirrored" (the legacy screen already moved the balance),
 * a per-account baseline records each balance when journalling started (so drift can be measured), and the
 * visible suspense account for one-sided legacy postings exists. Additive; nothing is rewritten.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('journal_lines') && !Schema::hasColumn('journal_lines', 'mirrored')) {
            Schema::table('journal_lines', function (Blueprint $t) {
                $t->boolean('mirrored')->default(false)->after('memo');
            });
        }
        if (!Schema::hasTable('ledger_baselines')) {
            Schema::create('ledger_baselines', function (Blueprint $t) {
                $t->unsignedBigInteger('account_id')->primary();
                $t->decimal('balance', 20, 2);
                $t->timestamp('taken_at')->nullable();
                $t->string('taken_by', 80)->nullable();
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
        Schema::dropIfExists('ledger_baselines');
        if (Schema::hasColumn('journal_lines', 'mirrored')) {
            Schema::table('journal_lines', fn (Blueprint $t) => $t->dropColumn('mirrored'));
        }
    }
};
