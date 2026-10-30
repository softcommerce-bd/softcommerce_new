<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ translate('Voucher') }} - {{ $voucher->voucher_no }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', 'Segoe UI', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
            background: #fff;
        }
        
        .voucher-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e0e0e0;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        
        /* Header Styles */
        .voucher-header {
            padding: 20px;
            border-bottom: 2px solid #4e73df;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .company-info {
            font-size: 11px;
            opacity: 0.9;
        }
        
        .voucher-title {
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            padding: 15px;
            background: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            color: #4e73df;
        }
        
        /* Info Section */
        .info-section {
            padding: 20px;
            background: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .info-label {
            width: 120px;
            font-weight: bold;
            color: #5a5c69;
        }
        
        .info-value {
            flex: 1;
            color: #333;
        }
        
        /* Table Styles */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .items-table th {
            background: #4e73df;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
        }
        
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e3e6f0;
            vertical-align: top;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Amount Summary */
        .amount-summary {
            padding: 20px;
            background: #f8f9fc;
            margin-top: 20px;
            border-top: 2px solid #4e73df;
        }
        
        .summary-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 8px;
        }
        
        .summary-label {
            width: 150px;
            font-weight: bold;
            text-align: right;
            padding-right: 20px;
        }
        
        .summary-value {
            width: 150px;
            text-align: right;
            font-weight: bold;
        }
        
        .total-row {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #333;
            font-size: 16px;
            color: #4e73df;
        }
        
        /* Footer */
        .voucher-footer {
            padding: 20px;
            border-top: 1px solid #e3e6f0;
            font-size: 11px;
            color: #858796;
            text-align: center;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding: 20px;
        }
        
        .signature-box {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            margin-top: 40px;
            padding-top: 10px;
            border-top: 1px solid #333;
        }
        
        /* Print & PDF Buttons */
        .action-buttons {
            text-align: center;
            padding: 20px;
            background: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            margin: 0 5px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background: #4e73df;
            color: white;
        }
        
        .btn-secondary {
            background: #858796;
            color: white;
        }
        
        .btn-success {
            background: #1cc88a;
            color: white;
        }
        
        .btn-danger {
            background: #e74a3b;
            color: white;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .voucher-container {
                box-shadow: none;
                border: none;
            }
            .btn {
                display: none;
            }
        }
        
        /* Badge */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 600;
            border-radius: 3px;
        }
        
        .badge-success {
            background: #1cc88a;
            color: white;
        }
        
        .badge-warning {
            background: #f6c23e;
            color: #333;
        }
        
        /* Word Wrap */
        .description-text {
            word-wrap: break-word;
            max-width: 300px;
        }
    </style>
