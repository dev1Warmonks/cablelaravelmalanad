@extends('admin.layout')

@section('content')
<script>
	$( document ).ready(function() {
		$('#container').html("");
		$("body").addClass("loading");
		$.ajax({
			url: "{{ url('AdminSettings/') }}/database_list",
			type: "POST",
			success:function(result){
				$('#container').html(result);
				$("body").removeClass("loading");
			}
		});
	});
	function generateBackup()
	{
		$("body").addClass("loading");
		$.ajax({
			url: "{{ url('AdminSettings/') }}/generateBackup",
			type: "POST",
			success:function(result){
				alert(result);
				location.reload();
			}
		});
	}
	function deleteBackup(name)
	{
		alertify.confirm("Would you like to delete this backup?", function (e) {
		if (e) {
			$("body").addClass("loading");
			$.ajax({
				url: "{{ url('AdminSettings/') }}/deleteBackup/"+name,
				type: "POST",
				success:function(result){
					alert(result);
					location.reload();
				}
			});
		}
		});
	}
</script>
<div style="overflow-x:auto;">
	<div class="col-lg-2 col-md-2">
		<div class="form-group">
			<button type="button" style="margin-top: 20px;" onClick="generateBackup();return false;" class="form-control btn-warning">Generate Backup</button>
		</div>
	</div>
	<div id="container"></div>
</div>
@endsection
