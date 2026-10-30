@extends('backend.layouts.app')

@section('content')

<div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="h3">{{translate('All Featured Collection')}}</h1>
        </div>
        @can('add_product_category')
            <div class="col-md-6 text-md-right">
                <a href="{{ route('featured_collection.create') }}" class="btn btn-circle btn-info">
                    <span>{{translate('Add New Collection')}}</span>
                </a>
            </div>
        @endcan
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table aiz-table mb-0">
            <thead>
                <tr>
                    <th data-breakpoints="lg">SL</th>
                    <th>{{ translate('Featured Collection Name') }}</th>
                    <th>{{ translate('Link') }}</th>
                    <th>{{ translate('Slug') }}</th>
                    <th>{{ translate('Image') }}</th>
                    <th width="10%" class="text-right">{{translate('Options')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($featuredCollections as $key => $collection)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $collection->title ?? '' }}</td>
                        <td>{{ $collection->link ?? '' }}</td>
                        <td>{{ $collection->slug ?? '' }}</td>
                        <td>
                            @if($collection->image)
                                <img src="{{ uploaded_asset($collection->image) }}" alt="{{translate('Banner')}}" class="h-50px">
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right">
                            <a class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                               href="{{ route('featured_collection.edit', $collection->id) }}"
                               title="{{ translate('Edit') }}">
                                <i class="las la-edit"></i>
                            </a>
                            <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete"
                               data-href="{{ route('featured_collection.destroy', $collection->id) }}"
                               title="{{ translate('Delete') }}">
                                <i class="las la-trash"></i>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="aiz-pagination">
            {{ $featuredCollections->appends(request()->input())->links() }}
        </div>
    </div>
</div>
@endsection

@section('modal')
    @include('modals.delete_modal')
@endsection
