@php
	$j = fn ($v) => json_encode((string) $v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
	$last = $variant === 'daily' ? 'Bal' : 'Outsatanding';
	$disc = $variant === 'daily' ? 'Disc' : 'Discount';
@endphp
<script>
	$(function () {
	$("#history1").DataTable();
	$("#example2").DataTable({
		"paging": false,
		"lengthChange": false,
		"searching": false,
		"dom": "Bfrtip",
		"ordering": false,
		"scrollX": true,
		"info": true,
		"fixedHeader": false,
		"autoWidth": false,
@if($variant === 'agent')
		"buttons": [ {	extend: "pdfHtml5",orientation: "landscape", pageSize: "LEGAL"}, {
			extend: "print",
			text: "Print",
			exportOptions: {
				modifier: {
					page: "all"
				}
			}
		},
		"copy", "csv", "excel"],
@elseif($variant === 'daily')
		"buttons": [
			{	extend: "pdfHtml5",
				pageSize: "LEGAL",
				orientation: "landscape",
				title : {!! $j($header['network_name'].' '.$header['report_name']) !!},
				message: {!! $j("\r\n".$header['lcoCode']."\r\n".$header['address']."\r\n".$header['phone']) !!},
				exportOptions: { columns: [ 0, 1, 2, 3,4,5,6,7,8,9,10] },
				customize: function ( doc ) {
					var cols = [];
					cols[0] = {text: {!! $j($username) !!},alignment: "left", margin:[20] };
					cols[1] = {text: {!! $j($now) !!}, alignment: "right", margin:[0,0,20] };
					var objFooter = {};
					objFooter["columns"] = cols;
					doc["footer"]=objFooter;
				}
			},
			{ 	extend: "print",
				pageSize: "LEGAL",
				orientation: "landscape",
				exportOptions: { columns: [ 0, 1, 2, 3,4,5,6,7,8,9,10] },
				title : {!! $j('<center>'.e($header['network_name'].' '.$header['report_name']).'</center>') !!},
				message: {!! $j(e($header['lcoCode']).'<br>'.e($header['address']).'<br>'.e($header['phone'])) !!},
				customize: function ( win ) {
					$(win.document.body)
						.css( "font-size", "10pt")
						.append({!! $j(e($username).'</br>'.$now) !!});
					$(win.document.body).find( "table" )
						.addClass( "compact" )
						.css( "font-size", "inherit" );
				}
			},
			{
				extend: "excel",
				title : {!! $j($header['network_name'].' '.$header['report_name']) !!},
			},
			"copy", "csv","colvis"],
@else
		"buttons": [
			{	extend: "pdfHtml5",
				pageSize: "LEGAL",
				orientation: "landscape",
			},
			{ 	extend: "print",
				pageSize: "LEGAL",
				orientation: "landscape",
				exportOptions: { columns: [ 0, 1, 2, 3,4,5,6,7,8,9,10] },
			},
			{
				extend: "excel",
			},
			"copy", "csv","colvis"],
@endif
	});
	});
</script>
<div class="box-body">
	<table id="example2" class="table table-bordered table-striped table-hover table-sm" cellspacing="0" width="100%">
		<thead>
			<tr>
				<th>Date</th>
				<th>Lco Cust ID</th>
				<th>Name</th>
				<th>Address</th>
				<th>Mobile</th>
				<th>Desc</th>
				<th>Due Amount</th>
				<th>Received</th>
				<th>{{ $disc }}</th>
				<th>{{ $last }}</th>
				<th>Agent</th>
				<th>SMS</th>
			</tr>
		</thead>
		<tbody>
@foreach($rows as $row)
			<tr>
				<td>{{ $row->date }}</td>
				<td>{{ $row->lco_customer_id }}</td>
				<td>{{ $row->customer_name }}</td>
				<td>{{ $row->billing_address }}</td>
				<td>{{ $row->mobile }}</td>
				<td>{{ $row->description }}</td>
				<td>{{ $row->dueamount }}</td>
				<td>{{ $row->paidamount }}</td>
				<td>{{ $row->discount }}</td>
				<td>{{ $row->dbafterdiscount }}</td>
				<td>{{ $row->agent }}</td>
				<td><img style="cursor:pointer;" src="{{ url('assets/img/sms.png') }}" onclick="smsView({{ (int) $row->id }})"></td>
			</tr>
@endforeach
		</tbody>
		<tr class="bg-navy">
			<td>Total</td>
			<td>{{ $count }}</td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td>{{ $totals['due'] }}</td>
			<td>{{ $totals['paid'] }}</td>
			<td>{{ $totals['discount'] }}</td>
			<td>{{ $totals['outstanding'] }}</td>
			<td></td>
			<td></td>
		</tr>
	</table>
</div>
