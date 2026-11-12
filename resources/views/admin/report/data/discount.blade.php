<div style="height:700px; overflow-y:scroll;">
<br/><center><h4><b>Displaying Discounts applied from {{ $first }} to {{ $last }}</b></h4></center>
<table class="table table-striped table-bordered" width="100%">
<tr class="bg-navy">
<td>Date</td><td>Member #</td><td>Name</td>
<td>Address</td><td>Mobile</td><td>Discount</td>
</tr>
@foreach($days as $d)
<tr>
<td colspan="6">{{ $d['ymd'] }}</td>
</tr>
@foreach($d['rows'] as $r)
<tr>
<td></td>
<td>{{ $r->lco_customer_id }}</td>
<td>{{ $r->customer_name }}</td>
<td>{{ $r->billing_address }}</td>
<td>{{ $r->mobile }}</td>
<td>{{ number_format((float) $r->discount, 2) }}</td>
</tr>
@endforeach
@endforeach
<tr class="bg-navy">
<td>Total</td><td></td><td></td>
<td></td><td></td>
<td>{{ $total }}</td>
</tr>
</table></div>
