<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled unpublishing. `scheduled_at` (go live) already exists; this adds the
 * matching "take offline" time. Both are honoured by Funnel::isLive() on every
 * public read, so a page appears and disappears on time even if the scheduler
 * is late; the scheduler only tidies the status afterwards.
 *
 * Additive and nullable: every existing funnel keeps NULL (no end time).
 */
class AddPublishingColumnsToFunnelsTable extends Migration
{
    public function up()
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->timestamp('unpublish_at')->nullable()->after('scheduled_at');
            $table->index(['status', 'scheduled_at']);
            $table->index('unpublish_at');
        });
    }

    public function down()
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropIndex(['unpublish_at']);
            $table->dropColumn('unpublish_at');
        });
    }
}
