@extends('admin.layout')

@section('content')
@php
	$remindersms = ''; $percustomeramount = ''; $blocknetworkMSG = ''; $DefaultNotification = '';
	foreach ($settings as $row) {
		switch ($row['field']) {
			case 'remindersms': $remindersms = $row['value']; break;
			case 'percustomeramount': $percustomeramount = $row['value']; break;
			case 'blocknetworkMSG': $blocknetworkMSG = $row['value']; break;
			case 'DefaultNotification': $DefaultNotification = $row['value']; break;
		}
	}
@endphp
<script type = "text/javascript">
	$(function () {
		//Initialize Select2 Elements
		$(".select2").select2();
	});
	function sendRemainderSMS()
	{
		$remindersms = $('#remindersms').val();

		alertify.confirm("Update SMS format?", function (e) {
			if (e) {
				$.ajax({
					url: "settings_save",
					type: "POST",
					data: {'field':'remindersms','value': $remindersms},
					success:function(result){
						alertify.alert(result);
					}
				});
			} else {
				alertify.error("Operation Cancelled");
			}
		});
	}
	function UpdateblocknetworkMSG()
	{
		$blocknetworkMSG = $('#blocknetworkMSG').val();
		alertify.confirm("Update Blocked Network Notification?", function (e) {
			if (e) {
				$.ajax({
					url: "settings_save",
					type: "POST",
					data: {'field':'blocknetworkMSG','value': $blocknetworkMSG},
					success:function(result)
					{
						alertify.alert(result);
					}
				});
			} else {
				alertify.error("Operation Cancelled");
			}
		});
	}
	function UpdateDefaultNotification()
	{
		$DefaultNotification = $('#DefaultNotification').val();
		alertify.confirm("Update Default Notification?", function (e) {
			if (e) {
				$.ajax({
					url: "settings_save",
					type: "POST",
					data: {'field':'DefaultNotification','value': $DefaultNotification},
					success:function(result)
					{
						alertify.alert(result);
					}
				});
			} else {
				alertify.error("Operation Cancelled");
			}
		});
	}
	function Updatepercustomeramount()
	{
		$percustomeramount = $('#percustomeramount').val();
		alertify.confirm("Update Per customer amount?", function (e) {
			if (e) {
				$.ajax({
					url: "settings_save",
					type: "POST",
					data: {'field':'percustomeramount','value': $percustomeramount},
					success:function(result){
						alertify.alert(result);
					}
				});
			} else {
				alertify.error("Operation Cancelled");
			}
		});
	}
	function RunScript()
	{
		ScriptNetwork = $("#ScriptNetwork option:selected").val();
		alertify.confirm("Run Script?", function (e) {
			if (e) {
				$.ajax({
					url: "{{ url('AdminSettings/') }}/scripts/"+ScriptNetwork,
					type: "POST",   // was GET: it changes data, so it is POST + CSRF now
					success:function(result){
						alertify.alert(result);
					}
				});
			} else {
				alertify.error("Operation Cancelled");
			}
		});
	}
	function sendSMS()
	{
		$subscribersms = $('#subscribersms').val();
		$subscriberid = $('#ddsubscriber option:selected').val();

		alertify.confirm("Send SMS to the user?", function (e) {
			if (e) {
				$.ajax({
					url: "ws_sendsms.php",
					type: "POST",
					data: {'subscriberid': $subscriberid, 'subscribersms': $subscribersms},
					success:function(result){
						alertify.alert(result);
					}
				});
			} else {
				alertify.error("Operation Cancelled");
			}
		});
	}
</script>
<div class="row">
	<div class="col-md-12">
		<div class="col-md-4" id="divremindersms">
			<div class="box box-info">
				<div class="box-header with-border">
					<h3 class="box-title">Reminder SMS</h3>
				</div>
				<form class="form-horizontal">
					<div class="box-body">
						<div class="form-group">
							<div class="col-sm-12">
								<label id="lblremindersms" for="remindersms"></label>
							</div>
							<div class="col-sm-12">
								<textarea name="remindersms" id="remindersms" rows="4" placeholder="Reminder SMS" data-clear-btn="true" class="form-control">{{ $remindersms }}</textarea>
							</div>
						</div>
					</div>
					<div class="box-footer">
						<button type="button" onClick="sendRemainderSMS(); return false;" class="btn btn-success pull-right">Update</button>
					</div>
				</form>
			</div>
		</div>
		<div class="col-md-4" id="divremindersms" >
			<div class="box box-info">
				<div class="box-header with-border">
					<h3 class="box-title">Script Run</h3>
				</div>
				<form class="form-horizontal">
					<select id="ScriptNetwork" class="form-control select2">
						<option value="">ALL Network</option>
						@foreach($all_network_list as $row)
							<option value="{{ $row['lcoCode'] }}">{{ $row['network_name'] }}</option>
						@endforeach
					</select>

					<div class="box-footer">
						<button type="button" onClick="RunScript(); return false;" class="btn btn-success pull-right">Run Script</button>
					</div>
				</form>
			</div>
		</div>
		<div class="col-md-4">
			<div class="box box-info">
				<div class="box-header with-border">
					<h3 class="box-title">Blocked Network Notification</h3>
				</div>
				<form class="form-horizontal">
					<div class="box-body">
						<div class="form-group">
							<div class="col-sm-12">
								<label for="blocknetworkMSG"></label>
							</div>
							<div class="col-sm-12">
								<textarea name="blocknetworkMSG" id="blocknetworkMSG" rows="4" placeholder="Blocked Network Notification" data-clear-btn="true" class="form-control">{{ $blocknetworkMSG }}</textarea>
							</div>
						</div>
					</div>

					<div class="box-footer">
						<button type="button" onClick="UpdateblocknetworkMSG(); return false;" class="btn btn-success pull-right">Update</button>
					</div>
				</form>
			</div>
		</div>
		<div class="col-md-4">
			<div class="box box-info">
				<div class="box-header with-border">
					<h3 class="box-title">Default Notification</h3>
				</div>
				<form class="form-horizontal">
					<div class="box-body">
						<div class="form-group">
							<div class="col-sm-12">
								<label for="DefaultNotification"></label>
							</div>
							<div class="col-sm-12">
								<textarea name="DefaultNotification" id="DefaultNotification" rows="4" placeholder="Default Notification" data-clear-btn="true" class="form-control">{{ $DefaultNotification }}</textarea>
							</div>
						</div>
					</div>

					<div class="box-footer">
						<button type="button" onClick="UpdateDefaultNotification(); return false;" class="btn btn-success pull-right">Update</button>
					</div>
				</form>
			</div>
		</div>
		<div class="col-md-4" id="divremindersms">
			<div class="box box-info">
				<div class="box-header with-border">
					<h3 class="box-title">Per Customer Amount</h3>
				</div>
				<form class="form-horizontal">
					<div class="box-body">
						<div class="form-group">
							<div class="col-sm-12">
								<label for="percustomeramount"></label>
							</div>
							<div class="col-sm-12">
								<input type="text" name="percustomeramount" id="percustomeramount" rows="4" placeholder="Amount" data-clear-btn="true" class="form-control" value="{{ $percustomeramount }}">
							</div>
						</div>
					</div>

					<div class="box-footer">
						<button type="button" onClick="Updatepercustomeramount(); return false;" class="btn btn-success pull-right">Update</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection
