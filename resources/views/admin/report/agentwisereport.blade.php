@extends('admin.layout')

@section('content')
<script>
	$(function () {
		//Initialize Select2 Elements
		$(".select2").select2();
	});
	$( document ).ready(function() {
	});
	function agentWiseReport($from,$to)
	{
		$('#container').html("");
		$agentid = $("#ddagent option:selected").val(); 

		$("body").addClass("loading");
		$.ajax({
			url: "{{ url('report') }}/agent_report_data",
			type: "POST",
			data : {'agentid' : $agentid,'sfromdate' : $from, 'stodate' : $to},
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
	<div class="col-lg-4 col-md-2">
		<div class="form-group">
		<span>Agent</span>
			<select id="ddagent" name="ddagent" class="form-control select2">
				<option value="-1">ALL</option>
				<option value="0">ADMIN</option>
				@foreach($agent as $row)
					<option value="{{ $row['id'] }}">{{ $row['name'] }}</option>
				@endforeach
			</select>
			
		</div>
	</div>
	
	<div class="col-lg-2 col-md-2">
		<button type="button" style="margin-top: 20px;" id="searchagentreport" onClick="agentWiseReport($('#drsearchfromdate').val(), $('#drsearchtodate').val()); return false;" name="searchagentreport" class="form-control btn-warning">Search</button>
	</div>
</div>
<div id="container"></div>
@endsection
