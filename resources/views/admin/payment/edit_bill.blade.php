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
	
	function addSubscriberPayment()
	{
		customerid = $('#customerid').val();
		invoiceid = $('#invoiceid').val();
		amount = $('#amount').val();
		days = $('#days').val();
		referer = $('#referer').val();

		$('body').addClass('loading');//agentid=0 admin
		alertify.confirm("Would you like to add this payment?", function (e) {
			if (e) {
				$.ajax({
					url: "{{ url('Transaction/approve') }}/"+invoiceid+'/'+customerid+'/'+amount+'/'+days,
					type: "POST",
					data: {'referer' : referer},
					success:function(result){ 
						alertify.alert('Payment added');
						window.location.href = referer;
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

</script>
<div class="row">
<div class="col-md-12">
	<div class="box box-danger">
		<div class="box-header with-border">
			<h3 class="box-title">Edit Bill</h3>
		</div>
		<input type="hidden" id="customerid" value="{{ $customer[0]['id'] }}"/>
		<input type="hidden" id="invoiceid" value="{{ $invoice[0]['id'] }}"/>
		<input type="hidden" id="referer" value="{{ $referer }}"/>
		<div class="box-body">				
			<!-- form start -->
			<form class="form-horizontal">
				<div class="box-body no-padding">
					<div class="form-group"></div>
				</div>
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="paymentdate" class="col-sm-3 control-label">Customer name</label>
						<div class="col-sm-9">
							<input type="text" value="{{ $customer[0]['customer_name'] }}" class="form-control" disabled/>
						</div>
					</div>	
				</div>
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="paymentdate" class="col-sm-3 control-label">Customer mobile</label>
						<div class="col-sm-9">
							<input type="text" value="{{ $customer[0]['mobile'] }}" class="form-control" disabled/>
						</div>
					</div>	
				</div>
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="paymentdate" class="col-sm-3 control-label">Packages</label>
						<div class="col-sm-9">
							<input type="text" value="{{ $invoice[0]['package'] }}" class="form-control" disabled/>
						</div>
					</div>	
				</div>
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="paymentdate" class="col-sm-3 control-label">Serial number</label>
						<div class="col-sm-9">
							<input type="text" value="{{ $invoice[0]['serial_number'] }}" class="form-control" disabled/>
						</div>
					</div>	
				</div>
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="paymentdate" class="col-sm-3 control-label">Ezy Bill Total</label>
						<div class="col-sm-9">
							<input type="text" value="{{ $invoice[0]['total_amount'] }}" class="form-control" disabled/>
						</div>
					</div>	
				</div>
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="amount" class="col-sm-3 control-label">System calculated amount</label>
						<div class="col-sm-9">
							<input type="text" onkeypress="return event.charCode >= 48 && event.charCode <= 57" class="form-control" value="{{ $current_bill_amount }}" id="amount" placeholder="System calculated amount"/>
						</div>
					</div>	
				</div>
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="days" class="col-sm-3 control-label">System calculated days</label>
						<div class="col-sm-9">
							<input type="text" onkeypress="return event.charCode >= 48 && event.charCode <= 57" class="form-control" id="days" value="{{ $no_of_days }}" placeholder="System calculated days"/>
						</div>
					</div>	
				</div>
				
			</form>
		</div>
		<div onClick="addSubscriberPayment();" class="box-footer btn-success" style="cursor:pointer;">
			<center><b>Save and generate bill</b></center>
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
