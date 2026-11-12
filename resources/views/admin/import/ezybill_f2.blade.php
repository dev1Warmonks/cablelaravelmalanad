@extends('admin.layout')

@section('content')
<script>
	function updatedatabase()
	{
		$('body').addClass('loading');//agentid=0 admin
		alertify.confirm("Would you like to update database?", function (e) {
			if (e) {
				$.ajax({
					url: "update_database",
					type: "POST",
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
<div class="col-md-12" id="divremindersms">
	<div class="box box-info">
		<div class="box-header with-border">
			<h3 class="box-title">Import Subscriber From Customer List (Format 2)</h3>
		</div>
		<form class="form-horizontal" action="import_suscriberfromezybill_f2" method="post" name="frmExcelImport" id="frmExcelImport" enctype="multipart/form-data">
			@csrf
			<div class="box-body">
				<label>Choose Excel File</label><br><br>
				<input type="file" name="file" id="file" accept=".xls,.xlsx,.csv"><br>
            </div>
			<div class="box-footer">
				<div>
            		<a class="btn btn-success pull-left" target="_blank" href="{{ url('assets') }}/uploads/formats/customer-list-format.csv">Download Format</a>
            	</div>
				<button type="submit" id="submit" name="import" class="btn btn-success pull-right">Import</button>
			</div>			
		</form>
		<div onClick="updatedatabase();" class="box-footer btn-success" style="cursor:pointer;">
			<center><b>Update Database</b></center>
		</div>
	</div>
</div>

@endsection
