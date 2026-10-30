@extends('backend.layouts.app')

@section('content')

<style>
    .fs-table::before, .fs-table::after { content: none; display: none; }
    .fs-table td, .fs-table th { vertical-align: middle; padding: .6rem .5rem; }
    .fs-actions form { display: inline; margin: 0; }
</style>

<div class="aiz-titlebar text-left mt-2 mb-3">
    <h1 class="h3">{{ translate('Saved Sections') }}</h1>
    <p class="text-muted mb-0 fs-13">{{ translate('Parts of a page saved for reuse: a hero, a reviews block, an FAQ. Add them to any funnel from the editor’s left panel, under “Saved”.') }}</p>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between" style="gap: 8px;">
        <h5 class="mb-0 h6">{{ translate('All saved sections') }} <span class="badge badge-inline badge-soft-secondary ml-1">{{ $sections->count() }}</span></h5>
        <form method="GET" action="{{ route('funnels.sections') }}" class="d-flex" style="gap: 6px;">
            <input type="search" class="form-control form-control-sm" name="search" value="{{ $search }}" placeholder="{{ translate('Search by name or block type') }}" aria-label="{{ translate('Search') }}">
            <button type="submit" class="btn btn-sm btn-light">{{ translate('Search') }}</button>
        </form>
    </div>
    <div class="card-body">
        @if ($sections->isEmpty())
            <div class="text-center py-3">
                <p class="fw-600 mb-1">{{ $search !== '' ? translate('No saved section matches this search.') : translate('No saved sections yet') }}</p>
                @if ($search === '')
                    <p class="text-muted fs-13 mb-0">{{ translate('In the editor, select a block and choose “Save section”. It then appears here and in every funnel’s editor.') }}</p>
                @endif
            </div>
        @else
            <table class="table aiz-table fs-table mb-0">
                <thead>
                    <tr>
                        <th>{{ translate('Name') }}</th>
                        <th data-breakpoints="md">{{ translate('Block') }}</th>
                        <th data-breakpoints="lg">{{ translate('Last changed') }}</th>
                        <th class="text-right">{{ translate('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sections as $section)
                        <tr>
                            <td class="fw-600">{{ $section->name }}</td>
                            <td><span class="badge badge-inline badge-light">{{ ucfirst(str_replace('_', ' ', $section->category)) }}</span></td>
                            <td>
                                {{ $section->updated_at->format('d M Y') }}
                                @if (isset($authors[$section->created_by]))
                                    <div class="fs-12 text-muted">{{ translate('by') }} {{ $authors[$section->created_by] }}</div>
                                @endif
                            </td>
                            <td class="text-right fs-actions text-nowrap">
                                @can('edit_funnel')
                                    <button type="button" class="btn btn-light btn-sm" data-rename="{{ $section->id }}" data-name="{{ $section->name }}">{{ translate('Rename') }}</button>
                                    <form method="POST" action="{{ route('funnels.sections.destroy', $section->id) }}" data-confirm="{{ translate('Delete this saved section? Pages that already use it keep their copy.') }}">
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

<div class="modal fade" id="fs-rename" tabindex="-1" role="dialog" aria-labelledby="fs-rename-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form class="modal-content" method="POST" id="fs-rename-form">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title h6" id="fs-rename-title">{{ translate('Rename section') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
            </div>
            <div class="modal-body">
                <label class="fw-600" for="fs-rename-name">{{ translate('Name') }}</label>
                <input type="text" class="form-control" id="fs-rename-name" name="name" maxlength="150" required>
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
            var form = event.target.closest('.fs-actions form[data-confirm]');
            if (form && !window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
        });
        var renameUrl = @json(route('funnels.sections.update', '__ID__'));
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-rename]');
            if (!button) return;
            document.getElementById('fs-rename-form').action = renameUrl.replace('__ID__', button.getAttribute('data-rename'));
            document.getElementById('fs-rename-name').value = button.getAttribute('data-name') || '';
            $('#fs-rename').modal('show');
        });
    </script>
@endsection
