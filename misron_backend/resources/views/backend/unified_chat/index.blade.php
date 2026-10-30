@extends('backend.layouts.app')

@section('content')
<link rel="stylesheet" href="{{ static_asset('assets/css/unified-chat.css') }}?v=1">

<div id="uc" class="uc" data-view="list" data-base="{{ url('admin/unified-chat') }}" data-csrf="{{ csrf_token() }}">
    <aside class="uc-nav" aria-label="{{ translate('Chat navigation') }}">
        <nav id="uc-folders"></nav>
        <div class="uc-nav-sec" id="uc-sections"></div>
    </aside>

    <section class="uc-list" aria-label="{{ translate('Conversations') }}">
        <div class="uc-list-head">
            <button class="uc-btn uc-only-mobile" id="uc-open-nav" aria-label="{{ translate('Menu') }}">☰</button>
            <input id="uc-search" type="search" placeholder="{{ translate('Search name, phone, message') }}" aria-label="{{ translate('Search conversations') }}">
            <button class="uc-btn" id="uc-filter-toggle" aria-expanded="false" aria-controls="uc-filters">{{ translate('Filters') }}</button>
        </div>
        <div id="uc-filters" class="uc-filters" hidden>
            <select id="f-platform" aria-label="{{ translate('Platform') }}"><option value="">{{ translate('All platforms') }}</option>
                <option value="facebook">Facebook</option><option value="instagram">Instagram</option><option value="whatsapp">WhatsApp</option><option value="tiktok">TikTok</option></select>
            <select id="f-account" aria-label="{{ translate('Account') }}"><option value="">{{ translate('All accounts') }}</option></select>
            <select id="f-assignee" aria-label="{{ translate('Assigned staff') }}"><option value="">{{ translate('Any staff') }}</option></select>
            <select id="f-priority" aria-label="{{ translate('Priority') }}"><option value="">{{ translate('Any priority') }}</option>
                <option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select>
            <input type="date" id="f-from" aria-label="{{ translate('From date') }}"><input type="date" id="f-to" aria-label="{{ translate('To date') }}">
        </div>
        <ul id="uc-convs" class="uc-convs" role="listbox" aria-label="{{ translate('Conversation list') }}"></ul>
        <button class="uc-btn uc-more" id="uc-convs-more" hidden>{{ translate('Load more') }}</button>
    </section>

    <section class="uc-chat" aria-label="{{ translate('Conversation') }}">
        <div id="uc-empty" class="uc-empty">{{ translate('Select a conversation') }}</div>
        <div id="uc-pane" class="uc-pane" hidden>
            <header class="uc-chat-head">
                <button class="uc-btn uc-only-mobile" id="uc-back" aria-label="{{ translate('Back to conversations') }}">←</button>
                <div class="uc-chat-title"><strong id="uc-title"></strong><small id="uc-sub"></small></div>
                <div class="uc-actions">
                    <select id="uc-assign" aria-label="{{ translate('Assign to') }}" hidden></select>
                    <select id="uc-status" aria-label="{{ translate('Status') }}"><option value="open">Open</option><option value="pending">Pending</option><option value="resolved">Resolved</option><option value="archived">Archived</option></select>
                    <button class="uc-btn" id="uc-order-btn" hidden>{{ translate('Create order') }}</button>
                    <button class="uc-btn uc-only-narrow" id="uc-info-btn">ⓘ</button>
                </div>
            </header>
            <div id="uc-banner" class="uc-banner" role="status" hidden></div>
            <div id="uc-msgs" class="uc-msgs" role="log" aria-live="polite" tabindex="0">
                <button class="uc-btn" id="uc-earlier" hidden>{{ translate('Load earlier messages') }}</button>
            </div>
            <form id="uc-composer" class="uc-composer" autocomplete="off">
                <div class="uc-tabs" role="tablist">
                    <button type="button" role="tab" aria-selected="true" data-mode="reply">{{ translate('Reply') }}</button>
                    <button type="button" role="tab" aria-selected="false" data-mode="note" id="uc-note-tab" hidden>{{ translate('Internal note') }}</button>
                    <select id="uc-quick" aria-label="{{ translate('Quick replies') }}"><option value="">{{ translate('Quick replies') }}</option></select>
                </div>
                <div class="uc-compose-row">
                    <label class="uc-btn uc-attach" id="uc-attach-label" title="{{ translate('Attach') }}">📎<input type="file" id="uc-file" hidden accept="image/*,.pdf,video/mp4"></label>
                    <textarea id="uc-text" rows="1" maxlength="4000" placeholder="{{ translate('Type a message') }}" aria-label="{{ translate('Message') }}"></textarea>
                    <button class="uc-btn uc-primary" id="uc-send" type="submit">{{ translate('Send') }}</button>
                </div>
                <small id="uc-file-name"></small>
            </form>
            <ul id="uc-notes" class="uc-notes" hidden></ul>
        </div>
    </section>

    <aside class="uc-info" id="uc-info" aria-label="{{ translate('Customer details') }}">
        <button class="uc-btn uc-only-narrow" id="uc-info-close" aria-label="{{ translate('Close') }}">✕</button>
        <div id="uc-info-body"></div>
    </aside>

    <section class="uc-page" id="uc-page" hidden></section>
</div>

<div class="uc-modal" id="uc-modal" role="dialog" aria-modal="true" aria-labelledby="uc-modal-title" hidden>
    <div class="uc-modal-box">
        <header><strong id="uc-modal-title"></strong><button class="uc-btn" id="uc-modal-close" aria-label="{{ translate('Close') }}">✕</button></header>
        <div id="uc-modal-body"></div>
    </div>
</div>
@endsection

@section('script')
<script src="{{ static_asset('assets/js/unified-chat.js') }}?v=1"></script>
@endsection
