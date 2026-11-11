@extends('admin.layout')

@section('content')
<script>
	$(function () {
		$(".select2").select2();
	});
	$( document ).ready(function() {
		$("#searchagentreport").click(function(){
			$subscriberid = $("#ddagent option:selected").val(); 			
			getPaymentHistory($subscriberid);
		});
	});

	function getPaymentHistory($subscriberid)
	{
		$.ajax({
			url: "{{ url('Transaction') }}/ledger_result",
			type: "POST",
			data: {'subscriberid': $subscriberid},
			success:function(result)
			{
				$('#container').html(result);
			},
			error:function(result){ 
				alertify.error('Failed to fetch PaymentHistory');					
			}
		});
	}
	function deleteBillClick($voucherid)
	{
		alertify.confirm("Would u like to delete this bill?", function (e) { 
			if (e) {
				$.ajax({
					url: "{{ url('Transaction') }}/delete_voucher",
					type: "POST",
					data: {'voucherid': $voucherid},
					success:function(result)
					{
						alertify.alert(result);
						$subscriberid = $("#ddagent option:selected").val(); 			
						getPaymentHistory($subscriberid);
					},
					error:function(result){ 
						alertify.error('Failed').setHeader('Message');					
					}
				});
				//alert($voucherid);
				//generateBill();
			} else {
				alertify.error("Operation Cancelled").setHeader('Message');
			}
		}).setHeader('Confirm');		
	}
</script>
<div class="row">
			
	<div class="col-lg-4 col-md-12">
		<div class="form-group">
			<select id="ddagent" name="ddagent" class="form-control select2">
				<option value="-1">-SELECT CUSTOMER-</option>
				@php
						foreach($customer as $row) {
							echo '<option value="'.e($row['id']).'">' .e($row['customer_name']) . ' (' . e($row['mobile']) .') '.e($row['lco_customer_id']).'</option>';
						}
					@endphp
			</select>
			
		</div>
	</div>
	
	<div class="col-lg-2 col-md-12">
		<button type="button" id="searchagentreport" name="searchagentreport" class="form-control btn-warning">Search</button>
	</div>
</div>
<div id="container"></div>
@endsection
