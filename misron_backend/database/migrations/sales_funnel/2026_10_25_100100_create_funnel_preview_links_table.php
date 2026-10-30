<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secret links that let someone without an account see a funnel's draft.
 *
 * Only a hash of the link's secret is stored, so a database dump cannot be
 * used to open a preview. A link belongs to one funnel, expires, and can be
 * revoked. Previews never place orders.
 */
class CreateFunnelPreviewLinksTable extends Migration
{
    public function up()
    {
        Schema::create('funnel_preview_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('funnel_id')->index();
            // sha256 of the secret in the link.
            $table->char('token_hash', 64)->unique();
            $table->string('label', 120)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['funnel_id', 'revoked_at', 'expires_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('funnel_preview_links');
    }
}
