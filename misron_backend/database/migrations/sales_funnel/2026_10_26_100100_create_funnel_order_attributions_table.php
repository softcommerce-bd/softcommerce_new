<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a funnel order came from: campaign parameters, ad click ids, the kind
 * of device, and whether it was a test order placed by staff.
 *
 * One row per combined order, written in the same transaction as the order.
 * Kept out of the `orders` table so the store's own table gains no columns.
 * Click ids are stored only when the visitor's tracking consent allowed it.
 */
class CreateFunnelOrderAttributionsTable extends Migration
{
    public function up()
    {
        Schema::create('funnel_order_attributions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('combined_order_id')->unique();
            $table->unsignedBigInteger('funnel_id')->index();
            $table->string('visitor_id', 64)->nullable();
            $table->string('session_id', 64)->nullable();
            $table->string('source', 40)->default('direct');
            $table->string('utm_source', 150)->nullable();
            $table->string('utm_medium', 150)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('fbclid', 255)->nullable();
            $table->string('gclid', 255)->nullable();
            $table->string('referrer_host', 150)->nullable();
            $table->string('device', 10)->default('unknown');
            $table->string('consent', 10)->default('unknown');
            $table->boolean('is_test')->default(false);
            $table->timestamps();

            $table->index(['funnel_id', 'is_test']);
            $table->index('utm_campaign');
        });
    }

    public function down()
    {
        Schema::dropIfExists('funnel_order_attributions');
    }
}
