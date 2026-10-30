@extends('backend.layouts.app')

@section('content')

<div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="h3 text-dark">{{translate('Draft Orders')}}</h1>
            <p class="text-muted mt-1">{{translate('Manage all incomplete/draft orders')}}</p>
        </div>
        <div class="col-md-6 text-md-right">
           
        </div>
    </div>
</div>

<div class="card">
    <form class="" id="sort_drafts" action="" method="GET">
        <div class="card-header">
            <div class="row w-100 align-items-center">
                <div class="col">
                    <h5 class="mb-0 h6">{{ translate('Draft Order List') }}</h5>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control form-control-sm" id="search" name="search" @isset($sort_search) value="{{ $sort_search }}" @endisset placeholder="{{ translate('Search by name, phone...') }}">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="las la-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table aiz-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="5%">
                                <div class="form-group mb-0">
                                    <div class="aiz-checkbox-inline">
                                        <label class="aiz-checkbox">
                                            <input type="checkbox" class="check-all">
                                            <span class="aiz-square-check"></span>
                                        </label>
                                    </div>
                                </div>
                            </th>
                            <th data-breakpoints="lg" width="20%">{{translate('Customer Info')}}</th>
                            <th data-breakpoints="lg" width="25%">{{translate('Address')}}</th>
                            <th width="10%">{{translate('Total Products')}}</th>
                            <th width="15%">{{translate('Grand Total')}}</th>
                            <th data-breakpoints="lg" width="10%">{{translate('Date')}}</th>
                            <th width="15%" class="text-right">{{translate('Actions')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($drafts as $key => $draft)
                        <tr>
                            <td>
                                <div class="form-group d-inline-block mb-0">
                                    <label class="aiz-checkbox">
                                        <input type="checkbox" class="check-one" name="id[]" value="{{$draft->id}}">
                                        <span class="aiz-square-check"></span>
                                    </label>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="font-weight-bold">{{ $draft->name }}</div>
                                        <small class="text-muted"><i class="las la-phone-alt"></i> {{ $draft->phone }}</small><br>
                                        @if($draft->email)
                                        <small class="text-muted"><i class="las la-envelope"></i> {{ $draft->email }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <i class="las la-map-marker-alt text-muted"></i>
                                    <span class="text-muted text-truncate-2">{{ $draft->address ?? translate('Not provided') }}</span>
                                </div>
                            </td>
                            <td>
                                @php
                                    $productsData = json_decode($draft->products_id, true);
                                    $totalProducts = 0;
                                    $grandTotal = '৳0';
                                    
                                    if (isset($productsData['data']) && is_array($productsData['data'])) {
                                        foreach ($productsData['data'] as $seller) {
                                            if (isset($seller['cart_items']) && is_array($seller['cart_items'])) {
                                                $totalProducts += count($seller['cart_items']);
                                            }
                                        }
                                        $grandTotal = $productsData['grand_total'] ?? '৳0';
                                    }
                                @endphp
                                <div class="text-center">
                                    <span class="badge badge-inline badge-info">{{ $totalProducts }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="font-weight-bold text-primary">{{ $grandTotal }}</span>
                            </td>
                            <td>
                                <small class="text-muted">{{ date('d M Y', strtotime($draft->created_at)) }}</small><br>
                                <small class="text-muted">{{ date('h:i A', strtotime($draft->created_at)) }}</small>
                            </td>
                            <td class="text-right">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-circle btn-soft-primary btn-icon" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="las la-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a class="dropdown-item" href="{{route('draft.show', $draft->id)}}">
                                            <i class="las la-eye"></i> {{ translate('View Details') }}
                                        </a>
                                        <a class="dropdown-item " href="#" onclick="if (confirm('Delete this draft?')) { postDelete('{{ route('draft.delete', $draft->id) }}'); } return false;">
                                            <i class="las la-trash"></i> {{ translate('Delete') }}
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="empty-state">
                                        <img src="{{ static_asset('assets/img/empty.svg') }}" alt="No drafts" class="img-fluid mb-3" style="max-width: 200px;">
                                        <h5 class="text-muted">{{ translate('No draft orders found') }}</h5>
                                        <p class="text-muted">{{ translate('Start creating orders from the customer panel') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="aiz-pagination mt-4">
                {{ $drafts->appends(request()->input())->links() }}
            </div>
        </div>
    </form>
</div>

@endsection

@section('modal')
    @include('modals.delete_modal')
    
    <!-- Bulk Delete Modal -->
    <div class="modal fade" id="bulk-delete-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Bulk Delete') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>{{ translate('Are you sure you want to delete selected drafts?') }}</p>
                    <p class="text-danger">{{ translate('This action cannot be undone!') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                   
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
    // State-changing actions are DELETE requests with the CSRF token, never plain links.
    function postDelete(url) {
        var f = document.createElement('form'); f.method = 'POST'; f.action = url;
        [['_token', '{{ csrf_token() }}'], ['_method', 'DELETE']].forEach(function (p) { var i = document.createElement('input'); i.type = 'hidden'; i.name = p[0]; i.value = p[1]; f.appendChild(i); });
        document.body.appendChild(f); f.submit();
    }
</script>

<script type="text/javascript">
    $(document).on("change", ".check-all", function() {
        if(this.checked) {
            $('.check-one:checkbox').each(function() {
                this.checked = true;
            });
        } else {
            $('.check-one:checkbox').each(function() {
                this.checked = false;
            });
        }
    });


    function sort_drafts(el) {
        $('#sort_drafts').submit();
    }

    // Auto-submit on search input change
    $('#search').on('change', function() {
        $('#sort_drafts').submit();
    });
</script>
@endsection