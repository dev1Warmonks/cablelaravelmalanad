<script>
	$(function () {
	$("#history1").DataTable();
	$("#example2").DataTable({
		"paging": true,
		"dom": "Bfrtip",
		"lengthChange": true,
		"searching": true,
		"ordering": false,
		"info": true,
		"scrollX": true,
		"autoWidth": true,
		"order": [[ 0, "asc" ]],
		"buttons": [ {
			extend: "pdfHtml5",
			orientation: "landscape",
			pageSize: "LEGAL",
			text:"Export PDF",
			title: "",
			customize : function(doc){
				var colCount = new Array();
				$("#example2").find("tbody tr:first-child td").each(function(){
					if($(this).attr("colspan")){
						for(var i=1;i<=$(this).attr("colspan");i++){
							colCount.push("*");
						}
					}else{ colCount.push("*"); }
				});
				doc.content[1].table.widths = colCount;
			}
		},
		{
			extend: "print",
			text: "Print",
			title: "",
			exportOptions: {
				modifier: {
					page: "all"
				}
			}
		},
		"copy", "csv", "excel"],
	});
	});
</script>
<div class="box">
	<div class="box-header">
		<h3 class="box-title">Payments</h3>
	</div>
	<!-- /.box-header -->
	<div style="overflow-x:auto;">
	<div class="box-body">
		<table id="example2" class="table table-bordered table-striped table-hover">
			<thead>
				<tr>
					<td>MONTH</td>
					<td>FOR THE MONTH</td>
					<td>NOTE</td>
					<td>SUBSCHRGE</td>
					<td>BILL TOTAL</td>
					<td>PAID AMT</td>
					<td>DISC</td>
					<td>BALANCE</td>
					<td>RECPT NO</td>
					<td>DATE</td>
					@if($canDelete)<td>ACTION</td>@endif
				</tr>
			</thead>
			<tbody>
			@foreach($rows as $row)
				@php
					$seconds = (int) ($row->timestamp / 1000);
					$forMonth = date('M Y', strtotime(date('M Y', $seconds).' -1 month'));
				@endphp
				<tr>
					<td>{{ date('M Y', $seconds) }}</td>
					<td>{{ $forMonth }}</td>
					<td>{{ $row->description }}</td>
					<td>{{ $row->subscriptioncharge }}</td>
					<td>{{ $row->dueamount }}</td>
					<td>{{ $row->paidamount }}</td>
					<td>{{ $row->discount }}</td>
					<td>{{ $row->dbafterdiscount }}</td>
					<td>{{ $row->id }}</td>
					<td>{{ date('d/m/Y H:i', $seconds) }}</td>
					@if($canDelete)
						@if($loop->first)
						<td style="text-align:center"><img style="cursor:pointer;" src="{{ url('assets/img/delete.jpg') }}" onclick="deleteBillClick({{ $row->id }})"></td>
						@else
						<td></td>
						@endif
					@endif
				</tr>
			@endforeach
			</tbody>
		</table>
	</div>
	</div>
</div>
