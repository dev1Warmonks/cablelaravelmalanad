@extends('admin.layout')

@section('content')
<style>
.switch {
  position: relative;
  display: inline-block;
  width: 60px;
  height: 34px;
}

.switch input { 
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  -webkit-transition: .4s;
  transition: .4s;
}

.slider:before {
  position: absolute;
  content: "";
  height: 26px;
  width: 26px;
  left: 4px;
  bottom: 4px;
  background-color: white;
  -webkit-transition: .4s;
  transition: .4s;
}

input:checked + .slider {
  background-color: #2196F3;
}

input:focus + .slider {
  box-shadow: 0 0 1px #2196F3;
}

input:checked + .slider:before {
  -webkit-transform: translateX(26px);
  -ms-transform: translateX(26px);
  transform: translateX(26px);
}
</style>
<script>
// 	$(document).ready(function() {
// 		area = $("#area option:selected").val();
// 		subarea	= $("#subarea option:selected").val();
// 		status = $("#status option:selected").val();
// 		filteramount = $("#filteramount").val();
// 		paidstat = $("#paidstat").val();
// 		$('#example').DataTable( {
//      	"ajax": {
// 			"url": "{{ url('Subscriber/index/list') }}",
// 			"data": function(d) {
//                 // Add custom filters to the request
//                 d.area = $("#area option:selected").val();
//                 d.subarea = $("#subarea option:selected").val();
//                 d.status = $("#status option:selected").val();
//                 d.filteramount = $("#filteramount").val();
//                 d.paidstat = $("#paidstat").val();
                
//                 // Add DataTables parameters
//                 d.draw = d.draw;
//                 d.start = d.start;
//                 d.length = d.length;
//                 d.order = d.order;
//                 d.search = d.search;
//             },			
			
