<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What visitors did on a live funnel page: views, product views and
 * selections, checkout starts. Used only for the funnel analytics dashboard.
 *
 * No personal data: the visitor and session ids are random values made by the
 * browser, there is no IP address, name, phone or full URL. Orders and revenue
 * are never counted from this table; they are read from the orders themselves.
 * A repeated event id is ignored (unique key), so a retried beacon counts once.
 */
class CreateFunnelEventsTable extends Migration
{
    public function up()
    {
        Schema::create('funnel_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_id', 64)->unique();
            $table->unsignedBigInteger('funnel_id');
            $table->unsignedBigInteger('funnel_version_id')->nullable();
            $table->string('type', 32);
            $table->string('visitor_id', 64)->nullable();
            $table->string('session_id', 64)->nullable();
            $table->string('offer_key', 64)->nullable();
            $table->string('device', 10)->default('unknown');
            $table->string('source', 40)->default('direct');
            $table->string('medium', 60)->nullable();
            $table->string('campaign', 120)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['funnel_id', 'created_at']);
            $table->index(['funnel_id', 'type', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('funnel_events');
    }
}
