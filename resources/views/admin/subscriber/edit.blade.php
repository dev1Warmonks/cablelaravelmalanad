@extends('admin.layout')

@section('content')
@php 
if(count($subscriber) > 0)
{
	$selected1 = "";$selected0 = "";$selected2 = "";$appselected1 = "";$appselected0 = "";$smsselected1 = "";$smsselected0 = "";$amcselected1 = "";$amcselected0 = "";
	$customer_name = $subscriber[0]['customer_name'];
	$customer_id = $subscriber[0]['customer_id'];
	$subscriberid = $subscriber[0]['id'];
	$caf_number = $subscriber[0]['caf_number'];
	$lcoCode = $subscriber[0]['lcoCode'];
	$lco_customer_id = $subscriber[0]['lco_customer_id'];
	$area = $subscriber[0]['area'];
	$area_id = $subscriber[0]['area_id'];
	$sub_area_id = $subscriber[0]['subarea_id'];
	$billing_address = $subscriber[0]['billing_address'];
	$broadband_plan = $subscriber[0]['broadband_plan'];
	$combo_plan = $subscriber[0]['combo_plan'];
	$amc_end_date = $subscriber[0]['amc_end_date'];
	$status = $subscriber[0]['status'];
	if($status == 1){$selected1 = "selected";}else if($status == 0){$selected0 = "selected";}else{$selected2 = "selected";}
	$enable_app = $subscriber[0]['enable_app'];
	if($enable_app==1)$appselected1 = "selected"; else $appselected0 = "selected"; 
	$amc_status = $subscriber[0]['amc_status'];
	if($amc_status==1)$amcselected1 = "selected"; else $amcselected0 = "selected"; 
	$mobile = $subscriber[0]['mobile'];
	$sms = $subscriber[0]['sms'];
	if($sms==1)$smsselected1 = "selected"; else $smsselected0 = "selected";
	$latest_pkg = isset($latest_package) ? $latest_package : '';
	$newid = isset($subscriber[0]['newid']) ? $subscriber[0]['newid'] : '';
}
@endphp
<script>
	$( document ).ready(function() {
		$(".select2").select2();
		$("#area").change(function () {
			area_id = $('#area option:selected').val();
			getsubarealist(area_id);
			
		});

		areaid = $('#areaid').val();
		$('#area').val(areaid);
		$('#area').trigger('change');

		broadband_plan_id = $('#broadband_plan_id').val();
		if (broadband_plan_id != "") {
			$('#broadbandplan').val(broadband_plan_id).trigger('change');
		}

		combo_plan_id = $('#combo_plan_id').val();
		if (combo_plan_id != "") {
			$('#comboplan').val(combo_plan_id).trigger('change');
		}
	});
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
						sub_area_id = $('#sub_area_id').val();
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
	function updatecomplaint()
	{
		subscriberid = $('#subscriberid').val();
		mobile = $('#mobile').val();
		area = $('#area').val();
		subarea = $('#subarea').val();
		status = $('#status').val();
		appenable = $('#appenable').val();
		smsenable = $('#smsenable').val();
		broadbandplan = $('#broadbandplan').val();
		comboplan = $('#comboplan').val();
		amc_status = $('#amc_status').val();
		amcenddate = $('#amcenddate').val();
		lco_customer_id = $.trim($('#lco_customer_id').val());
		newid = $.trim($('#newid').val());

		if (lco_customer_id === '') {
			alertify.error('LCO Customer ID is required');
			return false;
		}
		if (newid === '') {
			alertify.error('NUID is required');
			return false;
		}

		$('body').addClass('loading');//agentid=0 admin
		alertify.confirm("Would you like to update this subscriber?", function (e) {
			if (e) {
				$.ajax({
					url: "{{ url('Subscriber') }}/update_subscriber",
					type: "POST",
					data: {'subscriberid' : subscriberid ,'mobile': mobile,'amc_status' : amc_status,'area' : area,'comboplan' : comboplan,'subarea' : subarea,'status' : status,'amcenddate' : amcenddate,'appenable' : appenable,'smsenable' : smsenable,'broadbandplan' : broadbandplan,'newid' : newid, 'lco_customer_id' : lco_customer_id},
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
.form-control[readonly] {
    background-color: #fff;
}
</style>
<div class="row">
<div class="col-md-6">
	<div class="box box-danger">
		<div class="box-header with-border" style="text-align: center;">
			<h3 class="box-title">Customer Details</h3>
			
		</div>
		<form class="form-horizontal">
			<input type="hidden" value="{{ $subscriberid }}" id="subscriberid" />
			<input type="hidden" value="{{ $area_id }}" id="areaid" />
			<input type="hidden" value="{{ $sub_area_id }}" id="sub_area_id" />
			<input type="hidden" value="{{ $broadband_plan }}" id="broadband_plan_id" />
			<input type="hidden" value="{{ $combo_plan }}" id="combo_plan_id" />
			<div class="box-body">
				<div class="form-group">
					<label class="col-sm-3 control-label">Customer Name</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ $customer_name }}" disabled />
					</div>
				</div>	
				<div class="form-group">
					<label for="lco_customer_id" class="col-sm-3 control-label">LCO Customer ID <span style="color:red;font-weight:bold;">*</span></label>
					<div class="col-sm-9">
						<input type="text" id="lco_customer_id" class="form-control" value="{{ $lco_customer_id }}" />
					</div>
				</div>
				<div class="form-group">
					<label for="mobile" class="col-sm-3 control-label">Mobile</label>
					<div class="col-sm-9">
						<input type="text" id="mobile" class="form-control" value="{{ $mobile }}" />
					</div>
				</div>
				<div class="form-group">
					<label for="newid" class="col-sm-3 control-label">NUID <span style="color:red;font-weight:bold;">*</span></label>
					<div class="col-sm-9">
						<input type="text" id="newid" class="form-control" value="{{ $newid }}" />
					</div>
				</div>
				<div class="form-group">
					<label for="area" class="col-sm-3 control-label">Area</label>
					<div class="col-sm-9">
						<select name="area" id="area" class="form-control select2">
							@php
								foreach($arealist as $row) {
									echo '<option value="'.e($row['id']).'">' .e($row['areaname']).'</option>';
								}
							@endphp
						</select>
					</div>
				</div>
				<div class="form-group">
					<label for="subarea" class="col-sm-3 control-label">Sub Area</label>
					<div class="col-sm-9">
						<select name="subarea" id="subarea" class="form-control select2">
							@php
								foreach($subarealist as $row) {
									echo '<option value="'.e($row['id']).'">' .e($row['areaname']).'</option>';
								}
							@endphp
						</select>
					</div>
				</div>
				<div class="form-group">
					<label for="status" class="col-sm-3 control-label">Status</label>
					<div class="col-sm-9">
						<select name="status" id="status" class="form-control">
							<option value="1" {{ $selected1 }}>Active</option>
							<option value="0" {{ $selected0 }}>Inactive</option>
							<option value="2" {{ $selected2 }}>Invisible</option>
						</select>
					</div>
				</div>
				<div class="form-group" hidden>
					<label for="appenable" class="col-sm-3 control-label">Enable APP</label>
					<div class="col-sm-9">
						<select name="appenable" id="appenable" class="form-control">
							<option value="1" {{ $appselected1 }}>Active</option>
							<option value="0" {{ $appselected0 }}>Inactive</option>
						</select>
					</div>
				</div>
				<div class="form-group">
					<label for="smsenable" class="col-sm-3 control-label">Enable SMS</label>
					<div class="col-sm-9">
						<select name="smsenable" id="smsenable" class="form-control">
							<option value="1" {{ $smsselected1 }}>Active</option>
							<option value="0" {{ $smsselected0 }}>Inactive</option>
						</select>
					</div>
				</div>
				<div class="form-group">
					<label for="amc_status" class="col-sm-3 control-label">AMC</label>
					<div class="col-sm-9">
						<select name="amc_status" id="amc_status" class="form-control">
							<option value="1" {{ $amcselected1 }}>Active</option>
							<option value="0" {{ $amcselected0 }}>Inactive</option>
						</select>
					</div>
				</div>
				<div class="form-group">
					<label for="amcenddate" class="col-sm-3 control-label">AMC End date</label>
					<div class="col-sm-9">
						<input type="date" value="{{ $amc_end_date }}" class="form-control" id="amcenddate">
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label">Active Package</label>
					<div class="col-sm-9">
						<input type="text" class="form-control" value="{{ !empty($latest_pkg) ? $latest_pkg : '-' }}" readonly />
					</div>
				</div>
				<div class="form-group">
					<label for="area" class="col-sm-3 control-label">Broadband plan</label>
					<div class="col-sm-9">
						<select name="broadbandplan" id="broadbandplan" class="form-control select2">
							<option value="">Select</option>
							@php
								foreach($broadplans as $row) {
									$selected = ($row['id'] == $broadband_plan) ? 'selected' : '';
									echo '<option value="'.e($row['id']).'" '.$selected.'>' .e($row['name']).'</option>';
								}
							@endphp
						</select>
					</div>
				</div>
				<div class="form-group">
					<label for="area" class="col-sm-3 control-label">Combo plan</label>
					<div class="col-sm-9">
						<select name="comboplan" id="comboplan" class="form-control select2">
							<option value="">Select</option>
							@php
								foreach($comboplan as $row) {
									$selected = ($row['id'] == $combo_plan) ? 'selected' : '';
									echo '<option value="'.e($row['id']).'" '.$selected.'>' .e($row['name']).'</option>';
								}
							@endphp
						</select>
					</div>
				</div>
				<div onClick="updatecomplaint();" class="box-footer btn-success" style="cursor:pointer;">
					<center><b>Save</b></center>
				</div>
			</div>
		</form>
	</div>
</div>
@endsection
