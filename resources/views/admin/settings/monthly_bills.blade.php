@extends('admin.layout')

@section('content')
<script>
	$( document ).ready(function() {
		//dailyReportClick()
		var modal = document.getElementById("myModal");
		var span = document.getElementsByClassName("close")[0];
		window.onclick = function(event) {
			if (event.target == modal) {
				modal.style.display = "none";
			}
		}
		span.onclick = function() {
			modal.style.display = "none";
		}
		$("#user").change(function () {
			user_id = $('#user option:selected').val();
			getnetworklist(user_id);
		});
		$("#user").select2();
		$("#network").select2();
	});
	function NetworkwiseBillReport()
	{
		$user_id = $('#user option:selected').val();
		$network = $('#network option:selected').val();
		$status = $('#status option:selected').val();
		$('#container').html("");
		$("body").addClass("loading");
		$.ajax({
			url: "{{ url('AdminSettings') }}/monthly_bills_data",
			data: {'user_id': $user_id,'network': $network,'status': $status},
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				//$("#currentlocation").text("Reporting");
				//$("#contentheader").text("Reporting");
				$("body").removeClass("loading");
			}
		});	
	}
	function cashCollectedClick()
	{
		alertify.confirm('Please Confirm', 'Payment completed?', function()
		{
			$("body").addClass("loading");
			$current_month = $('#gsoft_invoice_current_month').text();
			$payment_type = $("#payment_type option:selected").val();
			$network = $("#currentnetwork").text();
			$.ajax({
				url: "{{ url('AdminSettings') }}/complete_payment",
				type: "POST",
				data: {'current_month': $current_month, 'payment_type': $payment_type, 'network': $network},
				success:function(result){ 
					alertify.alert().close(); 
					var modal = document.getElementById("myModal");
					modal.style.display = "none";
					windowreloadClick();
				}
			}).setting({'label':'Agree'});
			// alertify.success('Payment completed') 
		}
        , function()
		{ 
			alertify.error('Canceled')
		});
	}
	function windowreloadClick()
	{
		location.reload();
	}
	function payBill($current_month,$network)
	{
		$.ajax({
			url: "{{ url('AdminSettings') }}/monthly_bills_view",
			type: "POST",
			data: {'current_month': $current_month,'network':$network},
			success:function(result){ 
				var modal = document.getElementById("myModal");
				modal.style.display = "block";
				$('#Bill_container').html(result);
				selected_payment_type = $('#selected_payment_type').text();
				$('#payment_type').val(selected_payment_type).change();
				//alertify.alert(result).setHeader('Bill');				
			}
		});
	}
	function generatePendingBillClick()
	{
		$("body").addClass("loading");
		$.ajax({
			url: "{{ url('AdminSettings') }}/generate_monthly_bills",
			type: "POST",
			success:function(result){ 
				alertify.alert(result).setHeader('Invoice');
				$("body").removeClass("loading");
			}
		});
	}
	$(function () {
		var $eventSelect = $("#payment_type");
		$network = $('#network option:selected').val();
		$eventSelect.select2();
		$eventSelect.on("select2:select", function (e) { 
			$payment_type = $("#payment_type option:selected").val();
			$gsoft_invoice_total = $('#gsoft_invoice_total').text();
				$.ajax({
					url: "{{ url('AdminSettings') }}/payment_type_view",
					type: "POST",
					data: {'payment_type': $payment_type,'network':$network,'amount':$gsoft_invoice_total},
					success:function(result){ 
						alertify.alert(result).setHeader('Pay');
					}
				});
			});
		
	});
	function getnetworklist(user_id)
	{
		if(user_id !== undefined)
		{
			$('#network').empty();
			$.ajax({
				url: "{{ url('AdminSettings') }}/gsoft_network_list",
				type: "POST",
				dataType: "json",
				data: {'user_id': user_id},
				success:function(result)
				{
					if(result.success == true)
					{
						var data = result.data;
						var newOption = new Option('ALL NETWORK', -1, false, false);
						$('#network').append(newOption).trigger('change');
						for (var i in data)
						{
							if (data[i].hasOwnProperty("lcoCode") && data[i].hasOwnProperty("network_name")) 
							{
								id = data[i].lcoCode;
								network_name = data[i].network_name;
								var networkdata = {
									id: id,
									text: network_name
								};
								var newOption = new Option(networkdata.text, networkdata.id, false, false);
								$('#network').append(newOption).trigger('change');
							}
						}
						network_id = -1;
						$('#network').val(network_id);
						$('#network').trigger('change');
					}
				},
				error:function(result){ 
					alertify.error('Failed to fetch Network list');					
				}
			});
		}
	}
