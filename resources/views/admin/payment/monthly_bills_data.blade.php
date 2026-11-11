<div style="overflow-x:auto;"><table class="table table-striped table-bordered" style="white-space: nowrap;">
<tr class="bg-navy">
<td>Year Month</td>
<td>Invoice Generated</td>
<td>Free sms used</td>
<td>Extra sms used</td>
<td>Invoice Total</td>
<td>Payment Status</td>
<td></td>
</tr>
@if($data['empty'])
<tr><td colspan="7" style="text-align:center;">No billing history found for this date range.</td></tr>
@endif
@foreach($data['rows'] as $r)
<tr style="cursor:pointer;">
<td>{{ $r['yearmonth'] }}</td>
<td>{{ $r['invoices'] }}</td>
<td>{{ $r['free_sms'] }}</td>
<td>{{ $r['paid_sms'] }}</td>
<td style="font-weight:700;">{{ number_format($r['invoice_total'], 2) }}</td>
@if($r['payable'])
<td style="text-align:center"><span style="color:red;">{{ $r['status'] }}</span></td>
<td style="text-align:center"><button onclick="payBill(`{{ $r['current_month'] }}`,`{{ $r['bill_id'] }}`)">Pay&nbsp;Now</button></td>
@else
<td style="font-weight:700;color:green;text-align:center">{{ $r['status'] }}</td><td></td>
@endif
</tr>
@endforeach
<tr class="bg-navy">
<td>Total</td>
<td>{{ $data['totals']['generated'] }}</td>
<td>{{ $data['totals']['free_sms'] }}</td>
<td>{{ $data['totals']['extra_sms'] }}</td>
<td>{{ number_format($data['totals']['invoice'], 2) }}</td>
<td></td>
<td></td>
</tr>
</table></div>
