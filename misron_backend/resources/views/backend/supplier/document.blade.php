@php
    $purchaseNew = \App\Models\Purchase::where('supplier_id', $supplier->id)->get();
@endphp
<div class="document_note_body">
    <div class="d-flex justify-content-end mb-3 mt-3">
        <button class="btn btn-outline-secondary btn-sm print_tab_pdf">
            <i class="fas fa-print"></i> Print
        </button>
        <button class="btn btn-outline-secondary btn-sm download_tab_pdf ms-2">
            <i class="fas fa-download"></i> Download PDF
        </button>
    </div>


    <div class="print-area">
        <div class="table-responsive">
            <div class="d-flex justify-content-end mb-3">
                {{-- <button type="button" class="btn btn-primary btn-sm docs_and_notes_btn"
                    data-href="https://pos.ultimatefosters.com/note-documents/create?notable_id=3&amp;notable_type=App%5CContact">
                    Add&nbsp;
                    <i class="fa fa-plus"></i>
                </button> --}}
            </div>
            <table class="table table-bordered mb-0" id="myTable2">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ translate('Supplier Name') }}</th>
                        <th>{{ translate('Reference No') }}</th>
                        <th>{{ translate('Purchase Date') }}</th>
                        <th>{{ translate('Payment Note') }}</th>
                        <th>{{ translate('Document') }}</th>

                    </tr>
                </thead>
                <tbody>
                    {{-- @dd($purchaseNew); --}}
                    @foreach ($purchaseNew as $product)
                        <tr>
                            <td>{{ $product->id }}</td>
                            <td>{{ $product->supplier->business_name }}</td>
                            <td>{{ $product->reference_no }}</td>
                            <td>{{ $product->purchase_date }}</td>
                            <td>{{ $product->payment_note ?? 'N/A' }}</td>
                            <td><a href="{{ asset('storage/app/public/' . $product->document) }}"
                                    target="_blank">{{ $product->document ?? 'N/A' }}</a></td>

                        </tr>
                    @endforeach
                    @if (!$purchaseNew)
                        <tr>
                            <td colspan="17" class="text-center">{{ translate('No data available') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- @section('script')
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
        <script>
            $(document).ready(function() {
                $('#myTable2').DataTable();
            });
        </script>
    @endsection --}}
