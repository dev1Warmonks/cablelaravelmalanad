@extends('admin.layout')

@section('content')
<div class="col-md-12" id="divremindersms">
	<div class="box box-info">
		<div class="box-header with-border">
			<h3 class="box-title">Import products</h3>
		</div>
		<form class="form-horizontal" action="import_products" method="post" name="frmExcelImport" id="frmExcelImport" enctype="multipart/form-data">
			@csrf
			<div class="box-body">
				<label>Choose Excel File</label><br><br>
				<input type="file" name="file" id="file" accept=".xls,.xlsx,.csv"><br>
            </div>
			<div class="box-footer">
				<!-- <div>
            		<a href="/images/product upload format.xlsx"><button class="btn btn-success pull-left">Download Format</button></a>
            	</div> -->
				<button type="submit" id="submit" name="import" class="btn btn-success pull-right">Import</button>
			</div>			
		</form>
	</div>
</div>

@endsection
