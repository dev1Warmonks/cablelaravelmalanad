@extends('admin.layout')

@section('content')
<script>
$( document ).ready(function() {
		$(".select2").select2();
});
	function addExpensetoDB()
	{
		$headid = $("#head option:selected").val(); 
		$date = $('#date').val();
		$amount = $('#amount').val();
		$comment = $('#comment').val();
		$subscriberid = $('#ddsearchparam option:selected').val();
		if($subscriberid == 0 || $date.length == '' || $amount == '' || $headid == 0)
		{
			alertify.error('Fill necessary details');
			return;
		}
		$('body').addClass('loading');
		alertify.confirm("Would you like to add this Miscellaneous payment?", function (e) {
			if (e) {
				$.ajax({
					url: "{{ url('Transaction') }}/add_miscellaneous",
					type: "POST",
					data: {'headid' : $headid ,'date': $date, 'amount': $amount, 'subscriberid':$subscriberid, 'comment' : $comment },
					success:function(result){ 
						alertify.alert(result.message).setHeader('Miscellaneous');
						$('#expenseamount').val("");
						$('#expensecomment').val("");
						
						$('body').removeClass('loading');
						location.reload();
						//addExpenses();
						
					},
					error:function(result){ 
						alertify.error('Failed');	
						$('body').removeClass('loading');						
					}
				});
			} else {
				alertify.error("Operation Cancelled");
				$('body').removeClass('loading');	
			}
		}).set('oncancel', function(closeEvent){$('body').removeClass('loading');}).setHeader('Miscellaneous');
	}
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
	function getSubscriberDetails()
	{
		$subscriberid = $('#ddsearchparam option:selected').val(); 
		$('#dueamount').val('');
		$('body').addClass('loading');
		$.ajax({
			url: "{{ url('Transaction') }}/subscriber_details/",
			type: "POST",
			data: {'subscriberid': $subscriberid},
			success:function(result){ 
				var obj = JSON.parse(result);
				if(obj.success)
				{
					amount = obj.amountdue;
					if(amount>=0)
					{
						$('#dueamount').val(amount);
						$("#dueamount_label").html("Due Amount");
						$("#dueamount").css({"background-color": "mistyrose"});
					}
					else
					{
						$('#dueamount').val(Math.abs(amount));
						$("#dueamount_label").html("Advance Amount");
						$("#dueamount").css({"background-color": "#41ff49"});
						
					}
				}
				$('body').removeClass('loading');
			},
			error:function(result){ 
				alertify.error('Failed');		
				$('body').removeClass('loading');				
			}
		});
	}
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
				<h3 class="box-title">Add Miscellaneous Payments</h3>
			</div><br>
			<form class="form-horizontal">
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="ddsearchparam" class="col-sm-4 control-label">Customer</label>
						<div class="col-sm-5">
							<select name="ddsearchparam" id="ddsearchparam" class="form-control select2">
								<option value="0">- Select -</option>
								@php
									foreach($subscriber as $row) {
										echo '<option value="'.e($row['id']).'">' .e($row['customer_name']) . ' (' . e($row['mobile']) .') '.e($row['lco_customer_id']).'</option>';
									}
								@endphp
							</select>
						</div>	
					</div>
					<div class="form-group">
						<label for="dueamount" id="dueamount_label" class="col-sm-4 control-label">Due Amount</label>
						<div class="col-sm-5">
							<input type="text" style="background: mistyrose;font-weight: 700;" class="form-control" id="dueamount"  disabled/>
						</div>
					</div>	
					<div class="box-body no-padding">
						<div class="form-group">
							<label for="date" class="col-sm-4 control-label">Date*</label>
							<div class="col-sm-5">
								<input type="date" value="{{ date("Y-m-d") }}" class="form-control" id="date" />
							</div>
						</div>
					</div>
					<div class="form-group">
						<label for="head" class="col-sm-4 control-label">Head*</label>
						<div class="col-sm-5">
							<select id="head" name="head" class="form-control select2">
							<option value="0">-Select-</option>
							@php
								foreach($miscellaneousheads as $rows) {
									echo '<option value="' . e($rows['id']) . '">' . e($rows['name']) . '</option>';
								}
							@endphp	
							</select>
						</div>
					</div>											
					<div class="form-group">
						<label for="amount" class="col-sm-4 control-label">Amount*</label>
						<div class="col-sm-5">
							<input type="text" onkeypress='return event.charCode >= 48 && event.charCode <= 57' class="form-control" id="amount" placeholder="Amount"/>
						</div>
					</div>					
					<div class="box-body no-padding">
						<div class="form-group">
							<label for="comment" class="col-sm-4 control-label">Comments</label>
							<div class="col-sm-5">
								<textarea class="form-control" id="comment" placeholder="Comment" rows="3"></textarea>
							</div>
						</div>	
					</div>
					<div class="box-body no-padding">
						<div class="form-group">
							<div class="col-sm-9">
								<button type="button" onClick="addExpensetoDB();" id="submitexpensecreate" name="submitexpensecreate" value="submitexpensecreate" class="btn btn-danger pull-right">Add Miscellaneous Payment</button>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
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
								@php
									foreach($miscellaneousheads as $rows) {
										echo '<option value="' . e($rows['name']) . '">' . e($rows['name']) . '</option>';
									}
								@endphp	
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
