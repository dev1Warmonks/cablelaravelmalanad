@extends('agent.layout')

@section('content')
<script>
	$( document ).ready(function() {
		dailyReportClick("{{ date("Y-m-d") }}","{{ date("Y-m-d") }}")
	});
	function dailyReportClick($from, $to)
	{
		$('#container').html("");		
		$("body").addClass("loading");
		$.ajax({
			url: "{{ url('agent') }}/date_wise_report_data",
			type: "POST",
			data : {'sfromdate' : $from, 'stodate' : $to},
			success:function(result){ 
				$('#container').html(result);
				$('#container2').html("");
				$("#historyhead").text("");
				$("#currentlocation").text("Reporting");
				$("#contentheader").text("Reporting");
				$("body").removeClass("loading");
			}
		});	
	}
	function viewDateReport($fdate, $tdate)
	{
		$("body").addClass("loading");
		$.ajax({
			url: "{{ url('agent') }}/daily_report_data",
			type: "POST",
			data : {'sfromdate' : $fdate, 'stodate' : $tdate},
			success:function(result){ 
				$('#container2').html(result);
				//$("#currentlocation").text("Reporting");
				$("#historyhead").text("Transaction history of "+$fdate);
				$("body").removeClass("loading");
			}
		});	
	}
</script>

<div class="row">
	<div class="col-md-12">
		<div class="box box-danger">
			<div class="box-header with-border" style="text-align: center;">
				<h3 class="box-title">Daily report</h3>
			</div>
		</div>
	</div>
	<div class="col-md-2">
		<div class="form-group">
			<span>From date</span>
			<input type="date" id="drsearchfromdate" value="{{ date("Y-m-d") }}" class="form-control"/>
		</div>
	</div>
	<div class="col-md-2">
		<div class="form-group">
			<span>To date</span>
			<input type="date" id="drsearchtodate" value="{{ date("Y-m-d") }}" class="form-control"/>
		</div>
	</div>
	<div class="col-md-2">
		<button type="button" style="margin-top: 20px;" onClick="dailyReportClick($('#drsearchfromdate').val(), $('#drsearchtodate').val()); return false;"  class="form-control btn-warning">Search</button>
	</div>
</div>
<div id="container"></div>
<center><b><h4 id="historyhead"></h4></b></center>
<div id="container2"></div>
@endsection
