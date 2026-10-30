@php
    $currentSort = request('sort', 'settlement_date');
    $currentDirection = request('direction', 'desc');
    $icon = '';

    if ($currentSort === $field) {
        $icon = $currentDirection === 'asc' ? ' <i class="las la-sort-up"></i>' : ' <i class="las la-sort-down"></i>';
        $newDirection = $currentDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $newDirection = 'asc';
    }

    // Merge all current query parameters
    $params = array_merge(request()->except('page'), ['sort' => $field, 'direction' => $newDirection]);
    $url = request()->fullUrlWithQuery($params);
@endphp
<a href="{{ $url }}" class="text-white text-decoration-none">
    {!! $label !!}{!! $icon !!}
</a>