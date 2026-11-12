@extends('admin.layout')

@section('content')
<script>
	$(function () {
		//Initialize Select2 Elements
		$(".select2").select2();
	});
	function searchExpencesClick()
	{		
		expenseReportClick($('#drexpfromdate').val(), $('#drexptodate').val(), $('#ddexpenseheader option:selected').val());	
	}
	function expenseReportClick($from, $to, $header)
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "{{ url('report') }}/expense_report_data",
			type: "POST",
			data : {'sfromdate' : $from, 'stodate' : $to, 'header': $header},
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Reporting");
				$("#contentheader").text("Reporting");
				$("body").removeClass("loading");
			}
		});	
	}
	function fnExcelReport()
	{
		var tab_text = '<table border="1px" style="font-size:20px" ">';
		var textRange; 
		var j = 0;
		var tab = document.getElementById('exprpt'); // id of table
		var lines = tab.rows.length;

		// the first headline of the table
		if (lines > 0) {
			tab_text = tab_text + '<tr bgcolor="#DFDFDF">' + tab.rows[0].innerHTML + '</tr>';
		}

		// table data lines, loop starting from 1
		for (j = 1 ; j < lines; j++) {     
			tab_text = tab_text + "<tr>" + tab.rows[j].innerHTML + "</tr>";
		}

		tab_text = tab_text + "</table>";
		tab_text = tab_text.replace(/<A[^>]*>|<\/A>/g, "");             //remove if u want links in your table
		tab_text = tab_text.replace(/<img[^>]*>/gi,"");                 // remove if u want images in your table
		tab_text = tab_text.replace(/<input[^>]*>|<\/input>/gi, "");    // reomves input params
		// console.log(tab_text); // aktivate so see the result (press F12 in browser)

		var ua = window.navigator.userAgent;
		var msie = ua.indexOf("MSIE "); 

		 // if Internet Explorer
		if (msie > 0 || !!navigator.userAgent.match(/Trident.*rv\:11\./)) {
			txtArea1.document.open("txt/html","replace");
			txtArea1.document.write(tab_text);
			txtArea1.document.close();
			txtArea1.focus(); 
			sa = txtArea1.document.execCommand("SaveAs", true, "DataTableExport.xls");
		}  
		else // other browser not tested on IE 11
			sa = window.open('data:application/vnd.ms-excel,' + encodeURIComponent(tab_text));  

		return (sa);
	} 
</script>
<div class="row">
	<div class="col-lg-2 col-xs-2">
		<input type="date" name="drexpfromdate" id="drexpfromdate" value="{{ date("Y-m-d") }}" class="form-control"/>
	</div>	
	
	<div class="col-lg-2 col-xs-2">	
		<input type="date" name="drexptodate" id="drexptodate" value="{{ date("Y-m-d") }}" class="form-control"/>
	</div>
	<div class="col-lg-2 col-xs-2">	
		<select id="ddexpenseheader" name="ddexpenseheader" class="form-control select2">
			<option value="0">ALL</option>
			@foreach($expenseheader as $rows)
				<option value="{{ $rows['id'] }}">{{ $rows['title'] }}</option>
			@endforeach			
		</select>
	</div>
	<div class="col-lg-2 col-xs-2">
		<button onClick="searchExpencesClick(); return false;" type="button" id="drsearch" name="drsearch" class="form-control">Search</button>
	</div>
</div>
@endsection
