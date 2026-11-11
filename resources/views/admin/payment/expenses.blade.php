@extends('admin.layout')

@section('content')
<script>
$( document ).ready(function() {
		$(".select2").select2();
});
	function addExpensetoDB()
	{
		$expenseheadid = $("#ddexpensehead option:selected").val(); 
		$expensedate = $('#expensedate').val();
		$expenseamount = $('#expenseamount').val();
		$expensecomment = $('#expensecomment').val();
		if($expensedate.length == '' || $expenseamount == '' || $expenseheadid == 0)
		{
			alertify.error('Fill necessary details');
			return;
		}
		$('body').addClass('loading');
		alertify.confirm("Would you like to add this expense?", function (e) {
			if (e) {
				$.ajax({
					url: "{{ url('Transaction') }}/expenses_add",
					type: "POST",
					data: {'expenseheadid' : $expenseheadid ,'expensedate': $expensedate, 'expenseamount': $expenseamount, 
					'expensecomment': $expensecomment},
					success:function(result){ 
						alertify.alert(result).setHeader('Expense');
						$('#expenseamount').val("");
						$('#expensecomment').val("");
						
						$('body').removeClass('loading');
						location.reload();
						//addExpenses();
						
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
		}).set('oncancel', function(closeEvent){$('body').removeClass('loading');}).setHeader('Expense');
	}
</script>
<script>
	$(function () {
	$("#example2").DataTable({
		"paging": true,
		"lengthChange": true,
		"dom": "Bfrtip",
		"searching": true,
		"ordering": true,
		"scrollX": true,
		"info": true,
		"autoWidth": true,	  
		"order": [[ 0, "asc" ]],
		"buttons": [ {	extend:'pdfHtml5',
        text:'Export PDF',
        orientation:'landscape',
        customize : function(doc){
            var colCount = new Array();
            $("#example2").find('tbody tr:first-child td').each(function(){
                if($(this).attr('colspan')){
                    for(var i=1;i<=$(this).attr('colspan');$i++){
                        colCount.push('*');
                    }
                }else{ colCount.push('*'); }
            });
            doc.content[1].table.widths = colCount;
        }}, {
			extend: "print",
			text: "Print",
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
<div class="row">
	<div class="col-md-5">
		<div class="box box-danger">
			<div class="box-header with-border">
				<h3 class="box-title">Add Expenses</h3>
			</div>
			<form class="form-horizontal">
				<div class="box-body">
					<div class="box-body no-padding">
						<div class="form-group">
							<label for="expensedate" class="col-sm-3 control-label">Date</label>

							<div class="col-sm-9">
								<input type="date" value="{{ date("Y-m-d") }}" class="form-control" id="expensedate" />
							</div>
						</div>	
					</div>
					<div class="form-group">
						<label for="expenseamount" class="col-sm-3 control-label">Head</label>
						<div class="col-sm-9">
							<select id="ddexpensehead" name="ddexpensehead" class="form-control select2">
							<option value="0">-SELECT EXPENSE HEAD-</option>
							@php
								foreach($expenseheader as $rows) {
									echo '<option value="' . e($rows['id']) . '">' . e($rows['title']) . '</option>';
								}
							@endphp	
							</select>
						</div>
					</div>											
					<div class="form-group">
						<label for="expenseamount" class="col-sm-3 control-label">Amount</label>

						<div class="col-sm-9">
							<input type="text" onkeypress='return event.charCode >= 48 && event.charCode <= 57' class="form-control" id="expenseamount" placeholder="Expense Amount"/>
						</div>
					</div>					
					<div class="box-body no-padding">
						<div class="form-group">
							<label for="expensecomment" class="col-sm-3 control-label">Comments</label>
							<div class="col-sm-9">
								<textarea class="form-control" id="expensecomment" placeholder="Comment" rows="3"></textarea>
							</div>
						</div>	
					</div>
				</div>
				<div class="box-footer">
					<button type="button" onClick="addExpensetoDB();" id="submitexpensecreate" name="submitexpensecreate" value="submitexpensecreate" class="btn btn-danger pull-right">Create</button>
				</div>
			</form>
		</div>
	</div>
	<div class="col-md-7">
		<div class="box box-danger">
			<div class="box-header with-border">
				<h3 class="box-title">Expenses</h3>
			</div>
			<div class="box-body no-padding" style="height:700px; overflow-y:auto;">
			<table id="example2" class="table table-bordered table-striped table-sm" cellspacing="0" width="100%">
					<thead>
						<tr class="bg-navy">	
							<th>Date</th>
							<th>Title</th>
							<th>Amount</th>
							<th>Remarks</th>
						</tr>		
					</thead>
					<tbody>		
						@php
						$total_expense = 0;
						//$count = $expenseheader->num_rows();
						//print_r($expensedata);
						if (! function_exists('formatTimeDMY')) {
							function formatTimeDMY($ydatetime) {
								return date('d/m/Y', $ydatetime/1000);
							}
						}
						if(!empty($expensedata))
						{
							foreach($expensedata as $rows) {
								echo '<tr>';
								echo '<td>' . e($rows['expensedate']). '</td>';
								echo '<td>' . e($rows['title']) . '</td>';									
								echo '<td>' . e($rows['amount']) . '</td>';
								echo '<td>' . e($rows['remarks']) . '</td>';
								echo '</tr>';
								$total_expense = $total_expense + $rows['amount'];
							}
							
						}
							// echo '<tr>';
							// echo '<td colspan="4"> <center>No records found </center></td>';
							// echo '</tr>';
						@endphp
					</tbody>
					<tr class="bg-navy">
						<td>Total</td>
						<td></td>
						<td>{{ $total_expense }}</td>
						<td></td>
					</tr>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection
