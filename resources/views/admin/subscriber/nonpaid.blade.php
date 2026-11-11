@extends('admin.layout')

@section('content')
<script>
	$(document).ready(function() {
		area = -1;
		subarea	= -1;
		status = -1;
		filteramount = null;
		paidstat = 2;
		$('#example').DataTable( {
     	"ajax": {
			"url": "{{ url('Subscriber/index/list') }}",
			"data": {'area': area,'subarea' : subarea,'status' : status,'filteramount' : filteramount,'paidstat' : paidstat},
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
		
	});
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
				<h3 class="box-title">Non Paid This month Subscriber List</h3>
			</div>
		</div>
	</div>
</div>
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
