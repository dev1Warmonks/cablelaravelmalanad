<div class="table-responsive">
<table class="table table-bordered">
<tr><td>Month</td><td>{{ date_create($bill->current_month)->format('F Y') }}</td></tr>
<tr><td>Invoice generated for</td><td>{{ $bill->invoice_generated }} Customers</td></tr>
<tr><td>Invoice total</td><td><span id="gsoft_invoice_total">{{ $bill->invoice_total }}</span></td></tr>
<tr><td>Status</td><td>{{ $status }}</td></tr>
</table>
<span id="currentnetwork" hidden>{{ $network }}</span>
<span id="selected_payment_type" hidden>{{ $bill->payment_type }}</span>
<span id="gsoft_invoice_current_month" hidden>{{ $bill->current_month }}</span>
</div>
