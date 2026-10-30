<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every hosted payment session the store opens with a gateway (Paystation
 * today), for website and funnel orders alike.
 *
 * Without it the store cannot ask the gateway later what happened to a
 * payment: the invoice number sent to the gateway was never kept. The expiry
 * of unpaid online orders (orders:expire-unpaid) uses it to make sure an order
 * is only cancelled when no payment for it went through.
 */
class CreatePaymentGatewayAttemptsTable extends Migration
{
    public function up()
    {
        Schema::create('payment_gateway_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('combined_order_id')->index();
            $table->string('gateway', 30);
            $table->string('invoice_number', 80)->unique();
            $table->decimal('amount', 20, 2)->default(0);
            // started, paid, failed, unknown
            $table->string('status', 20)->default('started');
            $table->string('gateway_status', 40)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_gateway_attempts');
    }
}
