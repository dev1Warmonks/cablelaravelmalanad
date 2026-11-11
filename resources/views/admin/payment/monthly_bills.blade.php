@extends('admin.layout')

@section('content')
<script>
	$( document ).ready(function() {
		windowreloadClick();
		var modal = document.getElementById("myModal");
		var span = document.getElementsByClassName("close")[0];
		window.onclick = function(event) {
			if (event.target == modal) {
				modal.style.display = "none";
			}
		}
		span.onclick = function() {
			modal.style.display = "none";
			windowreloadClick();
		}
	});
	function windowreloadClick()
	{
		$('#container').html("");		
		$("body").addClass("loading");
		$sfromdate = $('#drsearchfromdate').val();
		$stodate = $('#drsearchtodate').val();
		$.ajax({
			url: "{{ url('Transaction') }}/monthly_bills_data",
			type: "POST",
			data: {'sfromdate': $sfromdate, 'stodate': $stodate},
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Reporting");
				$("#contentheader").text("Reporting");
				$("body").removeClass("loading");
			}
		});	
	}
	function paymentCompletedClick()
	{
		alertify.confirm('Please Confirm', 'Payment completed?', function()
		{
			$("body").addClass("loading");
			$current_month = $('#gsoft_invoice_current_month').text();
			$payment_type = $("#payment_type option:selected").val();
			$.ajax({
				url: "{{ url('Transaction') }}/initiate_payment",
				type: "POST",
				data: {'current_month': $current_month,'payment_type': $payment_type},
				success:function(result){ 
					alertify.alert().close(); 
					var modal = document.getElementById("myModal");
					modal.style.display = "none";
					windowreloadClick();
				}
			});
			 alertify.success('Payment initiated') 
		}
        , function()
		{ 
			alertify.error('Canceled')
		});
	}
	function payBill($current_month)
	{
		$.ajax({
			url: "{{ url('Transaction') }}/monthly_bills_view",
			type: "POST",
			data: {'current_month': $current_month},
			success:function(result){ 
				var modal = document.getElementById("myModal");
				modal.style.display = "block";
				$('#Bill_container').html(result);
				$('#payment_type').select2("val",$('#payment_type option:nth-child(2)').val());
				//alertify.alert(result).setHeader('Bill');				
			}
		});
	}
	$(function () {
		var $eventSelect = $(".select2");
		$eventSelect.select2();
		$eventSelect.on("select2:select", function (e) { 
			$payment_type = $("#payment_type option:selected").val();
			if($payment_type != 0)
			{
				$gsoft_invoice_total = $('#gsoft_invoice_total').text();
				$bill_id = $('#bill_id').text();
					$.ajax({
						url: "{{ url('Transaction') }}/payment_type_view",
						type: "POST",
						data: {'payment_type': $payment_type,'amount':$gsoft_invoice_total,'bill_id' : $bill_id},
						success:function(result)
						{
							if($payment_type == 4)
							{
								if(result.status == true)
								{
									window.open(result.payment_url, '_blank');
									// alertify.alert('<div class="table-responsive"><table class="table table-bordered"><tr><td>Payment type</td><td></td></tr><tr><td>URL</td><td><a href="" target="_blank"></a></td></tr></table></div>').setHeader('Pay');
              						location.reload();
								}
								else{
									alert(result.msg);
								}

							}
							else
							alertify.alert(result).setHeader('Pay');
						}
					});
			}
		});	
		//$(".select2").select2();
	});
</script>

<div class="row">
	<div class="col-md-12">
		<div class="box box-danger">
			<div class="box-header with-border" style="text-align: center;">
				<h3 class="box-title">Monthly bills</h3>
			</div>
		</div>
	</div>
	<div class="col-md-2">
		<div class="form-group">
			<span>From date</span>
			<input type="date" id="drsearchfromdate" value="{{ date("Y-m-01") }}" class="form-control"/>
		</div>
	</div>
	<div class="col-md-2">
		<div class="form-group">
			<span>To date</span>
			<input type="date" id="drsearchtodate" value="{{ date("Y-m-d") }}" class="form-control"/>
		</div>
	</div>
	<div class="col-md-2">
		<button type="button" style="margin-top: 20px;" onClick="windowreloadClick(); return false;"  class="form-control btn-warning">Search</button>
	</div>
</div>
<div id="container"></div>

<div id="myModal" class="modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content" style="width: 100%;">
			<div class="modal-header">
				<span class="close">&times;</span>
				<h2>Bill Pay</h2>
			</div>
			<div class="modal-body">
				<div id="Bill_container"></div>
				<div class="table-responsive">
					<table class="table table-bordered">
						<tr>
							<td>Payment type</td>
							<td>
								<select id="payment_type" class="form-control select2" style="width:200px;float:right">
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
</div>
@endsection
