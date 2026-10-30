<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Chat reports from stored data only. Every number starts from ChatAccess::conversations(), so scope follows permissions. */
class ChatAnalytics
{
    public function __construct(private ChatAccess $access)
    {
    }

    public function range(string $preset, ?string $from, ?string $to): array
    {
        $today = now()->startOfDay();

        return match ($preset) {
            'today' => [$today, now()],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subSecond()],
            '30d' => [$today->copy()->subDays(29), now()],
            'custom' => [Carbon::parse($from ?: $today)->startOfDay(), Carbon::parse($to ?: now())->endOfDay()],
            default => [$today->copy()->subDays(6), now()],
        };
    }

    public function report(User $user, Carbon $from, Carbon $to, array $filter = []): array
    {
        $scope = $this->access->conversations($user);
        if (!empty($filter['platform'])) $scope->where('platform', $filter['platform']);
        if (!empty($filter['account_id'])) $scope->where('chat_account_id', (int) $filter['account_id']);
        if (!empty($filter['staff_id'])) $scope->where('assigned_to', (int) $filter['staff_id']);

        $ids = (clone $scope)->select('id');
        $inRange = fn ($q, $col = 'created_at') => $q->whereBetween($col, [$from, $to]);

        $messages = ChatMessage::whereIn('chat_conversation_id', $ids)->whereBetween('created_at', [$from, $to]);
        $new = $inRange(clone $scope)->get(['id', 'created_at', 'first_response_at', 'resolved_at']);

        $avg = function ($rows, string $end) {
            $secs = $rows->filter(fn ($c) => $c->{$end})->map(fn ($c) => max(0, $c->{$end}->diffInSeconds($c->created_at)));

            return $secs->isEmpty() ? null : (int) round($secs->avg());
        };

        $orderIds = DB::table('chat_conversation_orders')->whereIn('chat_conversation_id', $ids)->whereBetween('created_at', [$from, $to]);
        $revenue = DB::table('orders')->whereIn('combined_order_id', (clone $orderIds)->select('combined_order_id'))->where('delivery_status', '!=', 'cancelled')->sum('grand_total');

        return [
            'range' => [$from->toDateString(), $to->toDateString()],
            'totals' => [
                'conversations' => (clone $scope)->whereBetween('last_message_at', [$from, $to])->count(),
                'new' => $new->count(),
                'active' => (clone $scope)->whereIn('status', ['open', 'pending'])->count(),
                'unread' => (clone $scope)->where('unread_count', '>', 0)->count(),
                'unassigned' => (clone $scope)->whereNull('assigned_to')->whereIn('status', ['open', 'pending'])->count(),
                'resolved' => (clone $scope)->whereBetween('resolved_at', [$from, $to])->count(),
                'incoming' => (clone $messages)->where('direction', 'in')->count(),
                'outgoing' => (clone $messages)->where('direction', 'out')->count(),
                'avg_first_response_seconds' => $avg($new, 'first_response_at'),
                'avg_resolution_seconds' => $avg($new, 'resolved_at'),
                'chat_orders' => (clone $orderIds)->count(),
                'chat_revenue' => round((float) $revenue, 2),
            ],
            'by_platform' => (clone $scope)->whereBetween('last_message_at', [$from, $to])->select('platform', DB::raw('count(*) as total'))->groupBy('platform')->pluck('total', 'platform'),
            'by_account' => (clone $scope)->whereBetween('chat_conversations.last_message_at', [$from, $to])->join('chat_accounts', 'chat_accounts.id', '=', 'chat_conversations.chat_account_id')
                ->select('chat_accounts.name', 'chat_conversations.platform', DB::raw('count(*) as total'))->groupBy('chat_accounts.name', 'chat_conversations.platform')->get(),
            'agents' => $this->agents($scope, $messages, $from, $to),
        ];
    }

    private function agents($scope, $messages, Carbon $from, Carbon $to): array
    {
        $sent = (clone $messages)->where('direction', 'out')->whereNotNull('sent_by')->select('sent_by', DB::raw('count(*) as total'))->groupBy('sent_by')->pluck('total', 'sent_by');
        $assigned = (clone $scope)->whereNotNull('assigned_to')->select('assigned_to', DB::raw('count(*) as total'))->groupBy('assigned_to')->pluck('total', 'assigned_to');
        $resolved = (clone $scope)->whereNotNull('assigned_to')->whereBetween('resolved_at', [$from, $to])->select('assigned_to', DB::raw('count(*) as total'))->groupBy('assigned_to')->pluck('total', 'assigned_to');
        $users = User::whereIn('id', $sent->keys()->merge($assigned->keys())->unique())->pluck('name', 'id');

        return $users->map(fn ($name, $id) => ['id' => $id, 'name' => $name, 'assigned' => $assigned[$id] ?? 0, 'resolved' => $resolved[$id] ?? 0, 'replies' => $sent[$id] ?? 0])->values()->all();
    }
}
