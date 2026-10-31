<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactional outbox for legacy postings. The balance change a legacy screen makes and the outbox row that describes it are
 * written in the SAME database transaction, so they commit or roll back together. A separate step turns each committed group
 * (one outermost transaction = one token) into exactly one balanced journal entry and marks the rows processed in the same
 * transaction as the journal insert. If the process dies after the business commit, the rows stay unprocessed and the
 * scheduled `ledger:process-outbox` (or the next request) completes them. Additive; nothing is rewritten.
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('ledger_outbox')) {
            Schema::create('ledger_outbox', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('token', 40);                         // one outermost business transaction
                $t->unsignedBigInteger('account_id');
                $t->decimal('delta', 20, 2);                     // change of current_balance, in the account's own sign
                $t->string('source_type', 40)->nullable();
                $t->unsignedBigInteger('source_id')->nullable();
                $t->string('description', 255)->nullable();
                $t->unsignedInteger('created_by')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->timestamp('processed_at')->nullable();
                $t->unsignedBigInteger('journal_entry_id')->nullable();
                $t->string('last_error', 255)->nullable();
                $t->unsignedInteger('attempts')->default(0);
                $t->index(['processed_at', 'id'], 'idx_outbox_pending');
                $t->index('token', 'idx_outbox_token');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('ledger_outbox');
    }
};