</script>

<div class="row">
	<div class="col-md-12">
		<div class="box box-danger">
			<div class="box-header with-border" style="text-align: center;">
				<h3 class="box-title">Monthly bills</h3>
			</div>
		</div>
	</div>
	<div class="col-lg-3 col-md-2">
		<div class="form-group">
		<span>Users</span>
			<select id="user" name="user" class="form-control select2">
				<option value="-1">ALL</option>
				@php
					foreach($users_list as $row) {
						echo '<option value="' . e($row['id']) . '">' . e(strtoupper($row['network_name'])) . '</option>';
					}
				@endphp
			</select>
			
		</div>
	</div>
	<div class="col-lg-3 col-md-2">
		<div class="form-group">
		<span>Network</span>
			<select id="network" name="network" class="form-control select2">
				<option value="-1">ALL NETWORK</option>
				@php
					foreach($networks as $row) {
						echo '<option value="' . e($row['lcoCode']) . '">' . e(strtoupper($row['network_name'])) . '</option>';
					}
				@endphp
			</select>
		</div>
	</div>
	<div class="col-lg-2 col-md-2">
		<div class="form-group">
			<span>Status</span>
			<select id="status" class="form-control select2">
				<option value="-1">ALL</option>
				<option value="0">Pending</option>
				<option value="1">Initiated</option>
				<option value="2">Successfully paid</option>
				<option value="3">Failed</option>
				<option value="4">Free</option>
			</select>
		</div>
	</div>
	<div class="col-lg-1 col-md-2">
		<div class="form-group">
			<button type="button" style="margin-top: 20px;" id="searchnetworkwisebillreport" onClick="NetworkwiseBillReport(); return false;" name="searchnetworkwisebillreport" class="form-control btn-warning">Search</button>
		</div>
	</div>
	<div class="col-lg-1 col-xs-2">
	</div>
	<div class="col-lg-2 col-md-2">
		<div class="form-group">
			<button type="button" style="margin-top: 20px;" onClick="generatePendingBillClick();return false;" class="form-control btn-warning">Generate pending bills</button>
		</div>
	</div>
</div>
<div style="overflow-x:auto;">
	<div id="container"></div>
</div>

<div id="myModal" class="modal">
  	<div class="modal-content">
		<div class="modal-header">
			<span class="close">&times;</span>
			<h2>Bill Pay</h2>
		</div>
		<div class="modal-body">
			<div id="Bill_container"></div>
			<div class="table-responsive">
				<table class="table table-bordered">
					<tr>
						<td>Select payment type</td>
						<td>
							<select id="payment_type" class="form-control select2" style="width:200px;float:right">
								<option value="-1" selected>Select</option>
								<option value="0">Cash</option>
								<option value="-2">Free</option>
								@php 
								if(count($payment_type_list) != 0)
								{
									foreach($payment_type_list as $row) { @endphp
									<option value="{{ $row['id'] }}">{{ $row['name'] }}</option>
								@php }
								}
								@endphp
							</select>
						</td>
					</tr>
				</table>
			</div>
			<!-- <div class="row">
				<div class="col-md-2">
					<div class="form-group">
						<span>Area</span>
						<select id="area" class="form-control select2">
							<option value="-1">Select</option>
							@php 
								if(count($payment_type_list) != 0)
								{
									foreach($payment_type_list as $row) { @endphp
									<option value="{{ $row['id'] }}">{{ $row['name'] }}</option>
								@php }
								}
								@endphp
						</select>
					</div>
				</div>
			</div> -->
		</div>
		<div class="modal-footer">
			<h3></h3>
		</div>
  	</div>
</div>
<!-- <img src="https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=upi%3A%2F%2Fpay%3Fpa%3Dshanjalal%40okhdfcbank%26pn%3DShanavas%2520Jalal%26aid%3DuGICAgIDA-4qKFQ" title="Link to Google.com" /> -->
@endsection
