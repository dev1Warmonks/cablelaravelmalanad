<div><br/><center><h4><b>Displaying results from {{ $first }} to {{ $last }}</b></h4></center><div style="overflow-x:auto;">
<table class="table table-striped table-bordered">
<tr class="bg-navy">
	<td>Date</td>
	<td>Collection Count</td>
	<td>Collection Amt</td>
	<td>Discount Count</td>
	<td>Discount Amt</td>
</tr>
@foreach($days as $d)
<tr style="cursor:pointer;" onclick="viewDateReport('{{ $d['dmy'] }}','{{ $d['dmy'] }}')">
	<td>{{ $d['ymd'] }}</td>
	<td>{{ $d['cc'] }}</td>
	<td>{{ $d['ca'] }}</td>
	<td>{{ $d['dc'] }}</td>
	<td>{{ $d['da'] }}</td>
</tr>
@endforeach
<tr class="bg-navy">
	<td>Total</td>
	<td>{{ $total['cc'] }}</td><td>{{ $total['ca'] }}</td>
	<td>{{ $total['dc'] }}</td><td>{{ $total['da'] }}</td>
</tr>
</table></div></div>
