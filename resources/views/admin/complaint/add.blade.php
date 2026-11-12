@extends('admin.layout')

@section('content')
<script>
	$( document ).ready(function() {
		$(".select2").select2();
	});
function updatecomplaint()
	{
		description = $('#description').val();
		subscriber = $('#subscriber').val();
		complaint_type = $('#complaint_type').val();

		$('body').addClass('loading');//agentid=0 admin
		alertify.confirm("Would you like to add this compalaint?", function (e) {
			if (e) {
				$.ajax({
					url: "{{ url('Complaint') }}/add_complaint",
					type: "POST",
					data: {'description' : description ,'subscriber': subscriber,'complaint_type' : complaint_type},
					success:function(result){ 
						$('body').removeClass('loading');
						alertify.alert(result);
						location.reload();
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
<div class="col-md-12">
	<div class="box box-danger">
		<div class="box-header with-border" style="text-align: center;">
			<h3 class="box-title">Complaint Details</h3>
			<a style="float:left;font-weight: 700;" href="{{ url('complaint') }}">Back</a>
		</div>
		<form class="form-horizontal">
			<div class="box-body">
				<div class="form-group">
					<label for="subscriber" class="col-sm-3 control-label">Customer</label>
					<div class="col-sm-9">
						<select name="subscriber" id="subscriber" class="form-control select2">
							<option value="0">- Select -</option>
							@foreach($subscriber as $row)
								<option value="{{ $row['id'] }}">{{ $row['customer_name'] }} ({{ $row['mobile'] }}) {{ $row['lco_customer_id'] }}</option>
							@endforeach
						</select>
					</div>
				</div>
				<div class="form-group">
					<label for="complaint_type" class="col-sm-3 control-label">Complaints Nature</label>
					<div class="col-sm-9">
						<select name="complaint_type" id="complaint_type" class="form-control select2">
							<option value="0">- Select -</option>
							@foreach($complaint_types as $row)
								<option value="{{ $row['id'] }}">{{ $row['name'] }}</option>
							@endforeach
						</select>
					</div>
				</div>
				<div class="form-group">
					<label for="description" class="col-sm-3 control-label">Description</label>
					<div class="col-sm-9">
						<textarea class="form-control" id="description" rows="3"></textarea>
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
