@extends('backend.layouts.app')
@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3"><h1 class="h3">{{ $title }}</h1></div>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr>@foreach ($columns as $label)<th>{{ translate($label) }}</th>@endforeach</tr></thead>
    <tbody>@forelse ($rows as $row)<tr>@foreach ($columns as $key => $label)<td>{{ is_array($row) ? ($row[$key] ?? '') : ($row->{$key} ?? '') }}</td>@endforeach</tr>
    @empty<tr><td colspan="{{ count($columns) }}" class="text-center text-muted">{{ translate('No records') }}</td></tr>@endforelse</tbody>
</table></div>
@if (method_exists($rows, 'links'))<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
@endsection
