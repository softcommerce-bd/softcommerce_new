<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable starting points. Templates and saved sections never carry product
 * ids: products are chosen per funnel through bindings, so a template stays
 * valid after the catalogue changes.
 */
class CreateFunnelTemplatesAndSectionsTables extends Migration
{
    public function up()
    {
        Schema::create('funnel_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('slug', 80)->unique();
            $table->text('description')->nullable();
            $table->string('category', 50)->default('general');
            $table->integer('thumbnail_upload_id')->nullable();
            $table->longText('content');
            $table->longText('product_slots')->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'sort_order']);
        });

        Schema::create('funnel_saved_sections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('category', 50)->default('general');
            $table->integer('thumbnail_upload_id')->nullable();
            $table->longText('content');
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('funnel_saved_sections');
        Schema::dropIfExists('funnel_templates');
    }
}
