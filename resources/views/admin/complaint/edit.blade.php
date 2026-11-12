@extends('admin.layout')

@section('content')
@php 
if(count($subscriber) > 0)
{
	$customer_name = $subscriber[0]['customer_name'];
	$network_name = $subscriber[0]['network_name'];
	$mobile = str_replace("'91",'',$subscriber[0]['mobile']);
	
	$lco_customer_id = $subscriber[0]['lco_customer_id'];
	$area = $subscriber[0]['area'];
	$billing_address = $subscriber[0]['address'];
	$status = $subscriber[0]['status'];
	$description = $subscriber[0]['description'];
	$created_on = $subscriber[0]['created_on'];
	$created_on = date("d-m-Y h:i A", strtotime($created_on));
	$accepted_on = $subscriber[0]['accepted_on'];
	if($accepted_on!='') $accepted_on = date("d-m-Y h:i A", strtotime($accepted_on));
	$resolved_on = $subscriber[0]['resolved_on'];
	if($resolved_on!='') $resolved_on = date("d-m-Y h:i A", strtotime($resolved_on));
	$technician = $subscriber[0]['technician'];
	$technician_remarks = $subscriber[0]['technician_remarks'];
	$o_message = $subscriber[0]['o_message'];
	$t_message = $subscriber[0]['t_message'];
	$c_message = $subscriber[0]['c_message'];
	$o_response = $subscriber[0]['o_response'];
	$t_response = $subscriber[0]['t_response'];
	$c_response = $subscriber[0]['c_response'];
	$complaint_id = $subscriber[0]['complaint_id'];
	$selected1 = "";$selected2 = "";$selected3 = "";
	switch($status)
	{
		case 1:
			$status = 'Pending';
			$selected1 = "selected"; 
		break;
		case 2:
			$status = 'On hold';
			$selected2 = "selected"; 
		break;
		case 3:
			$status = 'Resolved';
			$selected3 = "selected"; 
		break;
	}
@endphp
<script>
function updatecomplaint()
	{
		technician_remarks = $('#technician_remarks').val();
		statuslist = $('#statuslist').val();
		complaint_id = $('#complaint_id').val();

		$('body').addClass('loading');//agentid=0 admin
		alertify.confirm("Would you like to update this compalaint?", function (e) {
			if (e) {
				$.ajax({
					url: "{{ url('Complaint') }}/update_complaint",
					type: "POST",
					data: {'technician_remarks' : technician_remarks ,'statuslist': statuslist,'complaint_id' : complaint_id},
					success:function(result){ 
						$('body').removeClass('loading');
						alertify.alert(result);
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
<style>
.form-control[disabled] {
    background-color: #fff;
}
</style>
<div class="row">
<div class="col-md-12">
	<div class="box box-danger">
		<div class="box-header with-border" style="text-align: center;">
			<h3 class="box-title">Complaint Details</h3>
			<a style="float:left;font-weight: 700;" href="{{ url('complaint') }}">Back</a>
		</div>
		<input type="hidden" value="{{ $complaint_id }}" id="complaint_id" />
		<form class="form-horizontal">
			<div class="box-body">
				<div class="form-group">
					<label for="subscriberid" class="col-sm-3 control-label">Customer Name</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $customer_name }}" disabled />
					</div>
				</div>	
				<div class="form-group">
					<label for="subscriberid" class="col-sm-3 control-label">Network</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $network_name }}" disabled />
					</div>
				</div>
				<div class="form-group">
					<label for="subscriberid" class="col-sm-3 control-label">Mobile</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $mobile }}" disabled />
					</div>
				</div>
				<div class="form-group">
					<label for="subscriberid" class="col-sm-3 control-label">LCO Customer ID</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $lco_customer_id }}" disabled />
					</div>
				</div>
				<div class="form-group">
					<label for="subscriberid" class="col-sm-3 control-label">Area</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $area }}" disabled />
					</div>
				</div>
				<div class="form-group">
					<label for="subscriberid" class="col-sm-3 control-label">Address</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $billing_address }}" disabled />
					</div>
				</div>
				<div class="form-group">
					<label for="subscriberid" class="col-sm-3 control-label">Complaint</label>
					<div class="col-sm-9">
						<textarea class="form-control" rows="3" disabled >{{ $description }}</textarea>
					</div>
				</div>
				<div class="form-group">
					<label for="subscriberid" class="col-sm-3 control-label">Created on</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $created_on }}" disabled />
					</div>
				</div>
				<div class="form-group">
					<label for="statuslist" class="col-sm-3 control-label">Status</label>
					<div class="col-sm-9">
						<select name="statuslist" id="statuslist" class="form-control">
							<option value="0">- Select -</option>
							<option value="1" {{ $selected1 }}>Pending</option>
							<option value="2" {{ $selected2 }}>On Hold</option>
							<option value="3" {{ $selected3 }}>Resolved</option>
						</select>
					</div>
				</div>
				<div class="form-group">
					<label for="subscriberid" class="col-sm-3 control-label">Technician Remarks</label>
					<div class="col-sm-9">
						<textarea class="form-control" id="technician_remarks" rows="3">{{ $technician_remarks }}</textarea>
					</div>
				</div>
				<div onClick="updatecomplaint();" class="box-footer btn-success" style="cursor:pointer;">
					<center><b>Save</b></center>
				</div>
			</div>
		</form>
	</div>
</div>
@php } else echo 'error';@endphp
@endsection