</head>
<body>
    <div class="voucher-container">
        <!-- Action Buttons (visible only on screen) -->
        <div class="action-buttons no-print">
            <button onclick="window.print();" class="btn btn-primary">
                <i class="las la-print"></i> {{ translate('Print Voucher') }}
            </button>
            <button onclick="generatePDF();" class="btn btn-success">
                <i class="las la-file-pdf"></i> {{ translate('Download PDF') }}
            </button>
            <button onclick="window.close();" class="btn btn-secondary">
                <i class="las la-times"></i> {{ translate('Close') }}
            </button>
        </div>
        
        <!-- Voucher Header -->
        <div class="voucher-header">
            <div class="company-name">{{ get_setting('site_name', 'Your Company Name') }}</div>
            <div class="company-info">
                {{ get_setting('site_address', 'Company Address') }}<br>
                {{ translate('Phone') }}: {{ get_setting('site_phone', 'N/A') }} | 
                {{ translate('Email') }}: {{ get_setting('site_email', 'N/A') }}<br>
                {{ translate('VAT Reg') }}: {{ get_setting('vat_number', 'N/A') }}
            </div>
        </div>
        
        <!-- Voucher Title -->
        <div class="voucher-title">
            @if($voucher->type == 'CP') {{ translate('CASH PAYMENT VOUCHER') }}
            @elseif($voucher->type == 'BP') {{ translate('BANK PAYMENT VOUCHER') }}
            @elseif($voucher->type == 'CR') {{ translate('CASH RECEIPT VOUCHER') }}
            @elseif($voucher->type == 'BR') {{ translate('BANK RECEIPT VOUCHER') }}
            @elseif($voucher->type == 'JV') {{ translate('JOURNAL VOUCHER') }}
            @elseif($voucher->type == 'CV') {{ translate('CONTRA VOUCHER') }}
            @endif
        </div>
        
        <!-- Information Section -->
        <div class="info-section">
            <div class="info-row">
                <div class="info-label">{{ translate('Voucher No') }}:</div>
                <div class="info-value"><strong>{{ $voucher->voucher_no }}</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">{{ translate('Date') }}:</div>
                <div class="info-value">{{ $voucher->date->format('d-m-Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">{{ translate('Voucher Type') }}:</div>
                <div class="info-value">
                    <span class="badge badge-success">
                        @if($voucher->type == 'CP') {{ translate('Cash Payment') }}
                        @elseif($voucher->type == 'BP') {{ translate('Bank Payment') }}
                        @elseif($voucher->type == 'CR') {{ translate('Cash Receipt') }}
                        @elseif($voucher->type == 'BR') {{ translate('Bank Receipt') }}
                        @elseif($voucher->type == 'JV') {{ translate('Journal Voucher') }}
                        @elseif($voucher->type == 'CV') {{ translate('Contra Voucher') }}
                        @endif
                    </span>
                </div>
            </div>
            @if($voucher->description)
            <div class="info-row">
                <div class="info-label">{{ translate('Narration') }}:</div>
                <div class="info-value description-text">{{ $voucher->description }}</div>
            </div>
            @endif
        </div>
        
        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="35%">{{ translate('Account Head') }}</th>
                    <th width="25%">{{ translate('Account Code') }}</th>
                    <th width="15%" class="text-right">{{ translate('Debit') }} (৳)</th>
                    <th width="15%" class="text-right">{{ translate('Credit') }} (৳)</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalDebit = 0;
                    $totalCredit = 0;
                @endphp
                @foreach($voucher->details as $index => $detail)
                    @php
                        $totalDebit += $detail->debit ?? 0;
                        $totalCredit += $detail->credit ?? 0;
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $detail->account->name ?? 'N/A' }}</strong>
                            @if($detail->description)
                                <br><small class="text-muted">{{ $detail->description }}</small>
                            @endif
                        </td>
                        <td>{{ $detail->account->code ?? 'N/A' }}</td>
                        <td class="text-right">
                            @if($detail->debit > 0)
                                <strong>{{ number_format($detail->debit, 2) }}</strong>
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-right">
                            @if($detail->credit > 0)
                                <strong>{{ number_format($detail->credit, 2) }}</strong>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        
        <!-- Amount Summary -->
        <div class="amount-summary">
            <div class="summary-row">
                <div class="summary-label">{{ translate('Total Debit') }}:</div>
                <div class="summary-value">{{ number_format($totalDebit, 2) }} ৳</div>
            </div>
            <div class="summary-row">
                <div class="summary-label">{{ translate('Total Credit') }}:</div>
                <div class="summary-value">{{ number_format($totalCredit, 2) }} ৳</div>
            </div>
            @if($totalDebit == $totalCredit)
            <div class="summary-row total-row">
                <div class="summary-label">{{ translate('Total Amount') }}:</div>
                <div class="summary-value"><strong>{{ number_format($totalDebit, 2) }} ৳</strong></div>
            </div>
            <div class="summary-row">
                <div class="summary-label"></div>
                <div class="summary-value text-success">
                    <i class="las la-check-circle"></i> {{ translate('Balanced') }}
                </div>
            </div>
            @else
            <div class="summary-row total-row" style="color: #e74a3b;">
                <div class="summary-label">{{ translate('Difference') }}:</div>
                <div class="summary-value">{{ number_format(abs($totalDebit - $totalCredit), 2) }} ৳</div>
            </div>
            @endif
        </div>
        
        <!-- In Words -->
        <div style="padding: 0 20px 20px 20px; font-size: 11px;">
            <strong>{{ translate('Amount in Words') }}:</strong> 
            {{ convertNumberToWords($totalDebit) }} {{ translate('Taka Only') }}
        </div>
        
        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">{{ translate('Prepared By') }}</div>
                <div style="margin-top: 5px; font-size: 10px;">
                    @if($voucher->creator)
                        {{ $voucher->creator->name }}
                    @else
                        ___________________
                    @endif
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">{{ translate('Checked By') }}</div>
                <div style="margin-top: 5px; font-size: 10px;">___________________</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">{{ translate('Approved By') }}</div>
                <div style="margin-top: 5px; font-size: 10px;">___________________</div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="voucher-footer">
            <div>{{ translate('This is a computer generated voucher') }}</div>
            <div style="margin-top: 5px;">
                {{ translate('Print Date') }}: {{ now()->format('d-m-Y h:i A') }}
            </div>
            @if($voucher->type == 'CP' || $voucher->type == 'BP')
            <div style="margin-top: 5px; color: #e74a3b;">
                <strong>{{ translate('Note') }}:</strong> {{ translate('This payment is made against proper authorization') }}
            </div>
            @elseif($voucher->type == 'CR' || $voucher->type == 'BR')
            <div style="margin-top: 5px; color: #1cc88a;">
                <strong>{{ translate('Note') }}:</strong> {{ translate('Received with thanks') }}
            </div>
            @endif
        </div>
    </div>
    
    <!-- PDF Generation Script -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
    function generatePDF() {
        const element = document.querySelector('.voucher-container');
        const opt = {
            margin: [0.5, 0.5, 0.5, 0.5],
            filename: '{{ $voucher->voucher_no }}_{{ date('Y-m-d') }}.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, letterRendering: true },
            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
        };
        
        // Show loading indicator
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="las la-spinner la-spin"></i> Generating PDF...';
        btn.disabled = true;
        
        html2pdf().set(opt).from(element).save().then(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }).catch(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert('{{ translate("Error generating PDF. Please try again.") }}');
        });
    }
    
    // Auto trigger print if parameter is set
    @if(request()->has('print'))
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500);
    };
    @endif
    </script>
</body>
</html>