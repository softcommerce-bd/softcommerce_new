@extends('backend.layouts.app')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-between">
            <h3 class="mb-0 h6">{{translate('Purchase Products')}}</h3>
             <a href="{{ route('purchase.create') }}" class="btn btn-primary rounded-pill">Add Purchase</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered mb-0" id="myTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>{{translate('Supplier Name')}}</th>
                            <th>{{translate('Reference No')}}</th>
                            <th>{{translate('Purchase Date')}}</th>
                            <th>{{translate('Business Location')}}</th>
                            <th>{{translate('Payment Term')}}</th>
                            <th>{{translate('Payment Term Type')}}</th>
                            <th>{{translate('Total Item')}}</th>
                            <th>{{translate('Total Cost')}}</th>
                            {{-- <th>{{translate('Advanced Paid Amount')}}</th> --}}
                            {{-- <th>{{translate('Advanced Paid Date')}}</th> --}}
                            {{-- <th>{{translate('Payment Method')}}</th> --}}
                            <th>{{translate('Payment Note')}}</th>
                            <th>{{translate('Total Payment Due')}}</th>
                            <th>{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchase as $product) 
                        <tr>
                            <td>{{ $product->id ?? ''}}</td>
                            <td>{{ $product->supplier->business_name ?? '' }}</td>
                            <td>{{ $product->reference_no ?? ''}}</td>
                            <td>{{ $product->purchase_date ?? ''}}</td>
                            <td>{{ $product->business_location ?? ''}}</td>
                            <td>{{ $product->pay_term ?? ''}}</td>
                            <td>{{ $product->pay_term_type ?? ''}}</td>
                            <td>{{ $product->total_item ?? ''}}</td>
                            <td>{{ $product->net_total_amount ?? ''}}</td>
                            {{-- <td>{{ $product->advanced_amount ?? ''}}</td> --}}
                            {{-- <td>{{ $product->advanced_paid_on ?? ''}}</td> --}}
                            {{-- <td>{{ $product->payment_method ?? ''}}</td> --}}
                            <td>{{ $product->payment_note ?? ''}}</td>
                            <td>{{ $product->total_payment_due ?? ''}}</td>
                           
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="actionMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="actionMenu" style="min-width: 0; width: auto;">
                                        <a class="dropdown-item d-flex align-items-center" href="{{ route('purchase.view',$product->id) }}">
                                            <i class="fas fa-eye mr-2"></i>
                                        </a>
                                           <a class="dropdown-item d-flex align-items-center" href="{{ route('purchase.edit',$product->id) }}">
                                            <i class="fas fa-edit mr-2"></i>
                                        </a>
                                        {{-- <a class="dropdown-item d-flex align-items-center" href="javascript:void(0);" 
                                         onclick="confirmDelete('{{ route('purchase.delete', $product->id) }}')">
                                         <i class="fas fa-trash mr-2"></i>
                                        </a>--}}
                                        
                                    </div>
                                </div>
                            </td>
                            
                        </tr>
                        @endforeach
                        @if(!$purchase)
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        $('#myTable').DataTable({
            order: [[0, 'desc']]
        });
    });
</script>
<script>
    function confirmDelete(deleteUrl) {
        Swal.fire({
            title: "Are you sure?",
            text: "You won't be able to revert this!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, delete it!"
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = deleteUrl;
            }
        });
    }
    </script>
@endsection

