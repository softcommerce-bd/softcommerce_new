<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per checkout submitted through a funnel page.
 *
 * It is what makes a submission safe to repeat: the browser sends an idempotency
 * key with every submit, the key is stored (hashed, together with the funnel)
 * under a unique index, and a second submit with the same key finds the first
 * attempt instead of placing a second order. It also keeps, unchanged, the
 * prices the customer agreed to (`quote_snapshot`), whatever the catalogue does
 * afterwards.
 *
 * No personal data is stored here: names, phone numbers and addresses live on
 * the order. Additive only; no existing table is changed.
 */
class CreateFunnelCheckoutAttemptsTable extends Migration
{
    public function up()
    {
        Schema::create('funnel_checkout_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('funnel_id')->index();
            $table->unsignedBigInteger('funnel_version_id');
            // sha256 of funnel id + the browser's key: one attempt per key per funnel.
            $table->char('key_hash', 64)->unique();
            // sha256 of the normalised submission: a reused key with different content is refused.
            $table->char('request_hash', 64);
            // started (in flight) | placed | rejected (refused, final) | errored (may be retried)
            $table->string('status', 20)->default('started');
            $table->string('error_code', 60)->nullable();
            $table->char('quote_fingerprint', 40)->nullable();
            $table->unsignedBigInteger('combined_order_id')->nullable()->index();
            $table->string('payment_type', 40)->nullable();
            $table->longText('quote_snapshot')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('funnel_checkout_attempts');
    }
}
