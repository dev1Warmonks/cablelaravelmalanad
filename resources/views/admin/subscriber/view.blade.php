@extends('admin.layout')

@section('content')
@php 
if(count($subscriber) > 0)
{
	$customer_name = $subscriber[0]['customer_name'];
	$customer_id = $subscriber[0]['customer_id'];
	$caf_number = $subscriber[0]['caf_number'];
	$lcoCode = $subscriber[0]['lcoCode'];
	$lco_customer_id = $subscriber[0]['lco_customer_id'];
	$area = $subscriber[0]['area'];
	$subarea = $subscriber[0]['subarea'];
	
	$billing_address = $subscriber[0]['billing_address'];
	$status = $subscriber[0]['status'];
	if($status==1)$status = 'Active';else $status = 'Inactive';
	$enable_app = $subscriber[0]['enable_app'];
	if($enable_app==1)$enable_app = 'Active';else $enable_app = 'Inactive';
	$enable_bill_payment = $subscriber[0]['enable_bill_payment'];
	if($enable_bill_payment==1)$enable_bill_payment = 'Active';else $enable_bill_payment = 'Inactive';
	$serial_number = $subscriber[0]['serial_number'];
	$vc_number = $subscriber[0]['vc_number'];
	$mobile = $subscriber[0]['mobile'];
	$sms = $subscriber[0]['sms'];
	if($sms==1)$sms = 'Active';else $sms = 'Inactive';
}
@endphp
<style>
.form-control[readonly] {
    background-color: #fff;
}
</style>
<div class="row">
<div class="col-md-12">
	<div class="box box-danger">
		<div class="box-header with-border" style="text-align: center;">
			<h3 class="box-title">Customer Details</h3>
		</div>
		<form class="form-horizontal">
			<div class="box-body">
				<div class="form-group">
					<label class="col-sm-3 control-label">Customer Name</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $customer_name }}" readonly />
					</div>
				</div>	
				<div class="form-group">
					<label class="col-sm-3 control-label">Customer ID</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $customer_id }}" readonly />
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label">Mobile</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $mobile }}" readonly />
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label">CAF Number</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $caf_number }}" readonly />
					</div>
				</div>
				<!-- <div class="form-group">
					<label class="col-sm-3 control-label">LCO Code</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $lcoCode }}" readonly />
					</div>
				</div> -->
				<div class="form-group">
					<label class="col-sm-3 control-label">LCO Customer ID</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $lco_customer_id }}" readonly />
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label">Area</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $area }}" readonly />
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label">Sub Area</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $subarea }}" readonly />
					</div>
				</div>
				
				<div class="form-group">
					<label class="col-sm-3 control-label">Address</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $billing_address }}" readonly />
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label">Status</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $status }}" readonly />
					</div>
				</div>
				<div class="form-group" hidden>
					<label class="col-sm-3 control-label">Enable APP</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $enable_app }}" readonly />
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label">Enable Bill Payment</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $enable_bill_payment }}" readonly />
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label">Serial number</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $serial_number }}" readonly />
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label">VC Number</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $vc_number }}" readonly />
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label">SMS</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $sms }}" readonly />
					</div>
				</div>
			</div>
		</form>
	</div>
</div>
@endsection
