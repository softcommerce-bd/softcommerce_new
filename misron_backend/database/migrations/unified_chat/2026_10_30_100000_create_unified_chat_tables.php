<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified Chat schema. Additive only: every table is new and prefixed `chat_`,
 * nothing existing is altered. down() drops only what up() created.
 */
class CreateUnifiedChatTables extends Migration
{
    public function up()
    {
        Schema::create('chat_accounts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('platform', 20);                 // facebook | instagram | whatsapp | tiktok
            $t->string('external_id', 100);             // page id / IG id / WhatsApp phone_number_id / TikTok business id
            $t->string('name', 190);
            $t->string('handle', 190)->nullable();
            $t->string('status', 20)->default('connected'); // connected | expired | error | disabled | pending_approval
            $t->text('credentials')->nullable();        // encrypted JSON, never sent to the browser
            $t->timestamp('token_expires_at')->nullable();
            $t->boolean('inbox_enabled')->default(true);
            $t->timestamp('last_webhook_at')->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamp('last_api_ok_at')->nullable();
            $t->timestamp('last_sync_at')->nullable();
            $t->string('last_error', 500)->nullable();
            $t->text('meta')->nullable();
            $t->unsignedInteger('connected_by')->nullable();
            $t->timestamps();
            $t->unique(['platform', 'external_id']);
        });

        Schema::create('chat_account_user', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('chat_account_id');
            $t->unsignedInteger('user_id');
            $t->timestamps();
            $t->unique(['chat_account_id', 'user_id']);
            $t->index('user_id');
        });

        Schema::create('chat_conversations', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('chat_account_id');
            $t->string('platform', 20);
            $t->string('external_id', 150);             // the customer's id on that platform (PSID / IGSID / wa_id)
            $t->string('customer_name', 190)->nullable();
            $t->string('customer_handle', 190)->nullable();
            $t->string('customer_avatar', 500)->nullable();
            $t->string('customer_phone', 40)->nullable();
            $t->unsignedInteger('user_id')->nullable(); // linked SoftCommerce customer (manual or verified phone only)
            $t->string('status', 20)->default('open');  // open | pending | resolved | archived
            $t->string('priority', 10)->default('normal');
            $t->unsignedInteger('assigned_to')->nullable();
            $t->unsignedInteger('unread_count')->default(0);
            $t->string('last_message_preview', 200)->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamp('last_inbound_at')->nullable(); // drives provider messaging windows
            $t->timestamp('first_response_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->text('tags')->nullable();
            $t->text('customer_notes')->nullable();
            $t->timestamps();
            $t->unique(['chat_account_id', 'external_id']);
            $t->index(['status', 'last_message_at']);
            $t->index(['assigned_to', 'status']);
            $t->index(['chat_account_id', 'last_message_at']);
            $t->index('user_id');
        });

        Schema::create('chat_messages', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('chat_conversation_id');
            $t->unsignedBigInteger('chat_account_id');
            $t->string('external_id', 190)->nullable(); // provider message id; null until the provider confirms a send
            $t->string('client_ref', 64)->nullable();   // idempotency key of an outgoing send
            $t->string('direction', 3);                 // in | out
            $t->string('type', 20)->default('text');
            $t->text('body')->nullable();
            $t->text('attachments')->nullable();
            $t->string('status', 20)->default('received'); // received | queued | sent | delivered | read | failed
            $t->string('error', 500)->nullable();
            $t->unsignedInteger('sent_by')->nullable();
            $t->unsignedInteger('retry_count')->default(0);
            $t->timestamp('provider_at')->nullable();
            $t->timestamps();
            $t->unique(['chat_account_id', 'external_id']);
            $t->unique(['chat_conversation_id', 'client_ref']);
            $t->index(['chat_conversation_id', 'id']);
        });

        Schema::create('chat_notes', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('chat_conversation_id');
            $t->unsignedInteger('user_id');
            $t->text('body');
            $t->timestamps();
            $t->index('chat_conversation_id');
        });

        Schema::create('chat_saved_replies', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('title', 120);
            $t->text('body');
            $t->string('lang', 5)->default('en');       // en | bn
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('chat_settings', function (Blueprint $t) {
            $t->string('key', 100)->primary();
            $t->longText('value')->nullable();
            $t->timestamps();
        });

        Schema::create('chat_webhook_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('source', 12);                   // direct | gateway
            $t->string('platform', 20);
            $t->string('event_key', 190);               // dedupe key
            $t->longText('payload');
            $t->string('status', 12)->default('pending'); // pending | done | failed | dead
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->string('error', 500)->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->unique(['platform', 'event_key']);
            $t->index(['status', 'created_at']);
        });

        Schema::create('chat_conversation_orders', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('chat_conversation_id');
            $t->unsignedBigInteger('combined_order_id');
            $t->string('idempotency_key', 64)->unique();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();
            $t->index('chat_conversation_id');
            $t->index('combined_order_id');
        });

        Schema::create('chat_audit_logs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('user_id')->nullable();
            $t->string('action', 60);
            $t->string('subject_type', 40)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->text('context')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['subject_type', 'subject_id']);
            $t->index('created_at');
        });
    }

    public function down()
    {
        foreach (['chat_audit_logs', 'chat_conversation_orders', 'chat_webhook_events', 'chat_settings', 'chat_saved_replies',
                  'chat_notes', 'chat_messages', 'chat_conversations', 'chat_account_user', 'chat_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
