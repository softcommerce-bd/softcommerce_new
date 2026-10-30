{{-- resources/views/backend/stock_adjustments/csv_export.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stock Adjustments</title>
    <style>
        table { 
            border-collapse: collapse; 
            width: 100%; 
        }
        th, td { 
            border: 1px solid #000; 
            padding: 5px 10px; 
            text-align: left;
        }
        th { 
            background-color: #4472C4; 
            color: white; 
            font-weight: bold; 
        }
        /* Set minimum widths to prevent ### */
        td:nth-child(1), th:nth-child(1) { min-width: 150px; } /* Date */
        td:nth-child(2), th:nth-child(2) { min-width: 130px; } /* Reference No */
        td:nth-child(3), th:nth-child(3) { min-width: 120px; } /* Type */
        td:nth-child(4), th:nth-child(4) { min-width: 120px; } /* Total Amount */
        td:nth-child(5), th:nth-child(5) { min-width: 120px; } /* Total Recovered */
        td:nth-child(6), th:nth-child(6) { min-width: 250px; } /* Reason */
        td:nth-child(7), th:nth-child(7) { min-width: 100px; } /* Added By */
    </style>
</head>
<body>
    <table border="1">
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
                    <td>{{ number_format($adjustment->total_amount_recovered, 2) }}</td>
                    <td>{{ $adjustment->additional_notes ?? '' }}</td>
                    <td>{{ $adjustment->creator->name ?? 'N/A' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>