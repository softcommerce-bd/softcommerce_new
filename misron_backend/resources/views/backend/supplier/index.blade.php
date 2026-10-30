@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3">{{ translate('All Suppliers') }}</h1>
            <button type="button" class="btn btn-primary rounded-pill" data-toggle="modal" data-target="#addSupplierModal">Add Supplier</button>
        </div>
    </div>
</div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0 h6">{{translate('All Suppliers')}}</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" id="myTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>{{translate('Supplier Name')}}</th>
                                    <th>{{translate('contact_id')}}</th>
                                    <th>{{translate('Email')}}</th>
                                    <th>{{translate('Phone Number')}}</th>
                                    <th>{{translate('Address')}}</th>
                                    <th>{{translate('Tax Number')}}</th>
                                    <th>{{translate('Opening Balance')}}</th>
                                    <th>{{translate('Payment Term')}}</th>
                                    <th>{{ translate('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($suppliers as $key =>$supplier) 
                                <tr>
                                    <td>{{ $key+1 }}</td>
                                    <td>{{ $supplier->business_name }}</td>
                                    <td>{{ $supplier->contact_id }}</td>
                                    <td>{{ $supplier->email }}</td>
                                    <td>{{ $supplier->mobile }}</td>
                                    <td>{{ $supplier->address }}</td>
                                    <td>{{ $supplier->tax_number }}</td>
                                    <td>{{ $supplier->opening_balance }}</td>
                                    <td>{{ $supplier->pay_term }}</td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="actionMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="actionMenu" style="min-width: 0; width: auto;">
                                                <a class="dropdown-item d-flex align-items-center" href="{{ route('supplier.view',$supplier->id) }}">
                                                    <i class="fas fa-eye mr-2"></i>
                                                </a>
                                                 <a class="dropdown-item d-flex align-items-center" href="{{ route('supplier.edit', $supplier->id) }}">
                                                    <i class="fas fa-edit mr-2"></i>
                                                </a>
                                                {{--
                                                <a class="dropdown-item d-flex align-items-center" href="">
                                                    <i class="fas fa-trash mr-2"></i>
                                                </a>--}}
                                                <a 
                                                    class="dropdown-item d-flex align-items-center text-danger" 
                                                    href="#"
                                                    onclick="if (confirm('Are you sure you want to delete this supplier?')) { postDelete('{{ route('supplier.delete', $supplier->id) }}'); } return false;">
                                                    <i class="fas fa-trash mr-2 text-danger"></i>
                                                </a>

                                                
                                            </div>
                                        </div>
                                    </td>
                                    
                                </tr>
                                @endforeach
                                @if(!$suppliers)
                                <tr>
                                    <td colspan="17" class="text-center">{{ translate('No Supplier available') }}</td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
        
                </div>
            </div>
        </div>
    </div>

  
    
    <!-- Modal -->
    
    @include('backend.supplier.supplier_modal.modal')
    
@endsection

<!-- Scripts -->
@section('script')
<script>
    // State-changing actions are DELETE requests with the CSRF token, never plain links.
    function postDelete(url) {
        var f = document.createElement('form'); f.method = 'POST'; f.action = url;
        [['_token', '{{ csrf_token() }}'], ['_method', 'DELETE']].forEach(function (p) { var i = document.createElement('input'); i.type = 'hidden'; i.name = p[0]; i.value = p[1]; f.appendChild(i); });
        document.body.appendChild(f); f.submit();
    }
</script>

<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        $('#myTable').DataTable();
    });
    
    document.addEventListener("DOMContentLoaded", function () {
        document.getElementById("contact_id").value = "Gen-" + Math.floor(Math.random() * 100000);
    });
</script>
@endsection