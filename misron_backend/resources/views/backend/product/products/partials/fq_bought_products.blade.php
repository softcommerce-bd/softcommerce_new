@if(isset($product_ids) && is_array(json_decode($product_ids, true)))
    @foreach(json_decode($product_ids) as $key => $id)
        @php
            $product = \App\Models\Product::find($id);
        @endphp
        @if($product)
            <div class="d-flex justify-content-between align-items-center mt-2 border p-2 rounded">
                <div class="d-flex align-items-center">
                    <img class="size-50px mr-3" src="{{ uploaded_asset($product->thumbnail_img) }}" alt="{{ $product->getTranslation('name') }}">
                    <div>
                        <h6 class="mb-0">{{ $product->getTranslation('name') }}</h6>
                        <small class="text-muted">{{ $product->unit_price }}</small>
                    </div>
                </div>
                <input type="hidden" name="fq_bought_product_ids[]" value="{{ $id }}">
                <button class="btn btn-sm btn-icon" onclick="$(this).parent().remove()">
                    <i class="las la-times"></i>
                </button>
            </div>
        @endif
    @endforeach
@endif