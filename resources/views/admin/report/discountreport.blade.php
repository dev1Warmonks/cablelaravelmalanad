@extends('admin.layout')

@section('content')
<script>
	function discountReportClick($from, $to)
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "{{ url('report') }}/discount_report_data",
			type: "POST",
			data : {'sfromdate' : $from, 'stodate' : $to},
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
	<div class="col-lg-2 col-xs-2">
		<input type="date" name="drsearchfromdate" id="drsearchfromdate" value="{{ date("Y-m-d") }}" class="form-control"/>
	</div>	
	
	<div class="col-lg-2 col-xs-2">	
		<input type="date" name="drsearchtodate" id="drsearchtodate" value="{{ date("Y-m-d") }}" class="form-control"/>
	</div>
	
	<div class="col-lg-2 col-xs-2">
		<button onClick="discountReportClick($('#drsearchfromdate').val(), $('#drsearchtodate').val()); return false;" type="button" id="drsearch" name="drsearch" class="form-control">Search</button>
	</div>
</div>
@endsection
