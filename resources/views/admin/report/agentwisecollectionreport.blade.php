@extends('admin.layout')

@section('content')
<script>
	function agentwiseCollectionClick($from, $to)
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "{{ url('report') }}/agent_wise_collection_report_data",
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
	<div class="col-lg-2 col-md-2">
		<div class="form-group">
			<input type="date" name="drsearchfromdate" id="drsearchfromdate" value="{{ date("Y-m-d") }}" class="form-control"/>
		</div>
	</div>	
	<div class="col-lg-2 col-md-2">	
		<div class="form-group">
			<input type="date" name="drsearchtodate" id="drsearchtodate" value="{{ date("Y-m-d") }}" class="form-control"/>
		</div>
	</div>
	<div class="col-lg-2 col-md-2">
		<div class="form-group">
			<button onClick="agentwiseCollectionClick($('#drsearchfromdate').val(), $('#drsearchtodate').val()); return false;" type="button" id="drsearch" name="drsearch" class="box-footer btn-success form-control" style="cursor:pointer;">Search</button>
		</div>
	</div>
</div>
@endsection
