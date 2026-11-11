@extends('admin.layout')

@section('content')
<script>
	var due;
	$( document ).ready(function() {
		$(".select2").select2();
		$("#ddsearchparam").change(function () { 
			getSubscriberDetails();
		});
		$("#subscriberpayments").DataTable({
			"paging": true,
			"lengthChange": true,
			"dom": "Bfrtip",
			"searching": false,
			"ordering": false,
			"info": true,
			"scrollX": true,
			"autoWidth": true,	  
			"order": [[ 0, "asc" ]],
			"buttons": [ 
				{	extend: "pdfHtml5",
					pageSize: 'LEGAL',
					//orientation: 'landscape',
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
				"copy", "csv","colvis"],
			"columns": [
				{ "data": "timestamp" },
				{ "data": "description" },
				{ "data": "type" },
				{ "data": "dueamount" },
				{ "data": "subscriptioncharge" },
				{ "data": "miscellaneous" },
				{ "data": "paidamount" },
				{ "data": "discount" },
				{ "data": "dbafterdiscount" }
        	],
		});
	});
	function getSubscriberDetails()
	{
		$subscriberid = $('#ddsearchparam option:selected').val(); 
		
		$('body').addClass('loading');
		$.ajax({
			url: "{{ url('Transaction') }}/subscriber_details/",
			type: "POST",
			data: {'subscriberid': $subscriberid},
			success:function(result){ 
				var obj = JSON.parse(result);
				if(obj.success)
				{
					$('#subscriberid').val(obj.id);
					$('#memberid').val(obj.customer_id);
					$('#subscribername').val(obj.customer_name);
					$('#mobile').val(obj.mobile);
					$('#stbnumber').val(obj.stb);
					$('#newid').val(obj.newid);
					$('#address').val(obj.address);
					$('#dueamount').html(obj.amountdue);
					$('#ddpackage').val(obj.package_id);
					if(obj.amc_status == 1) 
					{
						$('#amc_status').removeClass('bg-red');
						$('#amc_status').addClass('bg-green');
					}
					else 
					{
						$('#amc_status').removeClass('bg-green');
						$('#amc_status').addClass('bg-red');
						
					}
					//$('#opening_balance').val(obj.opening_balance);
					due = obj.amountdue;			
					getPaymentHistory(obj.id);
				}
				else 
				{
					alertify.alert('No Records');
					$('#subscriberid').val("");
					$('#subscribername').val("");
					$('#mobile').val("");
					$('#stbnumber').val("");
					$('#newid').val("");
					$('#cafnumber').val("");
					$('#dueamount').html("");
					$('#ddpackage').val(0);
				}
				$('body').removeClass('loading');
			},
			error:function(result){ 
				alertify.error('Failed');		
				$('body').removeClass('loading');				
			}
		});
	}
	
	function getPaymentHistory($subscriberid)
	{
		$.ajax({
			url: "{{ url('Transaction') }}/subscriber_payments",
			type: "POST",
			data: {'subscriberid': $subscriberid},
			success:function(result)
			{
				$('#subscriberpayments').dataTable().fnClearTable();
				if(result.length > 0)
				{
					$('#subscriberpayments').dataTable().fnAddData(result);
				}
			},
			error:function(result){ 
				alertify.error('Failed to fetch PaymentHistory');					
			}
		});
	}
	
	function makeSubscriberPayment()
	{
		$paymentdate = $('#paymentdate').val();
		$paidamount = $('#paidamount').val();
		$discount = $('#discount').val();
		$comment = $('#comment').val();
		$subscriberid = $('#subscriberid').val();
		$othermobile = $('#othermobile').val();
		$trantype = $('#trantype option:selected').val();
		
		if($subscriberid == '')
		{
			alertify.error('Please select subscriber');
			$('#subscriberid').focus();
			return;
		}
		if($paidamount.length == '' || $paidamount == '' || $paymentdate == '' || $trantype == '')
		{
			alertify.error('Fill necessary details');
			return;
		}
		
		$('body').addClass('loading');
		alertify.confirm("Would you like to make this payment?", function (e) {
			if (e) {
				$.ajax({
					url: "{{ url('Transaction/add_payment') }}",
					type: "POST",
					data: {'subscriberid' : $subscriberid ,'paymentdate': $paymentdate, 'paidamount': $paidamount, 
					'discount': $discount, 'comment': $comment, 'agentid': '0','othermobile':$othermobile,'trantype':$trantype},
					success:function(result){ 
						var today = new Date();
						var dd = today.getDate();
						var mm = today.getMonth()+1; 
						var yyyy = today.getFullYear();
						if(dd<10) {	dd='0'+dd; } 
						if(mm<10) {	mm='0'+mm; } 
						$('#paymentdate').val(yyyy+'-'+mm+'-'+dd);
						$('#paidamount').val("");
						$('#discount').val("");
						$('#comment').val("");
						$('#othermobile').val("");
						$('body').removeClass('loading');
						if(result.status == "success")
                        {
							alertify.alert(result.message, function() {
								location.reload();
							}).setHeader('<em> Payment</em>');
						}
						$subscriberid = $('#ddsearchparam option:selected').val();
						getPaymentHistory($subscriberid);
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
		}).set('oncancel', function(closeEvent){$('body').removeClass('loading');}).setHeader('Payment');
	}
	
	function balanceUpdate(val)
	{		
		if(isNaN(val))
		{
			var val=0;
		}

		if(!isNaN(val))
		{
			var paid = $('#paidamount').val();
			if(paid != "") paid = $('#paidamount').val();
			else paid = 0;		
			var discount = $('#discount').val();
			if(discount != "") discount = $('#discount').val();
			else discount = 0;

			var netBalance = parseInt(due) - parseInt(paid) - parseInt(discount);
			if(isNaN(netBalance))
			{
				$('#dueamount').html(due);
			}
			else
			{
				$('#dueamount').html(netBalance);
			}
		}
	}
</script>
<div class="row">
<div class="col-md-12">
	<form class="form-horizontal">
		<div class="form-group">
			<label for="ddsearchparam" class="col-sm-1 control-label">Subscriber*</label>
			<div class="col-sm-5">
				<select name="ddsearchparam" id="ddsearchparam" class="form-control select2">
					<option value="0">- Select -</option>
					@php
						foreach($subscriber as $row) {
							$newid_label = (!empty($row['newid'])) ? ' (' . $row['newid'] . ')' : '';
							echo '<option value="'.e($row['id']).'">' .e($row['customer_name']) . ' (' . e($row['mobile']) .') '.e($row['lco_customer_id']). e($newid_label) . '</option>';
						}
					@endphp
				</select>
			</div>
			<div class="col-sm-6">
			<div class="col-sm-12" id="amc_status">				
				<center><b><h4>AMC</h4></b></center>
			</div>
				<!--<button type="text" class="form-control btn btn-info" onClick="getSubscriberDetails(); return false;">Search</button>-->
			</div>
		</div>	
	</form>
</div>
</div>
<div class="row">
<div class="col-md-6">
	<div class="box box-danger">
		<div class="box-header with-border">
			<h3 class="box-title">Subscriber Details</h3>
		</div>
		<!-- /.box-header -->
				
		<!-- form start -->
		<form class="form-horizontal">
			<div class="box-body">
				<div class="form-group" style="display:none;">
					<label for="subscriberid" class="col-sm-3 control-label">Subscriber ID</label>

					<div class="col-sm-9">
						<input type="text" class="form-control" id="subscriberid" placeholder="Subscriber ID" readonly />
					</div>
				</div>	
				
				<div class="form-group">
					<label for="memberid" class="col-sm-3 control-label">Member ID</label>

					<div class="col-sm-9">
						<input type="text" class="form-control" id="memberid" placeholder="Memberid ID" readonly />
					</div>
				</div>
				
				<div class="form-group">
					<label for="subscribername" class="col-sm-3 control-label">Subscriber Name</label>

					<div class="col-sm-9">
						<input type="text" class="form-control" id="subscribername" placeholder="Subscriber Name" readonly />
					</div>
				</div>	
				
				<div class="form-group">
					<label for="mobile" class="col-sm-3 control-label">Mobile</label>

					<div class="col-sm-9">
						<input type="text" class="form-control" id="mobile" placeholder="Mobile" readonly />
					</div>
				</div>	
				
				<div class="form-group">
					<label for="stbnumber" class="col-sm-3 control-label">STB Number</label>

					<div class="col-sm-9">
						<input type="text" class="form-control" id="stbnumber" placeholder="STB Number" readonly />
					</div>
				</div>	
				
				<div class="form-group">
					<label for="newid" class="col-sm-3 control-label">NUID</label>

					<div class="col-sm-9">
						<input type="text" class="form-control" id="newid" placeholder="NUID" readonly />
					</div>
				</div>	
				
				<div class="form-group">
					<label for="address" class="col-sm-3 control-label">Address</label>

					<div class="col-sm-9">
						<textarea class="form-control" id="address" placeholder="Address" rows="3" readonly ></textarea>
					</div>
				</div>	
				<!-- <div class="form-group">
					<label for="opening_balance" class="col-sm-3 control-label">Opening balance</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" id="opening_balance" placeholder="Opening balance" readonly />
					</div>
				</div>	 -->
			</div>
			  <!-- /.box-body -->
			
			<!--<div class="box-footer">
				<button type="button" id="submitpackagecreate" name="submitpackagecreate" value="submitpackagecreate" class="btn btn-danger pull-right">Create</button>
			</div>
			 /.box-footer -->
		</form>
	</div>
</div>

<div class="col-md-6">
	<div class="box box-danger">
		<div class="box-header with-border">
			<h3 class="box-title">Make Payment</h3>
		</div>
		<div class="form-group">
			<div class="col-sm-12 bg-red">				
				<center><b><h4>Payment Due: Rs. <span id="dueamount">0</span>/-</h4></b></center>
			</div>
		</div>
		<div class="box-body">				
			<!-- form start -->
			<form class="form-horizontal">
				<div class="box-body no-padding">
					<div class="form-group"></div>
				</div>
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="paymentdate" class="col-sm-3 control-label">Payment Date*</label>

						<div class="col-sm-9">
							<input type="date" value="{{ date("Y-m-d") }}" class="form-control" id="paymentdate" />
						</div>
					</div>	
				</div>
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="paidamount" class="col-sm-3 control-label">Paid Amount*</label>

						<div class="col-sm-9">
							<input type="text" onkeypress="return event.charCode >= 48 && event.charCode <= 57" class="form-control" id="paidamount" onkeyup="balanceUpdate(parseInt(this.value));" maxlength="4" placeholder="Paid Amount"/>
						</div>
					</div>	
				</div>

				<div class="box-body no-padding">
					<div class="form-group">
						<label for="trantype" class="col-sm-3 control-label">Transaction type*</label>
						<div class="col-sm-9">
							<select name="trantype" id="trantype" class="form-control select2">
								<option value="1">Subscription Charge</option>
								@php
									foreach($miscellaneousheads_list as $row) {
										echo '<option value="'.e($row['id']).'">' .e($row['name']).'</option>';
									}
								@endphp
							</select>
						</div>
					</div>
				</div>

				<div class="box-body no-padding">
					<div class="form-group">
						<label for="discount" class="col-sm-3 control-label">Discount</label>

						<div class="col-sm-9">
							<input type="text" onkeypress="return event.charCode >= 48 && event.charCode <= 57" class="form-control" id="discount" placeholder="Discount" maxlength="4" onkeyup="balanceUpdate(parseInt(this.value));"/>
						</div>
					</div>	
				</div>
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="comment" class="col-sm-3 control-label">Comments</label>

						<div class="col-sm-9">
							<textarea class="form-control" id="comment" placeholder="Comments" maxlength="250" rows="3"></textarea>
						</div>
					</div>	
				</div>

				<div class="box-body no-padding">
					<div class="form-group">
						<label for="discount" class="col-sm-3 control-label">Transaction SMS for other mobile</label>

						<div class="col-sm-9">
							<input type="text" onkeypress="return event.charCode >= 48 && event.charCode <= 57" class="form-control" id="othermobile" maxlength="10" placeholder="Transaction SMS for other mobile"/>
						</div>
					</div>	
				</div>

				
			</form>
		</div>
		<div onClick="makeSubscriberPayment();" class="box-footer btn-success" style="cursor:pointer;">
			<center><b>Make payment</b></center>
		</div>
	</div>
</div>
</div>
<div class="box">
	<div class="box-header">
		<h3 class="box-title">Payments</h3>
	</div>
	<div class="box-body">
		<table id="subscriberpayments" class="table table-bordered table-striped" style="width:100%">
			<thead>
				<tr>
					<td>Date</td>
					<td>Description</td>
					<td>Type</td>
					<td>Previous Due</td>
					<td>Subs Charge</td>
					<td>Miscellaneous</td>
					<td>Paid</td>
					<td>Discount</td>
					<td>Balance</td>
				</tr>
			</thead>
		</table>
	</div>
</div>
@endsection
