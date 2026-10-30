<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Services\Chat\ChatAccess;
use App\Services\Chat\ChatAnalytics;
use App\Services\Chat\ChatAudit;
use Illuminate\Http\Request;

class ChatAnalyticsController extends Controller
{
    public function __construct(private ChatAnalytics $analytics, private ChatAccess $access)
    {
        $this->middleware(['permission:view_unified_chat']);
        $this->middleware(['permission:view_chat_analytics'])->only('report');
        $this->middleware(['permission:export_chat_data'])->only('export');
    }

    public function report(Request $request)
    {
        [$from, $to] = $this->analytics->range((string) $request->query('range', '7d'), $request->query('from'), $request->query('to'));

        return response()->json($this->analytics->report($request->user(), $from, $to, $request->only(['platform', 'account_id', 'staff_id'])));
    }

    /** CSV of conversations the user may see. Formula-looking cells are neutralised against spreadsheet injection. */
    public function export(Request $request)
    {
        [$from, $to] = $this->analytics->range((string) $request->query('range', '30d'), $request->query('from'), $request->query('to'));
        $q = $this->access->conversations($request->user())->with(['account:id,name', 'assignee:id,name'])->whereBetween('last_message_at', [$from, $to])->orderBy('id');
        ChatAudit::log($request->user()->id, 'export', null, null, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);

        $safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;

        return response()->streamDownload(function () use ($q, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'platform', 'account', 'customer', 'phone', 'status', 'priority', 'assigned_to', 'messages', 'first_message_at', 'last_message_at']);
            $q->chunkById(500, function ($rows) use ($out, $safe) {
                $counts = ChatMessage::whereIn('chat_conversation_id', $rows->pluck('id'))->selectRaw('chat_conversation_id, count(*) as n')->groupBy('chat_conversation_id')->pluck('n', 'chat_conversation_id');
                foreach ($rows as $c) {
                    fputcsv($out, array_map($safe, [$c->id, $c->platform, $c->account->name ?? '', $c->customer_name, $c->customer_phone, $c->status, $c->priority, $c->assignee->name ?? '', $counts[$c->id] ?? 0, $c->created_at, $c->last_message_at]));
                }
            });
            fclose($out);
        }, 'unified-chat-export-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
