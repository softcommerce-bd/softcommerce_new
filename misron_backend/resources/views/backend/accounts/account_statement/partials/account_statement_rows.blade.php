{{-- @dd($rows); --}}

@foreach($rows as $row)
@dd($row);
<tr>
    <td>{{ $row['date'] }}</td>
    <td>{{ $row['voucher_no'] }}</td>
    <td>{{ $row['reference'] }}</td>
    <td class="text-right text-success">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '0.00' }}</td>
    <td class="text-right text-danger">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '0.00' }}</td>
    <td class="text-right font-weight-bold">{{ number_format($row['balance'], 2) }}</td>
</tr>
@endforeach