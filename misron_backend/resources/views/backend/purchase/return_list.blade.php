@extends('backend.layouts.app')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="mb-0 h6">{{translate('Purchase Return Products')}}</h3>
            <a class="btn btn-primary" href="{{ route('purchase_return.add') }}"><i class="las la-plus text-white"></i></a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered mb-0" id="myTable">
                    <thead>
                        <tr>
                            <th>{{translate('Date')}}</th>
                            <th>{{translate('Supplier')}}</th>
                            <th>{{translate('Reference No')}}</th>
                            <th>{{translate('Transaction Date')}}</th>
                            <th>{{ translate('Action') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($purchaseReturns as $product) 
                        <tr>
                            <td>{{ $product->created_at }}</td>
                            <td>{{ $product->supplier->business_name }}</td>
                            <td>{{ $product->reference_no }}</td>
                            <td>{{ $product->transaction_date }}</td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="actionMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="actionMenu" style="min-width: 0; width: auto;">
                                        <a class="dropdown-item d-flex align-items-center" href="{{ route('purchase_return.edit',$product->id) }}">
                                            <i class="fas fa-eye mr-2"></i>
                                        </a>
                                        {{-- <a class="dropdown-item d-flex align-items-center" href="">
                                            <i class="fas fa-trash mr-2"></i>
                                        </a> --}}
                                        
                                    </div>
                                </div>
                            </td>
                            
                        </tr>
                        @endforeach
                        @if(!$purchaseReturns)
                        <tr>
                            <td colspan="17" class="text-center">{{ translate('No data available') }}</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>

        </div>
    </div>

@endsection

<!-- Scripts -->
@section('script')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        $('#myTable').DataTable({
            order: [[0, 'desc']]
        });
    });
</script>
@endsection