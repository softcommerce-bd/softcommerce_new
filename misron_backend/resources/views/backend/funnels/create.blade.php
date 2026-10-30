@extends('backend.layouts.app')

@section('content')
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="h3">{{ translate('Create Funnel') }}</h1>
                <p class="text-muted mb-0 fs-13">{{ translate('Pick a design, choose your product and give the page a name. You can change everything in the editor afterwards.') }}</p>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('funnels.store') }}" method="POST" id="funnel-create-form">
        @csrf
        <input type="hidden" name="template_id" id="funnel-template-id" value="{{ old('template_id', (string) (int) request('template') ?: '') }}">

        {{-- 1. Design --}}
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
                <h5 class="mb-0 h6">{{ translate('1. Choose a design') }}</h5>
                <div class="btn-group btn-group-toggle fs-12" data-toggle="buttons" role="group" aria-label="{{ translate('Page language') }}">
                    <label class="btn btn-outline-primary btn-sm active">
                        <input type="radio" name="funnel_language" value="bn" checked autocomplete="off"> বাংলা
                    </label>
                    <label class="btn btn-outline-primary btn-sm">
                        <input type="radio" name="funnel_language" value="en" autocomplete="off"> English
                    </label>
                </div>
            </div>
            <div class="card-body">
                @if (count($gallery) === 0)
                    <p class="text-muted mb-0">
                        {{ translate('No templates are installed yet. Run') }} <code>php artisan funnel:templates</code>.
                        {{ translate('You can still create a blank page below.') }}
                    </p>
                @endif
                <div class="row gutters-10" id="funnel-template-grid">
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <label class="funnel-card h-100 mb-0" data-template="">
                            <span class="funnel-thumb funnel-thumb-blank"><i class="las la-plus"></i></span>
                            <span class="funnel-card-body">
                                <strong class="d-block">{{ translate('Blank page') }}</strong>
                                <small class="text-muted">{{ translate('Start from nothing and build with blocks.') }}</small>
                            </span>
                        </label>
                    </div>
                    @foreach ($gallery as $template)
                        <div class="col-6 col-md-4 col-xl-3 mb-3">
                            <label class="funnel-card h-100 mb-0"
                                data-template="{{ $template['key'] }}"
                                @foreach ($template['languages'] as $lang => $info) data-id-{{ $lang }}="{{ $info['id'] }}" @endforeach>
                                @if ($template['thumbnail'])
                                    <img class="funnel-thumb" src="{{ $template['thumbnail'] }}" alt="" loading="lazy">
                                @else
                                    <span class="funnel-thumb funnel-thumb-blank"><i class="las la-layer-group"></i></span>
                                @endif
                                <span class="funnel-card-body">
                                    @foreach ($template['languages'] as $lang => $info)
                                        <span class="funnel-lang funnel-lang-{{ $lang }}">
                                            <strong class="d-block">{{ $info['name'] }}</strong>
                                            <small class="text-muted d-block mb-1">{{ $info['description'] }}</small>
                                            @foreach ($info['tags'] as $tag)
                                                <span class="badge badge-inline badge-light">{{ $tag }}</span>
                                            @endforeach
                                        </span>
                                    @endforeach
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- 2. Product(s) --}}
        @foreach ($gallery as $template)
            @foreach ($template['languages'] as $lang => $info)
                <div class="card funnel-slots d-none" data-slots-for="{{ $template['key'] }}" data-slots-lang="{{ $lang }}">
                    <div class="card-header">
                        <h5 class="mb-0 h6">{{ translate('2. Choose your product') }}</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted fs-13">{{ translate('The page shows this product live from your store: its name, photos, price and stock. You can skip this and choose it in the editor.') }}</p>
                        @foreach ($info['slots'] as $slot)
                            <div class="form-group funnel-picker" data-slot="{{ $slot['key'] }}" data-field="slots[{{ $slot['key'] }}]">
                                <label class="fw-700">{{ $slot['label'] }}</label>
                                @if (!empty($slot['hint']))
                                    <small class="d-block text-muted mb-1">{{ $slot['hint'] }}</small>
                                @endif
                                <input type="hidden" class="funnel-picker-value" value="">
                                <div class="funnel-picker-chosen d-none align-items-center mb-2"></div>
                                <input type="search" class="form-control funnel-picker-search" autocomplete="off"
                                    placeholder="{{ translate('Search by product name, SKU or ID') }}">
                                <div class="list-group funnel-picker-results mt-1 d-none"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endforeach

        {{-- 3. Details --}}
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 h6" id="funnel-details-heading">{{ translate('2. Funnel details') }}</h5>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="fw-700" for="funnel-title">
                        {{ translate('Page title') }} <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="funnel-title" name="title" value="{{ old('title') }}"
                        maxlength="200" required autofocus
                        placeholder="{{ translate('e.g. Blender Mega Offer') }}">
                    <small class="form-text text-muted">{{ translate('Shown in the browser tab and when the link is shared. Bengali is fine.') }}</small>
                </div>

                <div class="form-group">
                    <label class="fw-700" for="funnel-slug">{{ translate('Page address') }}</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-soft-secondary fs-12">{{ $publicBase }}</span>
                        </div>
                        <input type="text" class="form-control" id="funnel-slug" name="slug" value="{{ old('slug') }}"
                            maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" autocomplete="off" spellcheck="false"
                            placeholder="{{ translate('blender-mega-offer') }}">
                    </div>
                    <small class="form-text text-muted">{{ translate('Lowercase English letters, numbers and dashes. Leave empty to create it from the title.') }}</small>
                </div>

                <div class="form-group">
                    <label class="fw-700" for="funnel-internal-name">{{ translate('Internal name') }}</label>
                    <input type="text" class="form-control" id="funnel-internal-name" name="internal_name" value="{{ old('internal_name') }}"
                        maxlength="150" placeholder="{{ translate('e.g. Blender – Eid campaign – Facebook') }}">
                    <small class="form-text text-muted">{{ translate('Only staff see this. Useful when several funnels sell the same product.') }}</small>
                </div>

                <div class="form-group mb-0">
                    <label class="fw-700" for="funnel-description">{{ translate('Notes') }}</label>
                    <textarea class="form-control" id="funnel-description" name="description" rows="2" maxlength="2000"
                        placeholder="{{ translate('Optional: campaign goal, ad audience, anything the team should know') }}">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>

        <div class="text-right mb-4">
            <a href="{{ route('funnels.index') }}" class="btn btn-light mr-2">{{ translate('Cancel') }}</a>
            {{-- With the editor configured, saving opens the new funnel in the visual builder (FunnelController::store). --}}
            @if (config('funnel.editor_url') !== '' && Auth::user()->can('edit_funnel'))
                <button type="submit" class="btn btn-primary">{{ translate('Create and open Visual Builder') }}</button>
            @else
                <button type="submit" class="btn btn-primary">{{ translate('Create funnel') }}</button>
            @endif
        </div>
    </form>
