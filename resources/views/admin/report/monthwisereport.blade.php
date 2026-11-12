@extends('admin.layout')

@section('content')
<script>
$( document ).ready(function() {
	dailyReportClick("{{ date("Y-m").'-01' }}","{{ date("Y-m-d") }}")
	});
	function dailyReportClick($from, $to)
	{
		$('#container').html("");
		paidstat = $("#paidstat").val();
		$("body").addClass("loading");
		$.ajax({
			url: "{{ url('report') }}/monthly_report_data",
			type: "POST",
			data : {'sfromdate' : $from, 'stodate' : $to, 'paidstat' : paidstat},
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Reporting");
				$("#contentheader").text("Reporting");
				$("body").removeClass("loading");
			}
		});	
	}
</script>
<div class="row">
	<div class="col-md-12">
		<div class="box box-danger">
			<div class="box-header with-border" style="text-align: center;">
				<h3 class="box-title">Monthly Report</h3>
			</div>
		</div>
	</div>
	<div class="col-md-2">
		<div class="form-group">
			<span>From date</span>
			<input type="date" id="drsearchfromdate" value="{{ date("Y-m").'-01' }}" class="form-control"/>
		</div>
	</div>
	<div class="col-md-2">
		<div class="form-group">
			<span>To date</span>
			<input type="date" id="drsearchtodate" value="{{ date("Y-m-d") }}" class="form-control"/>
		</div>
	</div>
	<div class="col-md-2">
		<div class="form-group">
			<span>This Month Paid Status</span>
			<select id="paidstat" class="form-control select2">
				<option value="-1">ALL</option>
				<option value="1">Paid</option>
				<option value="2">Non Paid</option>
			</select>
		</div>
	</div>
	<div class="col-md-2">
		<button type="button" style="margin-top: 20px;" onClick="dailyReportClick($('#drsearchfromdate').val(), $('#drsearchtodate').val()); return false;"  class="form-control btn-warning">Search</button>
	</div>
</div>
<!-- style="max-height:700px; overflow-y:scroll;" -->
<div id="container" ></div>
@endsection
