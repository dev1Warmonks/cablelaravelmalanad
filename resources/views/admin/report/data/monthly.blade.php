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
				<th>Lco Cust ID</th>
				<th>Name</th>
				<th>Address</th>
				<th>Mobile</th>
				<th>This month paid</th>
				<th>Due</th>
				<th>Last paid Date</th>
			</tr>
		</thead>
		<tbody>
@foreach($rows as $row)
			<tr>
				<td>{{ $row->lco_customer_id }}</td>
				<td>{{ $row->customer_name }}</td>
				<td>{{ $row->billing_address }}</td>
				<td>{{ $row->mobile }}</td>
				<td>{{ $row->paid }}</td>
				<td>{{ $row->due }}</td>
				<td>{{ $row->last }}</td>
			</tr>
@endforeach
		</tbody>
		<tr class="bg-navy">
			<td>Total</td>
			<td>{{ count($rows) }}</td>
			<td></td>
			<td></td>
			<td>{{ $totalPaid }}</td>
			<td>{{ $totalDue }}</td>
			<td></td>
		</tr>
	</table>
</div>