@endsection

@section('script')
    <style>
        .funnel-card { display: flex; flex-direction: column; border: 2px solid #e2e5ec; border-radius: 8px; overflow: hidden; cursor: pointer; background: #fff; transition: border-color .15s, box-shadow .15s; }
        .funnel-card:hover { border-color: #b8c1d4; }
        .funnel-card.selected { border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.18); }
        .funnel-thumb { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; object-position: top; background: #f1f3f7; }
        .funnel-thumb-blank { display: flex; align-items: center; justify-content: center; font-size: 34px; color: #9aa3b5; }
        .funnel-card-body { padding: 10px 12px 12px; }
        .funnel-lang { display: none; }
        body[data-funnel-lang="bn"] .funnel-lang-bn, body[data-funnel-lang="en"] .funnel-lang-en { display: block; }
        .funnel-picker-chosen { gap: 8px; }
        .funnel-picker-chosen img { width: 44px; height: 44px; object-fit: cover; border-radius: 6px; }
    </style>
    <script type="text/javascript">
        (function () {
            var body = document.body;
            var lang = 'bn';
            var key = '';
            var form = document.getElementById('funnel-create-form');
            var templateId = document.getElementById('funnel-template-id');
            var cards = document.querySelectorAll('.funnel-card');
            var panels = document.querySelectorAll('.funnel-slots');
            var heading = document.getElementById('funnel-details-heading');

            function refresh() {
                // The checked button is the truth (Bootstrap's toggle script may not fire a change event).
                var checked = document.querySelector('input[name="funnel_language"]:checked');
                if (checked) lang = checked.value;
                body.setAttribute('data-funnel-lang', lang);
                var selected = null;
                cards.forEach(function (card) {
                    var on = card.getAttribute('data-template') === key;
                    card.classList.toggle('selected', on);
                    if (on) selected = card;
                });
                templateId.value = selected && key ? (selected.getAttribute('data-id-' + lang) || '') : '';
                var hasSlots = false;
                panels.forEach(function (panel) {
                    var show = key !== '' && panel.getAttribute('data-slots-for') === key && panel.getAttribute('data-slots-lang') === lang;
                    panel.classList.toggle('d-none', !show);
                    // Hidden pickers must not submit a value.
                    panel.querySelectorAll('.funnel-picker-value').forEach(function (input) {
                        input.name = show ? input.closest('.funnel-picker').getAttribute('data-field') : '';
                    });
                    hasSlots = hasSlots || show;
                });
                heading.textContent = hasSlots ? '3. ' + heading.getAttribute('data-base') : '2. ' + heading.getAttribute('data-base');
            }

            heading.setAttribute('data-base', heading.textContent.replace(/^\d+\.\s*/, ''));

            cards.forEach(function (card) {
                card.addEventListener('click', function (event) {
                    event.preventDefault();
                    key = card.getAttribute('data-template');
                    refresh();
                });
            });
            document.querySelectorAll('input[name="funnel_language"]').forEach(function (radio) {
                radio.addEventListener('change', refresh);
                radio.closest('label').addEventListener('click', function () { setTimeout(refresh, 0); });
            });
            // The language radios are not part of the submission.
            form.addEventListener('submit', function () {
                document.querySelectorAll('input[name="funnel_language"]').forEach(function (r) { r.disabled = true; });
            });

            // Product search, one small box per slot.
            var searchUrl = @json(route('funnels.products'));
            document.querySelectorAll('.funnel-picker').forEach(function (picker) {
                var search = picker.querySelector('.funnel-picker-search');
                var results = picker.querySelector('.funnel-picker-results');
                var chosen = picker.querySelector('.funnel-picker-chosen');
                var value = picker.querySelector('.funnel-picker-value');
                var timer = null;

                function choose(product) {
                    value.value = product.id;
                    chosen.innerHTML = '';
                    if (product.thumbnail_url) {
                        var img = document.createElement('img');
                        img.src = product.thumbnail_url;
                        img.alt = '';
                        chosen.appendChild(img);
                    }
                    var name = document.createElement('span');
                    name.className = 'fw-600';
                    name.textContent = product.name + ' (#' + product.id + ')';
                    chosen.appendChild(name);
                    var clear = document.createElement('button');
                    clear.type = 'button';
                    clear.className = 'btn btn-sm btn-link text-danger';
                    clear.textContent = @json(translate('Remove'));
                    clear.addEventListener('click', function () {
                        value.value = '';
                        chosen.classList.add('d-none');
                        chosen.classList.remove('d-flex');
                    });
                    chosen.appendChild(clear);
                    chosen.classList.remove('d-none');
                    chosen.classList.add('d-flex');
                    results.classList.add('d-none');
                    search.value = '';
                }

                function render(products) {
                    results.innerHTML = '';
                    if (!products.length) {
                        var none = document.createElement('div');
                        none.className = 'list-group-item text-muted fs-13';
                        none.textContent = @json(translate('No products found.'));
                        results.appendChild(none);
                    }
                    products.forEach(function (product) {
                        var row = document.createElement('button');
                        row.type = 'button';
                        row.className = 'list-group-item list-group-item-action d-flex align-items-center';
                        row.style.gap = '10px';
                        if (product.thumbnail_url) {
                            var img = document.createElement('img');
                            img.src = product.thumbnail_url;
                            img.alt = '';
                            img.style.cssText = 'width:36px;height:36px;object-fit:cover;border-radius:4px';
                            row.appendChild(img);
                        }
                        var text = document.createElement('span');
                        text.textContent = product.name;
                        row.appendChild(text);
                        row.addEventListener('click', function () { choose(product); });
                        results.appendChild(row);
                    });
                    results.classList.remove('d-none');
                }

                search.addEventListener('input', function () {
                    clearTimeout(timer);
                    var term = search.value.trim();
                    if (term.length < 2) { results.classList.add('d-none'); return; }
                    timer = setTimeout(function () {
                        fetch(searchUrl + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                            .then(function (r) { return r.ok ? r.json() : { data: [] }; })
                            .then(function (json) { render(json.data || []); })
                            .catch(function () { render([]); });
                    }, 250);
                });
                // Enter in the search box must not submit the whole form.
                search.addEventListener('keydown', function (event) { if (event.key === 'Enter') event.preventDefault(); });
            });

            // The address field stays empty unless the merchant types one; the
            // placeholder previews what an English title would become. An empty
            // field lets the server pick a free address (and transliterate Bengali).
            var title = document.getElementById('funnel-title');
            var slug = document.getElementById('funnel-slug');
            var fallback = slug.getAttribute('placeholder');
            slug.addEventListener('input', function () {
                slug.value = slug.value.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-{2,}/g, '-');
            });
            title.addEventListener('input', function () {
                var suggestion = title.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
                slug.setAttribute('placeholder', suggestion.length >= 3 ? suggestion : fallback);
            });

            // Restore the previous choice after a validation error.
            var previous = templateId.value;
            if (previous) {
                cards.forEach(function (card) {
                    ['bn', 'en'].forEach(function (l) {
                        if (card.getAttribute('data-id-' + l) === previous) {
                            key = card.getAttribute('data-template');
                            lang = l;
                            var radio = document.querySelector('input[name="funnel_language"][value="' + l + '"]');
                            if (radio) { radio.checked = true; radio.closest('label').classList.add('active'); }
                            var other = document.querySelector('input[name="funnel_language"][value="' + (l === 'bn' ? 'en' : 'bn') + '"]');
                            if (other) other.closest('label').classList.remove('active');
                        }
                    });
                });
            }
            refresh();
        })();
    </script>
@endsection
