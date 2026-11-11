@extends('admin.layout')

@section('content')
<script>
	var due;
	$( document ).ready(function() {
		$(".select2").select2();
		//$("#ddsearchparam").val(1);
		$("#ddsearchparam").change(function () { 
			getSubscriberDetails();
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
					$('#cafnumber').val(obj.caf_no);
					$('#dueamount').html(obj.amountdue);
					$('#ddpackage').val(obj.package_id);
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
				$('#paymenthistory').html(result);
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
		
		if($paidamount.length == '' || $paidamount == '' || $paymentdate == '')
		{
			alertify.error('Fill necessary details');
			return;
		}
		
		$('body').addClass('loading');//agentid=0 admin
		alertify.confirm("Would you like to make this payment?", function (e) {
			if (e) {
				$.ajax({
					url: "{{ url('Transaction/add_broadband_payment') }}",   // old: relative "add_payment" -> Broadband/add_payment, which never existed
					type: "POST",
					data: {'subscriberid' : $subscriberid ,'paymentdate': $paymentdate, 'paidamount': $paidamount, 
					'discount': $discount, 'comment': $comment, 'agentid': '0'},
					success:function(result){ 
						alertify.alert('Payment Successful');
						$('#paymentdate').val("");
						$('#paidamount').val("");
						$('#discount').val("");
						$('#comment').val("");
						$('body').removeClass('loading');
						
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
		});
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
			<label for="ddsearchparam" class="col-sm-1 control-label">Search</label>
			<div class="col-sm-4">
				<select name="ddsearchparam" id="ddsearchparam" class="form-control select2">
					<option value="0">- Select -</option>
					@php
						foreach($subscriber as $row) {
							echo '<option value="'.e($row['id']).'">' . e($row['id']) . ' - ' . e($row['customer_name']) . ' (' . e($row['mobile']) .')</option>';
						}
					@endphp
				</select>
			</div>
			
			<div class="col-sm-2">
				<!--<button type="text" class="form-control btn btn-info" onClick="getSubscriberDetails(); return false;">Search</button>-->
			</div>
		</div>	
	</form>
</div>
</div>
<div class="row">
<div class="col-md-7">
	<div class="box box-danger" style="height:385px;">
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
					<label for="ddpackage" class="col-sm-3 control-label">Package</label>

					<div class="col-sm-9">
						<select name="ddpackage" id="ddpackage" class="form-control" disabled >
							<option value="0">- Select a Package -</option>
							@php
								foreach ($package as $row) {
									echo '<option value="'.e($row['id']).'">'.e($row['name']).'</option>';
								}
							@endphp
						</select>
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
					<label for="cafnumber" class="col-sm-3 control-label">CAF Number</label>

					<div class="col-sm-9">
						<input type="text" class="form-control" id="cafnumber" placeholder="CAF Number" readonly />
					</div>
				</div>	
			</div>
			  <!-- /.box-body -->
			
			<!--<div class="box-footer">
				<button type="button" id="submitpackagecreate" name="submitpackagecreate" value="submitpackagecreate" class="btn btn-danger pull-right">Create</button>
			</div>
			 /.box-footer -->
		</form>
	</div>
</div>

<div class="col-md-5">
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
						<label for="paymentdate" class="col-sm-3 control-label">Payment Date</label>

						<div class="col-sm-9">
							<input type="date" value="{{ date("Y-m-d") }}" class="form-control" id="paymentdate" />
						</div>
					</div>	
				</div>
				
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="paidamount" class="col-sm-3 control-label">Paid Amount</label>

						<div class="col-sm-9">
							<input type="text" onkeypress="return event.charCode >= 48 && event.charCode <= 57" class="form-control" id="paidamount" onkeyup="balanceUpdate(parseInt(this.value));" placeholder="Paid Amount"/>
						</div>
					</div>	
				</div>
				
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="discount" class="col-sm-3 control-label">Discount</label>

						<div class="col-sm-9">
							<input type="text" class="form-control" id="discount" placeholder="Discount" onkeyup="balanceUpdate(parseInt(this.value));"/>
						</div>
					</div>	
				</div>
				
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="comment" class="col-sm-3 control-label">Comments</label>

						<div class="col-sm-9">
							<textarea class="form-control" id="comment" placeholder="Comments" rows="3"></textarea>
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

<div id="paymenthistory"></div>
<script>
$(".select2").change(function () { 
		alert(1);
		//$selectedSubscriberID = $('#ddsearchparam" option:selected').val();
		
		//getSubscriberDetails();
	});
</script>
@endsection
