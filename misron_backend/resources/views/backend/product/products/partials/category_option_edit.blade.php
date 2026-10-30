@php
    $dash = str_repeat('-', $level);
@endphp

<option value="{{ $child_category->id }}" {{ $selected_id == $child_category->id ? 'selected' : '' }}>
    {{ $dash }} {{ $child_category->getTranslation('name') }}
</option>

@if ($child_category->childrenCategories->count() > 0)
    @foreach ($child_category->childrenCategories as $child)
        @include('backend.product.products.partials.category_option_edit', [
            'child_category' => $child,
            'level' => $level + 1,
            'selected_id' => $selected_id
        ])
    @endforeach
@endif