@extends('admin.layout')

@section('content')
<script>
	var due;
	function addSubscriberPayment()
	{
		current_month = $('#current_month').val();
		$('body').addClass('loading');//agentid=0 admin
		alertify.confirm("Would you like to sent SMS?", function (e) {
			if (e) {
				$.ajax({
					url: "{{ url('import/sendsmstopendingcustomers') }}/"+current_month,
					type: "POST",
					success:function(result){ 
						alertify.alert('SMS Added to queue').set({onshow:null, onclose:function(){ location.reload();}});
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
		}).setHeader('SMS').set('oncancel', function(closeEvent){$('body').removeClass('loading');});
	}
</script>
<div class="row">
<div class="col-md-6">
	<div class="box box-danger">
		<div class="box-header with-border">
			<h3 class="box-title">Send SMS</h3>
		</div>
		<input type="hidden" id="current_month" value="{{ $current_month }}"/>
		<div class="box-body">				
			<!-- form start -->
			<form class="form-horizontal">
				<div class="box-body no-padding">
					<div class="form-group"></div>
				</div>
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="paymentdate" class="col-sm-3 control-label">SMS Balance</label>
						<div class="col-sm-9">
							<input type="text" value="{{ $sms_balance }}" class="form-control" disabled/>
						</div>
					</div>	
				</div>
				<div class="box-body no-padding">
					<div class="form-group">
						<label for="paymentdate" class="col-sm-3 control-label">Pending SMS count</label>
						<div class="col-sm-9">
							<input type="text" value="{{ $pending_sms_count }}" class="form-control" disabled/>
						</div>
					</div>	
				</div>
				@if($pending_sms_count <= $sms_balance)
					<div onClick="addSubscriberPayment();" class="box-footer btn-success col-sm-3" style="cursor:pointer;float:right">
						<center><b>Send SMS</b></center>
					</div>
				@else
				<div style="text-align: center;color: red;"><span>Insufficient sms balance</span></div>
				@endif
			</form>
		</div>
		
	</div>
</div>
</div>

<div id="paymenthistory"></div>
@endsection
