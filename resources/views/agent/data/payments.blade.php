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
				<th>Discount</th>
				<th>Outsatanding</th>
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
		</tr>
	</table>
</div>
