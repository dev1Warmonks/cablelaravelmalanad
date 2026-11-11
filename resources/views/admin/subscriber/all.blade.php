@extends('admin.layout')

@section('content')
<script>
	$(document).ready(function() {
		area = $("#area option:selected").val();
		subarea	= $("#subarea option:selected").val();
		status = $("#status option:selected").val();
		network = $("#network option:selected").val();
		$('#example').DataTable( {
			"ajax": {
				"url": "{{ url('Subscriber/all_subscriber/list') }}",
				"data": {'area': area,'subarea' : subarea,'status' : status,'network' : network},
				"type": "POST",
				"dataSrc": "",
				"processing": true,
				"deferRender": true,
				
			},
		  "columns": [
          	{ "data": "lco_customer_id" },
			{ "data": "customer_name" },
			{ "data": "mobile" },
			{ "data": "billing_address" },
			{ "data": "area" },
			{ "data": "subarea" },
			{ "data": "due" },
			{ "data": "lastpaid" },
        ],
		rowCallback: function (row, data) {
        if (data.status == 0) {
            $(row).addClass('deactive');
        }},
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
		"order": [[ 0, "asc" ]],
		"buttons": [ 
			{	extend: "pdfHtml5",
				pageSize: 'LEGAL',
				orientation: 'landscape'
			}, 
			{ 	extend: 'print',
				pageSize: 'LEGAL',
				orientation: 'landscape',
			},
			{
                extend: 'excel',
            },
		"copy", "csv","colvis"]});
});
	$(function () {
		$(".select2").select2();
	});
	$( document ).ready(function() {
		$("#getsubscriber").click(function(){
			area = $("#area option:selected").val();
			subarea	= $("#subarea option:selected").val();
			status = $("#status option:selected").val();
			network = $("#network option:selected").val();
			getsubscriber(area,subarea,status,network);
		});
		$("#area").change(function () {
			area_id = $('#area option:selected').val();
			getsubarealist(area_id);
		});
	});
	function getsubscriber(area,subarea,status,network)
	{
		$('body').addClass('loading');
		$.ajax({
			url: "{{ url('Subscriber/all_subscriber/list') }}",
			type: "POST",
			data: {'area': area,'subarea' : subarea,'status' : status,'network' : network},
			success:function(result)
			{
				$('#example').dataTable().fnClearTable();
				if(result.length > 0)
				{
					$('#example').dataTable().fnAddData(result);
				}
				$('body').removeClass('loading');
			},
			error:function(result){ 
				alertify.error('Failed to fetch Subscriber');
				$('body').removeClass('loading');		
			}
		});
	}
	function getsubarealist(area_id)
	{
		if(area_id !== undefined)
		{
			$('#subarea').empty();
			$.ajax({
				url: "{{ url('Subscriber') }}/sub_area",
				type: "POST",
				dataType: "json",
				data: {'area_id': area_id},
				success:function(result)
				{
					if(result.success == true)
					{
						var data = result.data;
						var newOption = new Option('ALL SUB AREA', -1, false, false);
						$('#subarea').append(newOption).trigger('change');
						for (var i in data)
						{
							if (data[i].hasOwnProperty("id") && data[i].hasOwnProperty("areaname")) 
							{
								id = data[i].id;
								areaname = data[i].areaname;
								var areadata = {
									id: id,
									text: areaname
								};
								var newOption = new Option(areadata.text, areadata.id, false, false);
								$('#subarea').append(newOption).trigger('change');
							}
						}
						sub_area_id = -1;
						$('#subarea').val(sub_area_id);
						$('#subarea').trigger('change');
					}
				},
				error:function(result){ 
					alertify.error('Failed to fetch Sub area');					
				}
			});
		}
	}
