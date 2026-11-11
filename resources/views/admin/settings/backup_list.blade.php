<script>
	$(function () {
	$("#history1").DataTable();
	$("#example2").DataTable({
		"paging": false,
		"lengthChange": false,
		"searching": false,
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
	<table id="example2" class="table table-bordered table-striped table-hover table-sm" cellspacing="0"
		width="100%">
			<thead>
				<tr>
					<th>Name</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
			@foreach($files as $row)
				<tr>
					<td>{{ $row }}</td>
					<td><a download href="{{ url('AdminSettings/download_backup/'.$row) }}"><span>Download</span></a>
					<a href="#" onClick="deleteBackup(`{{ $row }}`);return false;" ><span style="color:red">Delete</span></a></td>
				</tr>
			@endforeach
			</tbody>
	</table>
</div>
