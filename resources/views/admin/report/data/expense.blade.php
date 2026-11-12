<div class="row">
<br/><center><h4><b>Displaying results from {{ $first }} to {{ $last }}</b></h4></center>
<div class="col-md-8" id="expensereport"><table id="exprpt" class="TFtable table table-striped table-bordered" width="100%">
<tr><td colspan="4"><input type="button" onclick=" fnExcelReport();" value="Export to Excel"/></td></tr>
<tr class="bg-navy">
<td>Date</td><td>Expense</td><td>Expense #</td><td>Expense Amt</td>
</tr>
@foreach($days as $d)
<tr>
<td >{{ $d['ymd'] }}</td><td></td><td></td><td></td>
</tr>
@foreach($d['rows'] as $r)
<tr>
<td></td>
<td>{{ $r['title'] }}</td>
<td>{{ $r['expcount'] }}</td>
<td>{{ $r['expamount'] }}</td>
</tr>
@endforeach
@endforeach
</table></div>
<div class="col-md-4" id="expconsolidatedrep"><table class="TFtable table table-striped table-bordered" width="100%">
<tr class="bg-navy">
<td colspan="3"><center>Consolidated Report ({{ $consolidatedTitle }})</center></td>
</tr>
<tr class="bg-navy">
<td>Expense Head</td><td>Expense #</td><td>Expense Amt</td>
</tr>
@foreach($heads as $r)
<tr>
<td>{{ $r['title'] }}</td>
<td>{{ $r['expcount'] }}</td>
<td>{{ $r['expamount'] }}</td>
</tr>
@endforeach
<tr class="bg-navy"><td colspan="2"><center><b>Total</b></center></td><td><b>{{ $totalExpense }}</b></td></tr>
</table><table class="TFtable table table-striped table-bordered" width="100%">
<tr class="bg-navy"><td>Collection</td><td>Expenses</td><td>Profit</td></tr>
<tr>
<td><center><b>{{ $collection }}</b></center></td>
<td><center><b>{{ $totalExpense }}</b></center></td>
<td><center><b>{{ $profit }}</b></center></td>
</tr>
</table>
</div>
</div>