</script>
<style>
.deactive
{
	background-color: lightpink !important;
}
</style>
<div class="row">
	<div class="col-md-12">
		<div class="box box-danger">
			<div class="box-header with-border" style="text-align: center;">
				<h3 class="box-title">Subscriber List</h3>
			</div>
		</div>
	</div>
	<div class="col-md-4">
		<div class="form-group">
			<span>Network</span>
			<select id="network" class="form-control select2">
				@php
					foreach($all_network_list as $row) {
						echo '<option value="'.e($row['lcoCode']).'">' .e($row['network_name']).'</option>';
					}
				@endphp
			</select>
		</div>
	</div>
	<div class="col-md-2">
		<div class="form-group">
			<span>Area</span>
			<select id="area" class="form-control select2">
				<option value="-1">ALL AREA</option>
				@php
					foreach($arealist as $row) {
						echo '<option value="'.e($row['id']).'">' .e($row['areaname']).'</option>';
					}
				@endphp
			</select>
		</div>
	</div>
	<div class="col-md-2">
		<div class="form-group">
			<span>Sub Area</span>
			<select id="subarea" class="form-control select2">
				<option value="-1">ALL SUB AREA</option>
			</select>
		</div>
	</div>
	<div class="col-md-2">
		<div class="form-group">
			<span>Status</span>
			<select id="status" class="form-control select2">
				<option value="-1">ALL STATUS</option>
				<option value="1">Active</option>
				<option value="0">Inactive</option>
			</select>
		</div>
	</div>
	<div class="col-md-2">
		<button type="button" style="margin-top: 20px;" id="getsubscriber" class="form-control btn-warning">Search</button>
	</div>
</div>
@php if(isset($has_generate_bill) && $has_generate_bill) { @endphp
<div class="row" style="margin-bottom: 15px;">
	<div class="col-md-3">
		<div class="form-group">
			<span>Bill Month</span>
			<input type="month" id="bill_month" class="form-control" value="{{ date('Y-m') }}" />
		</div>
	</div>
	<div class="col-md-3">
		<button type="button" style="margin-top: 20px;" id="generate_network_bill_btn" class="form-control btn-success">Generate Bill</button>
	</div>
	<div class="col-md-3" style="display:none;">
		<button type="button" style="margin-top: 20px;" id="delete_network_bill" class="form-control btn-danger">Delete Generated Bill</button>
	</div>
</div>
<script>
$(document).ready(function() {
	$("#generate_network_bill_btn").click(function() {
		var network = $("#network option:selected").val();
		var bill_month = $("#bill_month").val();
		if(!network) {
			alertify.error('Please select a network');
			return;
		}
		alertify.confirm("Are you sure you want to generate bills for selected network and month (" + bill_month + ")?", function (e) {
			if (e) {
				$('body').addClass('loading');
				$.ajax({
					url: "{{ url('Subscriber/generate_network_bill') }}",
					type: "POST",
					data: {'network': network, 'bill_month': bill_month},
					dataType: "json",
					success: function(res) {
						$('body').removeClass('loading');
						if(res.status) {
							alertify.alert("Bill Generation Completed!\nActive Bills Generated: " + res.active_count + "\nPending Bills: " + res.pending_count + "\nTotal Subscribers: " + res.total_count);
							var area = $("#area option:selected").val();
							var subarea = $("#subarea option:selected").val();
							var status = $("#status option:selected").val();
							getsubscriber(area, subarea, status, network);
						} else {
							alertify.error(res.message || 'Failed to generate bills');
						}
					},
					error: function() {
						$('body').removeClass('loading');
						alertify.error('Error occurred while generating bills');
					}
				});
			}
		});
	});

	$("#delete_network_bill_btn").click(function() {
		var network = $("#network option:selected").val();
		var bill_month = $("#bill_month").val();
		if(!network) {
			alertify.error('Please select a network');
			return;
		}
		alertify.confirm("WARNING: Are you sure you want to DELETE generated bills for selected network and month (" + bill_month + ")?", function (e) {
			if (e) {
				$('body').addClass('loading');
				$.ajax({
					url: "{{ url('Subscriber/delete_network_bill') }}",
					type: "POST",
					data: {'network': network, 'bill_month': bill_month},
					dataType: "json",
					success: function(res) {
						$('body').removeClass('loading');
						if(res.status) {
							alertify.alert("Bill Deletion Completed!\nDeleted Bills: " + res.deleted_count);
							var area = $("#area option:selected").val();
							var subarea = $("#subarea option:selected").val();
							var status = $("#status option:selected").val();
							getsubscriber(area, subarea, status, network);
						} else {
							alertify.error(res.message || 'Failed to delete bills');
						}
					},
					error: function() {
						$('body').removeClass('loading');
						alertify.error('Error occurred while deleting bills');
					}
				});
			}
		});
	});
});
</script>
@php } @endphp
<!-- style="max-height:700px; overflow-y:scroll;" -->
<div id="container" ></div>
<table id="example" class="table table-bordered table-striped table-sm" cellspacing="0" width="100%">
	<thead>
		<tr>
			<td>LCO Cust ID</td>
			<td>Customer name</td>
			<td>Mobile</td>
			<td>Address</td>
			<td>Area</td>
			<td>Sub area</td>
			<td>Due</td>
			<td>Last paid date</td>
		</tr>
	</thead>
</table>
@endsection
