@extends('admin.layout')

@section('content')
<script>
	$(document).ready(function() {
		area = $("#area option:selected").val();
		subarea	= $("#subarea option:selected").val();
		status = $("#status option:selected").val();
		filteramount = $("#filteramount").val();
		paidstat = $("#paidstat").val();
		$('#example').DataTable( {
     	"ajax": {
			"url": "{{ url('Subscriber/index/Mlist') }}",
			"data": {'area': area,'subarea' : subarea,'status' : status,'filteramount' : filteramount,'paidstat' : paidstat},
			"dataSrc": "",
			"processing": true,
			"deferRender": true,
			"columnDefs": [ {
				"targets": -1,
				"data": null,
				"defaultContent": "<img style='cursor:pointer;' src='{{ url('/').'/assets/img/view.png' }}'>"
			} ]
  		},
		  "columns": [
          	{ "data": "lco_customer_id" },
			{ "data": "customer_name" },
			{ "data": "billing_address" },
			{ "data": "area" },
			{ "data": "subarea" },
			{ "data": "mobile" },
			{ "data": "other_mobile" },
			{ data: null, render: function ( data, type, row ) {
                return '<td style="text-align:center"><img style="cursor:pointer;height: 20px;" src="../assets/img/save.png" onclick="updateMobileClick('+data.customer_id+','+data.other_mobile+')">';
            } },
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
				orientation: 'landscape',
				title : {{ "'".$header["network_name"]." ".$header["report_name"]."'" }},
				message: '\r\n{{ $header["lcoCode"] }}\r\n{{ $header["address"] }}\r\n{{ $header["phone"] }}',
				exportOptions: { columns: [ 0, 1, 2, 3,4,5,6, ] },
				customize: function ( doc ) {
					var cols = [];
					cols[0] = {text: {{ '"'.session('username').'"' }}, alignment: 'left', margin:[20] };
					cols[1] = {text: {{ '"'.date('Y-m-d h:i:s A').'"' }}, alignment: 'right', margin:[0,0,20] };
					var objFooter = {};
					objFooter['columns'] = cols;
					doc['footer']=objFooter;
				}
			}, 
			{ 	extend: 'print',
				pageSize: 'LEGAL',
				orientation: 'landscape',
				exportOptions: { columns: [ 0, 1, 2, 3,4,5,6] },
				title : {{ "'<center>".$header["network_name"]." ".$header["report_name"]."</center>'" }},
				message: '{{ $header["lcoCode"] }}<br>{{ $header["address"] }}<br>{{ $header["phone"] }}',
				customize: function ( win ) {
                    $(win.document.body)
                        .css( 'font-size', '10pt' )
                        .append("{{ session('username')."</br>".date('Y-m-d h:i:s A') }}");
                    $(win.document.body).find( 'table' )
                        .addClass( 'compact' )
                        .css( 'font-size', 'inherit' );
                }
			},
			{
                extend: 'excel',
				title : {{ "'".$header["network_name"]." ".$header["report_name"]."'" }},
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
			filteramount = $("#filteramount").val();
			paidstat = $("#paidstat").val();
			getsubscriber(area,subarea,status,filteramount,paidstat);
		});
		$("#area").change(function () {
			area_id = $('#area option:selected').val();
			getsubarealist(area_id);
		});
	});
	function getsubscriber(area,subarea,status,filteramount,paidstat)
	{
		$('body').addClass('loading');
		$.ajax({
			url: "{{ url('Subscriber/index/Mlist') }}",
			type: "POST",
			data: {'area': area,'subarea' : subarea,'status' : status,'filteramount' : filteramount,'paidstat' : paidstat},
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
	function updateMobileClick($customer_id,$new_number)
	{
		alertify.confirm("Would u like to update this mobile number?", function (e) { 
			if (e) {
				$.ajax({
					url: "{{ url('Subscriber') }}/update_mobile",
					type: "POST",
					data: {'customer_id': $customer_id,"new_number" : $new_number},
					success:function(result)
					{
						alertify.alert(result);
						area = $("#area option:selected").val();
						subarea	= $("#subarea option:selected").val();
						status = $("#status option:selected").val();
						filteramount = $("#filteramount").val();
						paidstat = $("#paidstat").val();
						getsubscriber(area,subarea,status,filteramount,paidstat);
					},
					error:function(result){ 
						alertify.error('Failed').setHeader('Message');					
					}
				});
				//alert($voucherid);
				//generateBill();
			} else {
				alertify.error("Operation Cancelled").setHeader('Message');
			}
		}).setHeader('Confirm');		
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
				<h3 class="box-title">Subscriber Mobile Number Update</h3>
			</div>
		</div>
	</div>
	<div class="col-md-3">
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
	<div class="col-md-3">
		<div class="form-group">
			<span>Sub Area</span>
			<select id="subarea" class="form-control select2">
				<option value="-1">ALL SUB AREA</option>
			</select>
		</div>
	</div>
	<div class="col-md-2" style="display:none">
		<div class="form-group">
			<span>This Month Paid Status</span>
			<select id="paidstat" class="form-control select2">
				<option value="-1">ALL</option>
				<option value="1">Paid</option>
				<option value="2">Non Paid</option>
			</select>
		</div>
	</div>
	<div class="col-md-2" style="display:none">
		<div class="form-group">
			<span>Amount</span>
			<input type="text" class="form-control" id="filteramount" placeholder="Amount" />
		</div>
	</div>
	<div class="col-md-3">
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
<!-- style="max-height:700px; overflow-y:scroll;" -->
<div id="container" ></div>
<table id="example" class="table table-bordered table-striped table-sm" cellspacing="0" width="100%">
	<thead>
		<tr>
			<td>LCO Cust ID</td>
			<td>Customer name</td>
			<td>Address</td>
			<td>Area</td>
			<td>Sub area</td>
			<td>Mobile</td>
			<td>Other Mobile</td>
			<td>Action</td>
		</tr>
	</thead>
</table>
@endsection
