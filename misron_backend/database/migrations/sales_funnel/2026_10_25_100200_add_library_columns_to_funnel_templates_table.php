<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template library. A template exists once per language (a Bengali and an
 * English document), grouped by `template_key`; `thumbnail_path` points at a
 * static picture shipped with the admin.
 *
 * Additive and nullable. Rows are written by `php artisan funnel:templates`.
 */
class AddLibraryColumnsToFunnelTemplatesTable extends Migration
{
    public function up()
    {
        Schema::table('funnel_templates', function (Blueprint $table) {
            $table->string('template_key', 60)->nullable()->after('slug');
            $table->string('language', 5)->default('bn')->after('template_key');
            $table->string('thumbnail_path', 255)->nullable()->after('thumbnail_upload_id');
            $table->unique(['template_key', 'language']);
        });
    }

    public function down()
    {
        Schema::table('funnel_templates', function (Blueprint $table) {
            $table->dropUnique(['template_key', 'language']);
            $table->dropColumn(['template_key', 'language', 'thumbnail_path']);
        });
    }
}
