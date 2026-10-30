<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who changed what on a funnel. No foreign key on purpose: the trail must
 * outlive the funnel it describes.
 */
class CreateFunnelAuditLogsTable extends Migration
{
    public function up()
    {
        Schema::create('funnel_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('funnel_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 40);
            $table->longText('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['funnel_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('funnel_audit_logs');
    }
}
