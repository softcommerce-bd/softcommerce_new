<?php

namespace App\Jobs\Chat;

use App\Events\Chat\ChatUpdated;
use App\Models\ChatAccount;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\Chat\InboundProcessor;
use App\Services\Chat\ProviderRegistry;
use App\Services\Chat\Providers\ProviderException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\URL;

class SendChatMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public function __construct(public int $messageId)
    {
    }

    /** Exponential backoff between provider retries: 10s, 30s, 90s, 270s. */
    public function backoff()
    {
        return [10, 30, 90, 270];
    }

    public function handle(ProviderRegistry $providers, InboundProcessor $inbound)
    {
        $message = ChatMessage::find($this->messageId);
        // Only a queued message is sent; a retry after success, or a manual re-run, never double-sends.
        if (!$message || $message->direction !== 'out' || $message->external_id || !in_array($message->status, ['queued', 'failed'], true)) {
            return;
        }
        $conversation = ChatConversation::find($message->chat_conversation_id);
        $account = ChatAccount::find($message->chat_account_id);
        $notify = fn (string $type) => event(new ChatUpdated($message->chat_account_id, $type, $message->chat_conversation_id, $message->id));

        if (!$conversation || !$account || $account->status === 'disabled') {
            return $this->fail($message, 'The connected account is disabled or missing.', $notify);
        }

        $provider = $providers->get($account->platform);
        $window = $provider->windowHours();
        $template = ($message->attachments[0]['type'] ?? null) === 'template' ? $message->attachments[0]['payload'] : null;
        // An approved template is the provider's own way to message outside the free-form window; free text is not.
        if ($template === null && $window !== null && (!$conversation->last_inbound_at || $conversation->last_inbound_at->lt(now()->subHours($window)))) {
            return $this->fail($message, "More than {$window} hours since the customer last wrote: the provider only allows an approved template message now.", $notify);
        }

        $payload = $template !== null ? ['template' => $template, 'text' => null] : ['text' => $message->body];
        if ($template === null && !empty($message->attachments[0]['path'])) {
            // The provider fetches the file itself, so it needs a URL: short-lived, signed, single message.
            $payload['attachment'] = $message->attachments[0] + ['url' => URL::temporarySignedRoute('chat.media', now()->addHour(), ['message' => $message->id, 'index' => 0])];
        }

        try {
            try {
                $mid = $provider->send($account, $conversation, $payload);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // DNS, timeout, provider outage: the same as a rate limit, try again later.
                throw new ProviderException('Could not reach the provider (network error).', ProviderException::RETRY);
            }
        } catch (ProviderException $e) {
            if ($e->kind === ProviderException::RETRY && $this->attempts() < $this->tries) {
                $message->forceFill(['error' => mb_substr($e->getMessage() . ' Retrying automatically.', 0, 500)])->save();
                $message->increment('retry_count');
                $account->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 500)])->save();

                return $this->release($this->backoff()[min($this->attempts() - 1, 3)]);
            }
            if ($e->kind === ProviderException::AUTH) {
                $account->forceFill(['status' => 'expired', 'last_error' => mb_substr($e->getMessage(), 0, 500)])->save();
                $notify('account.health');
            } else {
                $account->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 500)])->save();
            }

            return $this->fail($message, $e->getMessage() . ($e->kind === ProviderException::RETRY ? ' Gave up after several attempts; use Retry to send it again.' : ''), $notify);
        }

        // The provider may already have echoed this message back before we recorded its id.
        ChatMessage::where('chat_account_id', $account->id)->where('external_id', $mid)->where('id', '!=', $message->id)->delete();
        try {
            $message->forceFill(['external_id' => $mid, 'status' => 'sent', 'error' => null, 'provider_at' => now()])->save();
        } catch (QueryException $e) {
            $message->delete();
        }
        $account->forceFill(['last_api_ok_at' => now(), 'last_error' => null])->save();
        if (!$conversation->first_response_at && $message->sent_by) {
            $conversation->forceFill(['first_response_at' => now()])->save();
        }
        $inbound->applyEarly($message->fresh() ?? $message);
        $notify('message.status');
    }

    public function failed(\Throwable $e)
    {
        if ($m = ChatMessage::find($this->messageId)) {
            $m->forceFill(['status' => 'failed', 'error' => 'Delivery failed after several attempts. You can retry it.'])->save();
        }
    }

    private function fail(ChatMessage $message, string $reason, callable $notify)
    {
        $message->forceFill(['status' => 'failed', 'error' => mb_substr($reason, 0, 500)])->save();
        $notify('message.status');
    }
}
