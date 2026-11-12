@extends('admin.layout')

@section('content')
<div class="row">
<div class="col-md-12">
	<div class="box box-danger">
		<div class="box-header with-border" style="text-align: center;">
			<h3 class="box-title">Complaint List</h3>
		</div>
<script>
	$(function () {
	$("#history1").DataTable();
	$("#example2").DataTable({
		"paging": true,
		"lengthChange": true,
		"dom": "Bfrtip",
		"searching": true,
		"ordering": true,
		"scrollX": true,
		"info": true,
		"autoWidth": true,
		"order": [[ 0, "asc" ]],
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
<div class="box">
	<div class="box-body">
		<table id="example2" class="table table-bordered table-striped table-hover table-sm" cellspacing="0" width="100%">
			<thead>
				<tr>
					<td>Customer name</td>
					<td>Complaint nature</td>
					<td>Address</td>
					<td>Created on</td>
					<td>Technician</td>
					<td>Description</td>
					<td>Status</td>
					<td>Action</td>
				</tr>
			</thead>
			<tbody>
@foreach($rows as $row)
				<tr>
					<td>{{ $row->customer_name }}</td>
					<td>{{ $row->type }}</td>
					<td>{{ $row->address }}</td>
					<td>{{ $row->created_on }}</td>
					<td>{{ $row->technician }}</td>
					<td>{{ $row->description }}</td>
					<td>{{ $row->status_text }}</td>
					<td><a href="{{ url('complaint/view_complaint/'.$row->complaint_id) }}"><img style="cursor:pointer;" src="{{ url('assets/img/view.png') }}"></a> &nbsp; &nbsp;<a href="{{ url('complaint/edit_complaint/'.$row->complaint_id) }}"><img style="cursor:pointer;" src="{{ url('assets/img/edit.png') }}"></a></td>
				</tr>
@endforeach
			</tbody>
		</table>
	</div>
</div>
	</div>
</div>
</div>
@endsection
