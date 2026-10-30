@extends('backend.layouts.app')
@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3"><h1 class="h3">{{ $title }}</h1></div>
@isset($note)<div class="alert alert-info small" id="report-note">{{ $note }}</div>@endisset
@isset($filters)<form method="GET" class="form-inline mb-3" id="report-filters">@foreach ($filters as $name => [$label, $value])<label class="mr-2 small" for="f-{{ $name }}">{{ translate($label) }}</label><input id="f-{{ $name }}" name="{{ $name }}" value="{{ $value }}" class="form-control form-control-sm mr-3 mb-2">@endforeach<button class="btn btn-sm btn-primary mb-2">{{ translate('Show') }}</button></form>@endisset
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr>@foreach ($columns as $label)<th>{{ translate($label) }}</th>@endforeach</tr></thead>
    <tbody>@forelse ($rows as $row)<tr>@foreach ($columns as $key => $label)<td>{{ is_array($row) ? ($row[$key] ?? '') : ($row->{$key} ?? '') }}</td>@endforeach</tr>
    @empty<tr><td colspan="{{ count($columns) }}" class="text-center text-muted">{{ translate('No records') }}</td></tr>@endforelse</tbody>
</table></div>
@if (method_exists($rows, 'links'))<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
@endsection
