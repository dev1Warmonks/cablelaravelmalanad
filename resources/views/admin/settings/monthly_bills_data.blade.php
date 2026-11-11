<table class="table table-striped table-bordered" style="white-space: nowrap;overflow-x: auto;">
<tr class="bg-navy">
<td>Network</td>
<td>Year Month</td>
<td>Invoice Generated</td>
<td>Free sms used</td>
<td>Extra sms used</td>
<td>Invoice Total</td>
<td>Payment Status</td>
<td></td>
</tr>
@foreach($data['rows'] as $r)
<tr style="cursor:pointer;">
<td>{{ $r['network'] }}</td>
<td>{{ $r['yearmonth'] }}</td>
<td>{{ $r['invoices'] }}</td>
<td>{{ $r['free_sms'] }}</td>
<td>{{ $r['paid_sms'] }}</td>
<td style="font-weight:700;">{{ number_format($r['total'], 2) }}</td>
@if($r['payable'])
<td style="text-align:center"><span style="color:red;">{{ $r['status'] }}</span></td>
<td style="text-align:center"><button onclick="payBill(`{{ $r['current_month'] }}`,`{{ $r['lco'] }}`)">Pay&nbsp;Now</button></td>
@else
<td style="font-weight:700;color:green;text-align:center">{{ $r['status'] }}{{ $r['payment_type'] !== '' ? '('.$r['payment_type'].')' : '' }}</td>
<td style="text-align:center"><button onclick="payBill(`{{ $r['current_month'] }}`,`{{ $r['lco'] }}`)">Edit</button></td>
@endif
</tr>
@endforeach
<tr class="bg-navy">
<td></td>
<td>Total</td>
<td>{{ $data['totals']['generated'] }}</td>
<td>{{ $data['totals']['free'] }}</td>
<td>{{ $data['totals']['extra'] }}</td>
<td>{{ number_format($data['totals']['amount'], 2) }}</td>
<td></td>
<td></td>
</tr>
</table>
