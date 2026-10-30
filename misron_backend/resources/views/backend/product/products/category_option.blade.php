@php
    $dash = str_repeat('-', $level);
@endphp

<option value="{{ $child_category->id }}" 
    {{ collect($selected_ids)->contains($child_category->id) ? 'selected' : '' }}>
    {{ $dash }} {{ $child_category->getTranslation('name') }}
</option>

@if ($child_category->childrenCategories->count() > 0)
    @foreach ($child_category->childrenCategories as $child)
        @include('backend.product.products.category_option', [
            'child_category' => $child,
            'level' => $level + 1,
            'selected_ids' => $selected_ids
        ])
    @endforeach
@endif