// 			"type":"post",
// 			"processing": true,
// 			"deferRender": true,
// 			"serverSide": true,
// 			"columnDefs": [ {
// 				"targets": -1,
// 				"data": null,
// 				"defaultContent": "<img style='cursor:pointer;' src='{{ url('/').'/assets/img/view.png' }}'>"
// 			} ]
//   		},
// 		  "columns": [
//           	{ "data": "lco_customer_id" },
// 			{ "data": "customer_name" },
// 			{ "data": "mobile" },
// 			{ "data": "billing_address" },
// 			{ "data": "area" },
// 			{ "data": "subarea" },
// 			{ "data": "due" },
// 			{ "data": "lastpaid" },
// 			{ data: null, render: function ( data, type, row ) {
// 				if(data.amc_status == 1)
// 				{
// 					return '<span style="color:green">Yes</span>';
// 				}
// 				else{
// 					return '<span style="color:red">No</span>';
// 				}
// 			}},
// 			//{ "data": "sms_stat" },
// 			{
// 				data: null,
// 				render: function ( data, type, row ) {
// 					checked = "";
// 					if(data.sms_stat == 1)
// 					{
// 						checked = "checked";
// 					}
// 					return '<label class="switch"><input type="checkbox" '+checked+' onClick="updateSMSstat('+data.customer_id+',this)"><span class="slider"></span></label>';
// 				}
// 			},
// 			{ data: null, render: function ( data, type, row ) {
//                 return '<a href="{{ url('/').'/'.'Subscriber/view_subscriber/' }}'+data.customer_id+'" target="_blank" class="editor_edit">View</a> / <a href="{{ url('/').'/'.'Subscriber/edit_subscriber_view/' }}'+data.customer_id+'" class="editor_remove" target="_blank">Edit</a>';
//             } },
//         ],
// 		rowCallback: function (row, data) {
//         if (data.status == 0) {
//             $(row).addClass('deactive');
//         }},
// 		"paging": true,
// 		"lengthMenu": [[10, 25, 50,100,500, -1], [10, 25, 50,100,500, "All"]],
// 		"responsive": true,
// 		"dom": "Blfrtip",
// 		"lengthChange": true,
// 		"searching": true,
// 		"ordering": true,
// 		"scrollX": true,
// 		"fixedHeader": true,
// 		"autoWidth": false,	  
// 		"order": [[ 0, "asc" ]],
// 		"buttons": [ 
// 			{	extend: "pdfHtml5",
// 				pageSize: 'LEGAL',
// 				orientation: 'landscape',
// 				title : {!! json_encode($header["network_name"].' '.$header["report_name"], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!},
// 				message: '\r\n{{ $header["lcoCode"] }}\r\n{{ $header["address"] }}\r\n{{ $header["phone"] }}',
// 				exportOptions: { columns: [ 0, 1, 2, 3,4,5,6, ] },
// 				customize: function ( doc ) {
// 					var cols = [];
// 					cols[0] = {text: {!! json_encode((string) session('username'), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!}, alignment: 'left', margin:[20] };
// 					cols[1] = {text: {!! json_encode(date('Y-m-d h:i:s A'), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!}, alignment: 'right', margin:[0,0,20] };
// 					var objFooter = {};
// 					objFooter['columns'] = cols;
// 					doc['footer']=objFooter;
// 				}
// 			}, 
// 			{ 	extend: 'print',
// 				pageSize: 'LEGAL',
// 				orientation: 'landscape',
// 				exportOptions: { columns: [ 0, 1, 2, 3,4,5,6] },
// 				title : {!! json_encode('<center>'.e($header["network_name"]).' '.e($header["report_name"]).'</center>', JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!},
// 				message: {!! json_encode(e($header["lcoCode"]).'<br>'.e($header["address"]).'<br>'.e($header["phone"]), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!},
// 				customize: function ( win ) {
//                     $(win.document.body)
//                         .css( 'font-size', '10pt' )
//                         .append("{{ session('username')."</br>".date('Y-m-d h:i:s A') }}");
//                     $(win.document.body).find( 'table' )
//                         .addClass( 'compact' )
//                         .css( 'font-size', 'inherit' );
//                 }
// 			},
// 			{
//                 extend: 'excel',
// 				title : {!! json_encode($header["network_name"].' '.$header["report_name"], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!},
//             },
// 		"copy", "csv","colvis"]});
// });
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    var results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}
var comboPlans = {!! json_encode(isset($comboplan) ? $comboplan : array()) !!};
function escHtml(s) {
	return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
		return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
	});
}
$(document).ready(function() {
		var stype = getUrlParameter('stype');
		if(stype == 1){
			$("#subrlist").text("IPTV Subsriber List");
		}else if(stype == 2){
			$("#subrlist").text("Broadband Subscriber List");
		}else
		{
			$("#subrlist").text("CableTV Subscriber List");
		}
		area = $("#area option:selected").val();
		subarea	= $("#subarea option:selected").val();
		status = $("#status option:selected").val();
		filteramount = $("#filteramount").val();
		paidstat = $("#paidstat").val();
		$('#example').DataTable( {
     	"ajax": {
			"url": "{{ url('Subscriber/index/list') }}",
			"data": {'area': area,'subarea' : subarea,'status' : status,'filteramount' : filteramount,'paidstat' : paidstat,'stype': stype},
			"dataSrc": "",
			"type":"post",
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
			{ "data": "mobile" },
			{ "data": "billing_address" },
			{ "data": "area" },
			{ "data": "subarea" },
			{
				data: null,
				render: function ( data, type, row ) {
					var selectedCombo = data.combo_plan || "";
					var rawPkgName = data.package_name || "-";
					var pkgName = rawPkgName.toString().trim().toLowerCase();
					var html = '<div style="font-weight: 600; margin-bottom: 4px;">' + escHtml(rawPkgName) + '</div>';
					html += '<select class="form-control form-control-sm combo-plan-select" data-customer-id="' + escHtml(data.customer_id) + '" style="min-width: 140px;">';
					html += '<option value="">- Select -</option>';
					$.each(comboPlans, function(i, plan) {
						var planName = (plan.name || "").toString().trim().toLowerCase();
						var selected = (plan.id == selectedCombo || (pkgName !== "" && pkgName !== "-" && planName === pkgName)) ? 'selected' : '';
						html += '<option value="' + escHtml(plan.id) + '" ' + selected + '>' + escHtml(plan.name) + '</option>';
					});
					html += '</select>';
					return html;
				}
			},
			{ "data": "package_amount" },
			{ "data": "newid" },
			{ "data": "due" },
			{ "data": "lastpaid" },
			{ data: null, render: function ( data, type, row ) {
				if(data.amc_status == 1)
				{
					return '<span style="color:green">Yes</span>';
				}
				else{
					return '<span style="color:red">No</span>';
				}
			}},
			//{ "data": "sms_stat" },
			{
				data: null,
				render: function ( data, type, row ) {
					checked = "";
					if(data.sms_stat == 1)
					{
						checked = "checked";
					}
					return '<label class="switch"><input type="checkbox" '+checked+' onClick="updateSMSstat('+data.customer_id+',this)"><span class="slider"></span></label>';
				}
			},
			{ data: null, render: function ( data, type, row ) {
                return '<a href="{{ url('/').'/'.'Subscriber/view_subscriber/' }}'+data.customer_id+'" target="_blank" class="editor_edit">View</a> / <a href="{{ url('/').'/'.'Subscriber/edit_subscriber_view/' }}'+data.customer_id+'" class="editor_remove" target="_blank">Edit</a>';
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
				title : {!! json_encode($header["network_name"].' '.$header["report_name"], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!},
				message: '\r\n{{ $header["lcoCode"] }}\r\n{{ $header["address"] }}\r\n{{ $header["phone"] }}',
				exportOptions: { columns: [ 0, 1, 2, 3,4,5,6, ] },
				customize: function ( doc ) {
					var cols = [];
					cols[0] = {text: {!! json_encode((string) session('username'), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!}, alignment: 'left', margin:[20] };
					cols[1] = {text: {!! json_encode(date('Y-m-d h:i:s A'), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!}, alignment: 'right', margin:[0,0,20] };
					var objFooter = {};
					objFooter['columns'] = cols;
					doc['footer']=objFooter;
				}
			}, 
			{ 	extend: 'print',
				pageSize: 'LEGAL',
				orientation: 'landscape',
				exportOptions: { columns: [ 0, 1, 2, 3,4,5,6] },
				title : {!! json_encode('<center>'.e($header["network_name"]).' '.e($header["report_name"]).'</center>', JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!},
				message: {!! json_encode(e($header["lcoCode"]).'<br>'.e($header["address"]).'<br>'.e($header["phone"]), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!},
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
				title : {!! json_encode($header["network_name"].' '.$header["report_name"], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!},
            },
		"copy", "csv","colvis"]});

	$(document).on("change", ".combo-plan-select", function() {
		var selectElem = $(this);
		var customerId = selectElem.data("customer-id");
		var comboPlanId = selectElem.val();

		var newRate = "0.00";
		var newPlanName = "";
		$.each(comboPlans, function(i, plan) {
			if (plan.id == comboPlanId) {
				newRate = parseFloat(plan.rate || 0).toFixed(2);
				newPlanName = plan.name || "";
				return false;
			}
		});

		$.ajax({
			url: "{{ url('Subscriber/update_combo_plan') }}",
			type: "POST",
			data: { 'customer_id': customerId, 'combo_plan': comboPlanId },
			dataType: "json",
			success: function(res) {
				if(res.status) {
					alertify.success("Plan updated successfully");
					var tr = selectElem.closest("tr");
					tr.find("td").eq(7).html(newRate);
					if (newPlanName !== "") {
						selectElem.siblings("div").text(newPlanName);
					}
				} else {
					alertify.error(res.message || "Failed to update plan");
				}
			},
			error: function() {
				alertify.error("Error updating plan");
			}
		});
	});
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
	function updateSMSstat(customer_id,element)
	{
		smsStat = 0;
		if($(element).is(":checked")) {
			smsStat = 1;
		}
		$('body').addClass('loading');
		$.ajax({
			url: "{{ url('Subscriber/updateSMSstat') }}",
			type: "POST",
			data: {'customer_id': customer_id,'smsStat' : smsStat},
			success:function(result)
			{
				if(result == "Failed")
				{
					alertify.error('Please try again');
					if(smsStat == 0) $(element).prop('checked', true);
					else $(element).prop('checked', false);
				}
				$('body').removeClass('loading');
			},
			error:function(result){ 
				$('body').removeClass('loading');		
			}
		});
	}
	function getsubscriber(area,subarea,status,filteramount,paidstat)
	{
		$('body').addClass('loading');
		$.ajax({
			url: "{{ url('Subscriber/index/list') }}",
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
				<h3 class="box-title"><span id="subrlist"></span></h3>
			</div>
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
			<span>This Month Paid Status</span>
			<select id="paidstat" class="form-control select2">
				<option value="-1">ALL</option>
				<option value="1">Paid</option>
				<option value="2">Non Paid</option>
			</select>
		</div>
	</div>
	<div class="col-md-2">
		<div class="form-group">
			<span>Amount</span>
			<input type="text" class="form-control" id="filteramount" placeholder="Amount" />
		</div>
	</div>
	<div class="col-md-2">
		<div class="form-group">
			<span>Status</span>
			<select id="status" class="form-control select2">
				<option value="-1">ALL STATUS</option>
				<option value="1">Active</option>
				<option value="0">Inactive</option>
				<option value="2">Invisible</option>
			</select>
		</div>
	</div>
	<div class="col-md-2">
		<button type="button" style="margin-top: 20px;" id="getsubscriber" class="form-control btn-warning">Search</button>
	</div>
</div>
@php if(isset($has_generate_bill) && $has_generate_bill) { @endphp
<div class="row" style="margin-bottom: 15px;">
	<div class="col-md-3" style="display:none;">
		<div class="form-group">
			<span>Bill Month</span>
			<input type="month" id="bill_month" class="form-control" value="{{ date('Y-m') }}" />
		</div>
	</div>
	<div class="col-md-3">
		<button type="button" style="margin-top: 20px;" id="generate_network_bill_btn" class="form-control btn-success">Generate Bill</button>
	</div>
	<div class="col-md-3" style="display:none;">
		<button type="button" style="margin-top: 20px;" id="delete_network_bill_btn" class="form-control btn-danger">Delete Generated Bill</button>
	</div>
</div>
<script>
$(document).ready(function() {
	$("#generate_network_bill_btn").click(function() {
		var network = '{{ session()->has('lcoCode') ? session('lcoCode') : "" }}';
		var bill_month = $("#bill_month").val() || '{{ date('Y-m') }}';
		if(!network) {
			alertify.error('Network not found for this user');
			return;
		}
		alertify.confirm("Are you sure you want to generate bills for network (" + network + ") and month (" + bill_month + ")?", function (e) {
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
							var filteramount = $("#filteramount").val();
							var paidstat = $("#paidstat").val();
							getsubscriber(area, subarea, status, filteramount, paidstat);
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
		var network = '{{ session()->has('lcoCode') ? session('lcoCode') : "" }}';
		var bill_month = $("#bill_month").val();
		if(!network) {
			alertify.error('Network not found for this user');
			return;
		}
		alertify.confirm("WARNING: Are you sure you want to DELETE generated bills for network (" + network + ") and month (" + bill_month + ")?", function (e) {
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
							var filteramount = $("#filteramount").val();
							var paidstat = $("#paidstat").val();
							getsubscriber(area, subarea, status, filteramount, paidstat);
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
<table id="example" class="table table-bordered table-striped table-sm " cellspacing="0" width="100%">
	<thead>
		<tr>
			<td>LCO Cust ID</td>
			<td>Customer name</td>
			<td>Mobile</td>
			<td>Address</td>
			<td>Area</td>
			<td>Sub area</td>
			<td>Package</td>
			<td>Package Amount</td>
			<td>NUID</td>
			<td>Due</td>
			<td>Last paid date</td>
			<td>AMC</td>
			<td>SMS</td>
			<td>Actions</td>
		</tr>
	</thead>
</table>
@endsection
