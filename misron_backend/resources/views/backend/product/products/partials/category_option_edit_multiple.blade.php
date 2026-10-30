@php
    $dash = str_repeat('-', $level);
@endphp

<option value="{{ $child_category->id }}" {{ in_array($child_category->id, $selected_ids) ? 'selected' : '' }}>
    {{ $dash }} {{ $child_category->getTranslation('name') }}
</option>

@if ($child_category->childrenCategories->count() > 0)
    @foreach ($child_category->childrenCategories as $child)
        @include('backend.product.products.partials.category_option_edit_multiple', [
            'child_category' => $child,
            'level' => $level + 1,
            'selected_ids' => $selected_ids
        ])
    @endforeach
@endif