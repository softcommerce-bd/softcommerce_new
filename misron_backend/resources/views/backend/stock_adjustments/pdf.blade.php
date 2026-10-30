{{-- resources/views/backend/stock_adjustments/pdf.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stock Adjustments</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h2 { text-align: center; color: #333; }
    </style>
</head>
<body>
    <h2>Stock Adjustments Report</h2>
    <p>Generated on: {{ date('m/d/Y H:i') }}</p>
    
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference No</th>
                <th>Adjustment Type</th>
                <th>Total Amount</th>
                <th>Total Recovered</th>
                <th>Reason</th>
                <th>Added By</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stock_adjustments as $adjustment)
                <tr>
                    <td>{{ $adjustment->transaction_date->format('m/d/Y H:i') }}</td>
                    <td>{{ $adjustment->reference_no }}</td>
                    <td>{{ ucfirst($adjustment->adjustment_type) }}</td>
                    <td>{{ number_format($adjustment->total_amount, 2) }}</td>
                    {{-- <td>{{ number_format($adjustment->total_amount_recovered, 2) }}</td> --}}
                    <td>{{ $adjustment->additional_notes ?? 'N/A' }}</td>
                    <td>{{ $adjustment->creator->name ?? 'N/A' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>