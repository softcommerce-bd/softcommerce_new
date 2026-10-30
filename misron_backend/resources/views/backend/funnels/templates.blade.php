@extends('backend.layouts.app')

@section('content')

<style>
    .ft-table::before, .ft-table::after { content: none; display: none; }
    .ft-table td, .ft-table th { vertical-align: middle; padding: .6rem .5rem; }
    .ft-thumb { width: 64px; height: 48px; border-radius: 6px; background: #f2f3f8; overflow: hidden; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #9aa3b5; font-size: 22px; }
    .ft-thumb img { width: 100%; height: 100%; object-fit: cover; object-position: top; }
    .ft-card { border: 1px solid #e2e5ec; border-radius: 8px; overflow: hidden; background: #fff; height: 100%; display: flex; flex-direction: column; }
    .ft-card-img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; object-position: top; background: #f1f3f7; }
    .ft-card-body { padding: 10px 12px 12px; display: flex; flex-direction: column; flex: 1; }
    .ft-card-body .ft-use { margin-top: auto; padding-top: 8px; display: flex; gap: 6px; flex-wrap: wrap; }
    .ft-actions form { display: inline; margin: 0; }
    .min-w-0 { min-width: 0; }
    .ft-name { max-width: min(320px, 60vw); }
    .ft-actions { white-space: normal !important; }
    .ft-actions > * { margin: 2px 0; }
</style>

<div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="row align-items-center">
        <div class="col-md">
            <h1 class="h3">{{ translate('Funnel Templates') }}</h1>
            <p class="text-muted mb-0 fs-13">{{ translate('Start a new funnel from a ready design or from a page you made before. Templates never contain products: you choose them each time.') }}</p>
        </div>
        @can('add_funnel')
            <div class="col-md-auto mt-2 mt-md-0">
                <a href="{{ route('funnels.create') }}" class="btn btn-primary">{{ translate('Create New Funnel') }}</a>
            </div>
        @endcan
    </div>
</div>

{{-- The merchant's own templates --}}
<div class="card">
    <div class="card-header">
        <h5 class="mb-0 h6">{{ translate('Your templates') }}</h5>
    </div>
    <div class="card-body">
        @if ($custom->isEmpty())
            <div class="text-center py-3">
                <p class="fw-600 mb-1">{{ translate('No templates of your own yet') }}</p>
                <p class="text-muted fs-13 mb-0">
                    {{ translate('Built a page you want to reuse? In All Funnels, open its ⋯ menu and choose “Save as template”. Its layout, wording and offers are kept; products are chosen again for each new campaign.') }}
                </p>
            </div>
        @else
            <table class="table aiz-table ft-table mb-0">
                <thead>
                    <tr>
                        <th>{{ translate('Template') }}</th>
                        <th data-breakpoints="sm">{{ translate('Products to choose') }}</th>
                        <th data-breakpoints="lg">{{ translate('Language') }}</th>
                        <th data-breakpoints="lg">{{ translate('Last changed') }}</th>
                        <th data-breakpoints="sm" class="text-right">{{ translate('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($custom as $template)
                        @php $slots = count(($template->product_slots ?: [])['slots'] ?? []); @endphp
                        <tr data-template-row="{{ $template->id }}">
                            <td>
                                <div class="d-flex align-items-center" style="gap: 12px;">
                                    <div class="ft-thumb">
                                        @if ($template->thumbnail_upload_id)
                                            <img src="{{ uploaded_asset($template->thumbnail_upload_id) }}" alt="" loading="lazy">
                                        @else
                                            <i class="las la-layer-group"></i>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-700 text-truncate ft-name">{{ $template->name }}</div>
                                        @if ($template->description)
                                            <div class="fs-12 text-muted text-truncate ft-name">{{ $template->description }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $slots > 0 ? $slots : translate('None (choose in the editor)') }}</td>
                            <td>{{ $template->language === 'en' ? 'English' : 'বাংলা' }}</td>
                            <td>
                                {{ $template->updated_at->format('d M Y') }}
                                @if (isset($authors[$template->created_by]))
                                    <div class="fs-12 text-muted">{{ translate('by') }} {{ $authors[$template->created_by] }}</div>
                                @endif
                            </td>
                            <td class="text-right ft-actions">
                                @can('add_funnel')
                                    <a href="{{ route('funnels.create', ['template' => $template->id]) }}" class="btn btn-soft-primary btn-sm">{{ translate('Use') }}</a>
                                    <button type="button" class="btn btn-light btn-sm" data-rename="{{ $template->id }}"
                                        data-name="{{ $template->name }}" data-description="{{ $template->description }}">{{ translate('Rename') }}</button>
                                    <form method="POST" action="{{ route('funnels.templates.duplicate', $template->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-light btn-sm">{{ translate('Duplicate') }}</button>
                                    </form>
                                @endcan
                                @can('delete_funnel')
                                    <form method="POST" action="{{ route('funnels.templates.destroy', $template->id) }}" data-confirm="{{ translate('Delete this template? Funnels made from it are not changed.') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-soft-danger btn-sm">{{ translate('Delete') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

{{-- Built-in designs --}}
<div class="card">
    <div class="card-header">
        <h5 class="mb-0 h6">{{ translate('Built-in designs') }}</h5>
    </div>
    <div class="card-body">
        @if ($system->isEmpty())
            <p class="text-muted mb-0">{{ translate('No templates are installed yet. Run') }} <code>php artisan funnel:templates</code>.</p>
        @else
            <div class="row gutters-10">
                @foreach ($system as $entry)
                    @php $first = reset($entry['languages']); @endphp
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <div class="ft-card">
                            @if ($entry['thumbnail'])
                                <img class="ft-card-img" src="{{ $entry['thumbnail'] }}" alt="" loading="lazy">
                            @endif
                            <div class="ft-card-body">
                                <strong class="d-block">{{ ($entry['languages']['en'] ?? $first)['name'] }}</strong>
                                <small class="text-muted d-block mb-1">{{ ($entry['languages']['en'] ?? $first)['description'] }}</small>
                                <small class="text-muted d-block">
                                    {{ count($first['slots']) }} {{ count($first['slots']) === 1 ? translate('product to choose') : translate('products to choose') }}
                                </small>
                                @can('add_funnel')
                                    <div class="ft-use">
                                        @foreach ($entry['languages'] as $lang => $info)
                                            <a href="{{ route('funnels.create', ['template' => $info['id']]) }}" class="btn btn-soft-primary btn-xs">
                                                {{ translate('Use') }} · {{ $lang === 'en' ? 'English' : 'বাংলা' }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endcan
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- Rename --}}
<div class="modal fade" id="ft-rename" tabindex="-1" role="dialog" aria-labelledby="ft-rename-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form class="modal-content" method="POST" id="ft-rename-form">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title h6" id="ft-rename-title">{{ translate('Rename template') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="fw-600" for="ft-rename-name">{{ translate('Name') }}</label>
                    <input type="text" class="form-control" id="ft-rename-name" name="name" maxlength="150" required>
                </div>
                <div class="form-group mb-0">
                    <label class="fw-600" for="ft-rename-description">{{ translate('Description') }}</label>
                    <textarea class="form-control" id="ft-rename-description" name="description" rows="2" maxlength="500"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">{{ translate('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('script')
    <script type="text/javascript">
        // Listeners on the document: the theme's table plugin rebuilds rows after load.
        document.addEventListener('submit', function (event) {
            var form = event.target.closest('.ft-actions form[data-confirm]');
            if (form && !window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
        });
        var renameUrl = @json(route('funnels.templates.update', '__ID__'));
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-rename]');
            if (!button) return;
            document.getElementById('ft-rename-form').action = renameUrl.replace('__ID__', button.getAttribute('data-rename'));
            document.getElementById('ft-rename-name').value = button.getAttribute('data-name') || '';
            document.getElementById('ft-rename-description').value = button.getAttribute('data-description') || '';
            $('#ft-rename').modal('show');
        });
    </script>
@endsection
