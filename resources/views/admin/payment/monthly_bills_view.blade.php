<div class="table-responsive"><table class="table table-bordered">
<tr><td>Month</td><td>{{ date_create($bill->current_month)->format('F Y') }}</td></tr>
<tr><td>Invoice generated for</td><td>{{ $bill->invoice_generated }} Customers</td></tr>
<tr><td>Invoice total</td><td><span id="gsoft_invoice_total">{{ $bill->invoice_total }}</span></td></tr>
<tr><td>Status</td><td>{{ $status }}</td></tr>
</table>
<span style="display:none" id="bill_id">{{ $bill->id }}</span>
<span style="display:none" id="gsoft_invoice_current_month">{{ $bill->current_month }}</span>
</div>
