<!-- backend/accounts/modals/statement_modal.blade.php -->
<div class="modal fade" id="statementModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="las la-file-alt mr-2"></i> {{ translate('Account Statement') }} - <span id="statement_account_name"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label>{{ translate('Start Date') }}</label>
                        <input type="date" id="statement_start_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label>{{ translate('End Date') }}</label>
                        <input type="date" id="statement_end_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary form-control" onclick="refreshStatement()">{{ translate('Filter') }}</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="bg-light">
                            <tr>
                                <th>{{ translate('Date') }}</th>
                                <th>{{ translate('Reference No') }}</th>
                                <th>{{ translate('Description') }}</th>
                                <th>{{ translate('Debit') }}</th>
                                <th>{{ translate('Credit') }}</th>
                                <th>{{ translate('Balance') }}</th>
                            </tr>
                        </thead>
                        <tbody id="statement_table_body">
                            <tr><td colspan="6" class="text-center">{{ translate('Loading...') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                <button type="button" class="btn btn-primary" onclick="printStatement()">{{ translate('Print') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentAccountId = null;

function refreshStatement() {
    if (!currentAccountId) return;
    
    var startDate = $('#statement_start_date').val();
    var endDate = $('#statement_end_date').val();
    
    $.ajax({
        url: '{{ route("accounts.statement", "") }}/' + currentAccountId,
        type: 'GET',
        data: { start_date: startDate, end_date: endDate },
        success: function(response) {
            if (response.success) {
                var tbody = $('#statement_table_body');
                tbody.empty();
                
                if (response.statements.length === 0) {
                    tbody.append('<tr><td colspan="6" class="text-center">{{ translate("No transactions found") }}</td></tr>');
                } else {
                    $.each(response.statements, function(index, statement) {
                        tbody.append(`
                            <tr>
                                <td>${statement.transaction_date}</td>
                                <td>${statement.reference_no}</td>
                                <td>${statement.description || '-'}</td>
                                <td class="text-success">${parseFloat(statement.debit).toFixed(2)}</td>
                                <td class="text-danger">${parseFloat(statement.credit).toFixed(2)}</td>
                                <td class="font-weight-bold">${parseFloat(statement.balance).toFixed(2)}</td>
                            </tr>
                        `);
                    });
                }
            }
        },
        error: function() {
            $('#statement_table_body').html('<tr><td colspan="6" class="text-center text-danger">{{ translate("Error loading statement") }}</td></tr>');
        }
    });
}

function printStatement() {
    var printContent = $('#statement_table_body').closest('.table-responsive').html();
    var accountName = $('#statement_account_name').text();
    var startDate = $('#statement_start_date').val();
    var endDate = $('#statement_end_date').val();
    
    var printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>{{ translate('Account Statement') }}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h2 { text-align: center; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .text-success { color: green; }
                .text-danger { color: red; }
                .text-center { text-align: center; }
            </style>
        </head>
        <body>
            <h2>{{ translate('Account Statement') }}</h2>
            <p><strong>{{ translate('Account') }}:</strong> ${accountName}</p>
            <p><strong>{{ translate('Period') }}:</strong> ${startDate} - ${endDate}</p>
            ${printContent}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>