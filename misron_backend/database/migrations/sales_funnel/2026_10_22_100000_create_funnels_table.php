<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales funnels. A funnel is only a wrapper around a page document: products,
 * stock, customers and orders all stay in their existing tables.
 *
 * draft_content is the working copy the editor autosaves into. What customers
 * see is an immutable row in funnel_versions, pointed to by published_version_id.
 */
class CreateFunnelsTable extends Migration
{
    public function up()
    {
        Schema::create('funnels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('internal_name', 150);
            $table->string('title', 200);
            $table->string('slug', 80)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');

            $table->longText('draft_content');
            $table->unsignedInteger('draft_revision')->default(1);
            $table->char('draft_hash', 40)->nullable();
            $table->timestamp('draft_updated_at')->nullable();

            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('unpublished_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();

            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->integer('og_image_upload_id')->nullable();
            $table->integer('thumbnail_upload_id')->nullable();

            $table->longText('settings')->nullable();
            $table->unsignedBigInteger('source_template_id')->nullable();

            // Product ids referenced by the draft's offer bindings, kept in sync
            // on every save so the list can show and filter by product without
            // decoding page documents.
            $table->text('product_ids')->nullable();

            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deleted_at']);
            $table->index('published_version_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('funnels');
    }
}
