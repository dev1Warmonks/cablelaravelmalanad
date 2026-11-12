@extends('admin.layout')

@section('content')
<script>
$( document ).ready(function() {
		$(".select2").select2();
});
	$(document).ready(function() {
		$("#ddsearchparam").change(function () { 
			getSubscriberDetails();
		});
		type = $("#type option:selected").val();
		head = $("#head option:selected").val();
		drsearchfromdate = $("#drsearchfromdate").val();
		drsearchtodate = $("#drsearchtodate").val();
		$('#example2').DataTable( {
			"ajax": {
				"url": "{{ url('Transaction/miscellaneous_report') }}/"+type,
				"type": "POST",
				"data": {'head': head,'drsearchfromdate' : drsearchfromdate,'drsearchtodate' : drsearchtodate},
				"dataSrc": "",
				"processing": true,
				"deferRender": true,
				// "columnDefs": [ {
				// 	"targets": -1,
				// 	"data": null,
				// 	"defaultContent": "<img style='cursor:pointer;' src='{{ url('/').'/'.'/assets/img/view.png' }}'>"
				// } ]
			},
			"columns": [
				{ "data": "lco_customer_id" },
				{ "data": "customer_name" },
				{ "data": "date" },
				{ "data": "amount" },
				{ "data": "description" },
				// { data: null, render: function ( data, type, row ) {
				//     return '<a href="{{ url('/').'/'.'Subscriber/view_subscriber/' }}'+data.customer_id+'" target="_blank" class="editor_edit">View</a> / <a href="{{ url('/').'/'.'Subscriber/edit_subscriber_view/' }}'+data.customer_id+'" class="editor_remove" target="_blank">Edit</a>';
				// } },
			],
			//rowCallback: function (row, data) {
			// if (data.status == 0) {
			//     $(row).addClass('deactive');
			// }},
			"paging": true,
			"lengthMenu": [[10, 25, 50,100,500, -1], [10, 25, 50,100,500, "All"]],
			"responsive": true,
			"dom": "Blfrtip",
			"lengthChange": true,
			"searching": true,
			"ordering": true,
			"scrollX": true,
			"fixedHeader": true,
			"autoWidth": false,	  
			"order": [[ 0, "asc" ]]
		});
	});
	
	function MiscReportClick(type,head,drsearchfromdate,drsearchtodate)
	{
		$('body').addClass('loading');
		$.ajax({
			"url": "{{ url('Transaction/miscellaneous_report') }}/"+type,
			"data": {'head': head,'drsearchfromdate' : drsearchfromdate,'drsearchtodate' : drsearchtodate},
			type: "POST",
			success:function(result)
			{
				$('#example2').dataTable().fnClearTable();
				if(result.length > 0)
				{
					$('#example2').dataTable().fnAddData(result);
				}
				$('body').removeClass('loading');
			},
			error:function(result){ 
				alertify.error('Failed to fetch Miscellaneous Payments Report');
				$('body').removeClass('loading');		
			}
		});
	}
</script>
<div class="row">
	<div class="col-md-12">
		<div class="box box-danger">
			<div class="box-header with-border">
				<h3 class="box-title">Miscellaneous Payments Report</h3>
			</div>
			<div class="row">
				<div class="col-md-12">
					<div class="col-md-3">
						<div class="form-group">
							<span>Report Type</span>
							<div>
								<select id="type" class="form-control select2">
									<option value="0">Adding Report</option>
									<option value="1">Payment Report</option>
								</select>
							</div>
						</div>
					</div>
					<div class="col-md-3">
						<div class="form-group">
							<span>Head</span>
							<div>
								<select id="mischead" name="head" class="form-control select2">
								<option value="0">ALL</option>
								@foreach($miscellaneousheads as $rows)
								<option value="{{ $rows['name'] }}">{{ $rows['name'] }}</option>
								@endforeach	
								</select>
							</div>
						</div>
					</div>
					<div class="col-md-2">
						<div class="form-group">
							<span>From date</span>
							<input type="date" id="drsearchfromdate" value="{{ date("Y-m-d") }}" class="form-control"/>
						</div>
					</div>
					<div class="col-md-2">
						<div class="form-group">
							<span>To date</span>
							<input type="date" id="drsearchtodate" value="{{ date("Y-m-d") }}" class="form-control"/>
						</div>
					</div>
					<div class="col-md-2">
						<button type="button" style="margin-top: 20px;" onClick="MiscReportClick($('#type option:selected').val(),$('#mischead option:selected').val(),$('#drsearchfromdate').val(), $('#drsearchtodate').val()); return false;"  class="form-control btn-warning">Search</button>
					</div>
				</div>
			</div>
			<div class="box-body no-padding" style="height:700px; overflow-y:auto;">
			<table id="example2" class="table table-bordered table-striped table-sm" cellspacing="0" width="100%">
					<thead>
						<tr class="bg-navy">	
							<th>LCO Customer ID</th>
							<th>Name</th>
							<th>Date</th>
							<th>Amt</th>
							<th>Remarks</th>
						</tr>		
					</thead>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection
