<div class="row">
<br/><center><h4><b>Displaying results from {{ $first }} to {{ $last }}</b></h4></center>
<div class="col-md-8"><table class="TFtable" width="100%">
<tr class="bg-navy">
<td>Date</td><td>Total#</td><td>Total Amount</td><td>Agents</td><td>Count#</td><td>Amount</td>
</tr>
@foreach($days as $d)
<tr style="background-color: #ff4000; color: #ffffff;">
<td>{{ $d['ymd'] }}</td>
<td>{{ $d['count'] }}</td>
<td><b>{{ $d['amount'] }}</b></td>
<td></td><td></td><td></td>
</tr>
@foreach($d['agents'] as $a)
<tr>
<td></td><td></td><td></td>
<td>{{ $a['name'] }}</td>
<td>{{ $a['count'] }}</td>
<td align="right">{{ $a['amount'] }}</td>
</tr>
@endforeach
<tr><td colspan="7"></td></tr>
@endforeach
</table></div>
<div class="col-md-4"><table class="TFtable" width="50%">
<tr class="bg-navy">
<td>Agent</td><td>Total Count</td><td>Total Amount</td>
</tr>
@foreach($totals as $a)
<tr>
<td>{{ $a['name'] }}</td>
<td>{{ $a['count'] }}</td>
<td align="right">{{ $a['amount'] }}</td>
</tr>
@endforeach
@if(count($totals) > 0)
<tr class="bg-navy">
<td>Total</td>
<td>{{ $totalCount }}</td><td align="right">{{ $totalAmount }}</td>
</tr>
@endif
</table></div></div>
