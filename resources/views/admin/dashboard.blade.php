@extends('admin.layout')

@section('content')
@php
  // Small-box tiles: [colour class, icon prefix (rupee), value, label, footer]. Order = old dashboard.php.
  $tiles = [];
  if ((string) session('usertype') !== '1') {
    $tiles = [
      ['green',  true,  $todays_total_collection['totalcollectionamount'],  'Total collection', 'TODAY'],
      ['yellow', false, $todays_total_collection['totalcollectioncount'],   'Total count', 'TODAY'],
      ['orange', true,  $todays_total_collection['totaldiscountamount'],    'Total discount', 'TODAY'],
      ['black',  true,  $this_month_total_collection['totalcollectionamount'], 'Total collection', 'THIS MONTH'],
      ['gray',   false, $this_month_total_collection['totalcollectioncount'],  'Total collected count', 'THIS MONTH'],
      ['navy',   true,  $this_month_total_collection['totaldiscountamount'],   'Total discount given', 'THIS MONTH'],
      ['yellow', false, $this_month_miscellaneous_collection['totalcollectionamount'], 'Total Miscellaneous', 'THIS MONTH'],
      ['maroon', true,  $previous_month_total_collection['totalcollectionamount'], 'Previous month collection', 'PREVIOUS MONTH'],
      ['maroon', true,  $same_day_last_month_total_collection['totalcollectionamount'], 'Coll-Same day in last month', 'PREVIOUS MONTH'],
      ['orange', null,  $active_customer_count['active_customer'], 'Active customer', 'TOTAL'],
      ['maroon', null,  $active_customer_count['total_customer'],  'Total customer', 'TOTAL'],
      ['teal',   null,  $sms_balance['smscount'], 'SMS Balance', 'TOTAL'],
      ['green',  true,  $total_outstanding['total_outstanding'], 'Total Outstanding', 'TOTAL'],
      ['green',  true,  $last_month_outstanding['total_outstanding'], 'Last Month Outstanding', 'TOTAL'],
    ];
  }
@endphp
<div class="row">
	<div class="col-md-8">
	@foreach($tiles as [$colour, $rupee, $value, $label, $footer])
		<div class="col-lg-4 col-sm-12">
			<div class="small-box bg-{{ $colour }}">
				<div class="inner">
				  <h3>@if($rupee === true)<i class="fa fa-rupee"></i>&nbsp;@elseif($rupee === false)&nbsp;@endif{{ $value }}</h3>
				  <p>{{ $label }}</p>
				</div>
				<div class="icon">
				  <i class="ion ion-arrow-graph-down-right"></i>
				</div>
				<a href="#" class="small-box-footer">{{ $footer }}</a>
			</div>
		</div>
	@endforeach
	</div>
	<div class="col-md-4">
		@foreach($agent_wise_total_collection as $row)
					<div class="col-lg-6 col-sm-12">
						<div class="small-box bg-maroon">
							<div class="inner">
								<span style="font-size: 2rem;"><i class="fa fa-rupee"></i>&nbsp;{{ $row['collectionamount'] }}</span>
								@if($row['agentid'] == 0)<p>Admin</p>@else<p>{{ $row['name'] }}</p>@endif
							</div>
							<div class="icon">
								<i class="ion ion-arrow-graph-down-right"></i>
							</div>
							<a href="#" class="small-box-footer">Today's Collection </a>
						</div>
					</div>
		@endforeach
	</div>
</div>
@endsection
