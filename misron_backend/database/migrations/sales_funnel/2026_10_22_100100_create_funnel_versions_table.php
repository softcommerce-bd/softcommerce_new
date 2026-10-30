<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable page snapshots. A row is written when a funnel is published (kind
 * "published") or just before a rollback overwrites the draft (kind
 * "restore_point"). Rows are never updated, which is what keeps the live page
 * stable while a merchant edits the next draft.
 */
class CreateFunnelVersionsTable extends Migration
{
    public function up()
    {
        Schema::create('funnel_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('funnel_id');
            $table->unsignedInteger('version_number');
            $table->string('kind', 20)->default('published');
            $table->unsignedSmallInteger('schema_version');
            $table->longText('content');
            $table->char('content_hash', 40);
            $table->string('label', 120)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['funnel_id', 'version_number']);
            $table->index(['funnel_id', 'kind']);
            $table->foreign('funnel_id')->references('id')->on('funnels')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('funnel_versions');
    }
}
