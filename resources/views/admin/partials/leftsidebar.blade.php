@php
  $ut = (string) session('usertype');
  $blocked = session('block_network') == 1;
@endphp
<aside class="main-sidebar">
    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">

      <!-- Sidebar user panel (optional) -->
      <div class="user-panel">
        <div class="pull-left image">
          <img src="{{ url('/assets/img/avatar.png') }}" class="img-circle" alt="User Image">
        </div>
        <div class="pull-left info">
          <p>{{ session('networkname') }}</p>
          <!-- Status -->
          <a href="#"><i class="fa fa-circle text-success"></i> Online</a>
        </div>
      </div>

      <!-- Sidebar Menu -->

      <ul class="sidebar-menu">
        <li class="header">MAIN NAVIGATION</li>
		@if(! $blocked)
        <li><a href="{{ url('master') }}"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a></li>
		@if($ut === '1')
			<li class="treeview">
				<a href="#"><i class="fa fa-shield"></i> <span>Admin Settings</span> <i class="fa fa-angle-left pull-right"></i></a>
				<ul class="treeview-menu">
					<li><a href="{{ url('AdminSettings/users_management') }}"><i class="fa fa-circle-o"></i> <span>Users</span></a></li>
					<li><a href="{{ url('AdminSettings/payment_types') }}"><i class="fa fa-circle-o"></i> <span>Payment types</span></a></li>
					<li><a href="{{ url('AdminSettings/general') }}"><i class="fa fa-circle-o"></i> <span>General</span></a></li>
					<li><a href="{{ url('AdminSettings/user_main_menus') }}"><i class="fa fa-circle-o"></i> <span>Main Menus</span></a></li>
					<li><a href="{{ url('AdminSettings/sms_apis') }}"><i class="fa fa-circle-o"></i> <span>Sms APIS</span></a></li>
					<li><a href="{{ url('AdminSettings/monthly_bills') }}"><i class="fa fa-circle-o"></i> <span>Monthly bills</span></a></li>
					<li><a href="{{ url('AdminSettings/import_formats') }}"><i class="fa fa-circle-o"></i> <span>Import Formats</span></a></li>
				</ul>
			</li>
		@endif
		@if(array_intersect([1,2,3,4,5,6], $menulist))
			<li class="treeview">
				<a href="#"><i class="fa fa-gear"></i> <span>Settings</span> <i class="fa fa-angle-left pull-right"></i></a>
				<ul class="treeview-menu">
					@if(in_array(1, $menulist))
						<li><a href="{{ url('master/products_management') }}"><i class="fa fa-circle-o"></i> <span>Products</span></a></li>
						<li><a href="{{ url('master/products_category_management') }}"><i class="fa fa-circle-o"></i> <span>Products Category</span></a></li>
						<li><a href="{{ url('master/taxrate_management') }}"><i class="fa fa-circle-o"></i> <span>Tax rate</span></a></li>
					@endif
					@if(in_array(2, $menulist))
					<li><a href="{{ url('master/package_management') }}"><i class="fa fa-circle-o"></i> <span>Packages</span></a></li>
					@endif
					@if(in_array(3, $menulist))
					<li><a href="{{ url('master/area_management') }}"><i class="fa fa-circle-o"></i> <span>Area</span></a></li>
					<li><a href="{{ url('master/subarea_management') }}"><i class="fa fa-circle-o"></i> <span>Sub Area</span></a></li>
					@endif
					@if(in_array(4, $menulist))
					<li><a href="{{ url('master/network_management') }}"><i class="fa fa-circle-o"></i> <span>Network</span></a></li>
					@endif
					@if(in_array(5, $menulist))
					<li><a href="{{ url('master/stb_model_management') }}"><i class="fa fa-circle-o"></i> <span>STB Model</span></a></li>
					<li><a href="{{ url('master/stb_type_management') }}"><i class="fa fa-circle-o"></i> <span>STB Type</span></a></li>
					<li><a href="{{ url('master/stb_make_management') }}"><i class="fa fa-circle-o"></i> <span>STB Make</span></a></li>
					<li><a href="{{ url('master/stb_cas_management') }}"><i class="fa fa-circle-o"></i> <span>CAS</span></a></li>
					@endif
					@if($ut === '1')
						<li><a href="{{ url('master/miscellaneous_heads_management') }}"><i class="fa fa-circle-o"></i>Miscellaneous Heads</a></li>
						<li><a href="{{ url('Settings/general') }}"><i class="fa fa-circle-o"></i>General Settings</a></li>
					@endif
				</ul>
			</li>
		@endif
		@if($ut !== '1')
		<li class="treeview">
			<a href="#"><i class="fa fa-automobile"></i> <span>Transactions</span> <i class="fa fa-angle-left pull-right"></i></a>
			<ul class="treeview-menu">
				<li><a href="{{ url('Transaction/payment') }}"><i class="fa fa-circle-o"></i> <span>Make Payment</span></a></li>
				<li><a href="{{ url('Transaction/') }}"><i class="fa fa-circle-o"></i> <span>History</span></a></li>
				<li><a href="{{ url('Subscriber') }}"><i class="fa fa-circle-o"></i> <span>Due List</span></a></li>
				<li><a href="{{ url('Transaction/ledger') }}"><i class="fa fa-circle-o"></i> <span>Subs Statement</span></a></li>
				<li><a href="{{ url('Transaction/import_history') }}"><i class="fa fa-circle-o"></i> <span>Invoice import history</span></a></li>
				<li><a href="{{ url('Transaction/miscellaneous') }}"><i class="fa fa-circle-o"></i> <span>Add Miscellaneous</span></a></li>
                <li><a href="{{ url('Transaction/bulkoperations') }}"><i class="fa fa-circle-o"></i> <span>Bulk Operation</span></a></li>
			</ul>
        </li>
		@endif
		@if(in_array(7, $menulist))
		<li class="treeview">
			<a href="#"><i class="fa fa-exclamation-triangle"></i> <span>Complaints</span> <i class="fa fa-angle-left pull-right"></i></a>
			<ul class="treeview-menu">
				<li><a href="{{ url('complaint/complaint_add_view') }}"><i class="fa fa-circle-o"></i> <span>Add Complaint</span></a></li>
				<li><a href="{{ url('complaint/') }}"><i class="fa fa-circle-o"></i> <span>Complaints List</span></a></li>
				<li><a href="{{ url('complaint/enquiry/add') }}"><i class="fa fa-circle-o"></i> <span>Add Enquiry</span></a></li>
				<li><a href="{{ url('complaint/enquiry') }}"><i class="fa fa-circle-o"></i> <span>Enquiry List</span></a></li>
				@if($ut === '1')
				<li><a href="{{ url('complaint/complaint_type_management') }}"><i class="fa fa-circle-o"></i> <span>Complaints Type</span></a></li>
				<li><a href="{{ url('complaint/enquiry_type_management') }}"><i class="fa fa-circle-o"></i> <span>Enquiry Type</span></a></li>
				@endif
			</ul>
        </li>
		@endif
		@if(in_array(9, $menulist) && $ut !== '1')
			<li class="treeview">
				<a href="#"><i class="fa fa-user"></i> <span>Expenses</span> <i class="fa fa-angle-left pull-right"></i></a>
				<ul class="treeview-menu">
					<li><a href="{{ url('Transaction/expenses') }}"><i class="fa fa-circle-o"></i> <span>Add Expenses</span></a></li>
					<li><a href="{{ url('Transaction/expenseheader_management') }}"><i class="fa fa-circle-o"></i> <span>Expense Heads</span></a></li>
				</ul>
			</li>
		@endif
		<li class="treeview">
			<a href="#"><i class="fa fa-user"></i> <span>Subscriber</span> <i class="fa fa-angle-left pull-right"></i></a>
			@if($ut === '1')
			<ul class="treeview-menu">
				<li><a href="{{ url('Subscriber/all_subscriber') }}"><i class="fa fa-circle-o"></i> <span>Subscriber List</span></a></li>
				<li><a href="{{ url('Transaction/all_network_transaction') }}"><i class="fa fa-circle-o"></i> <span>All Tran. history</span></a></li>
				<li><a href="{{ url('Transaction/import_history') }}"><i class="fa fa-circle-o"></i> <span>Import history</span></a></li>
			</ul>
			@else
				<ul class="treeview-menu">
					<li><a href="{{ url('Subscriber') }}?stype=1"><i class="fa fa-circle-o"></i> <span>IPTV CusList</span></a></li>
					<li><a href="{{ url('Subscriber') }}?stype=2"><i class="fa fa-circle-o"></i> <span>Broadband CusList</span></a></li>
					<li><a href="{{ url('Subscriber') }}"><i class="fa fa-circle-o"></i> <span>CableTV CusList</span></a></li>
					<li><a href="{{ url('Subscriber/inactive_subscriber') }}"><i class="fa fa-circle-o"></i> <span>Inactive Subscriber List</span></a></li>
					<li><a href="{{ url('Subscriber/Mlist') }}"><i class="fa fa-circle-o"></i> <span>Subscriber M Update</span></a></li>
					<li><a href="{{ url('Subscriber/subscriber_management/add') }}"><i class="fa fa-circle-o"></i> <span>Add Subscriber</span></a></li>
				</ul>
			@endif
        </li>
		@if(in_array(5, $menulist))
		<li class="treeview">
			<a href="#"><i class="fa fa-square"></i> <span>STB</span> <i class="fa fa-angle-left pull-right"></i></a>
			<ul class="treeview-menu">
			<li><a href="{{ url('master/stb_management/add') }}"><i class="fa fa-circle-o"></i> <span>Add STB</span></a></li>
				<li><a href="{{ url('master/stb_management') }}"><i class="fa fa-circle-o"></i> <span>STB List</span></a></li>

			</ul>
        </li>
		@endif
		@if(in_array(10, $menulist))
		<li class="treeview">
			<a href="#"><i class="fa fa-internet-explorer"></i> <span>Broadband</span> <i class="fa fa-angle-left pull-right"></i></a>
			<ul class="treeview-menu">
				<li><a href="{{ url('Broadband/broadband_subscriber_management') }}"><i class="fa fa-circle-o"></i> <span>Subscribers</span></a></li>
				@if($ut === '1')
					<li><a href="{{ url('Broadband/broadbandplans_management') }}"><i class="fa fa-circle-o"></i> <span>Broadband Plans</span></a></li>
					<li><a href="{{ url('Broadband/isptype_management') }}"><i class="fa fa-circle-o"></i> <span>ISP Type</span></a></li>
					<li><a href="{{ url('Broadband/comboplans_management') }}"><i class="fa fa-circle-o"></i> <span>Combo Plans</span></a></li>
				@endif
				<li><a href="{{ url('Broadband/broadband_payment') }}"><i class="fa fa-circle-o"></i> <span>Make Payment</span></a></li>
			</ul>
        </li>
		@endif
		@if($ut !== '1')
		<li class="treeview">
			<a href="#"><i class="fa fa-internet-explorer"></i> <span>Reporting</span> <i class="fa fa-angle-left pull-right"></i></a>
			<ul class="treeview-menu">
				<li><a href="{{ url('report/date_wise_report') }}"><i class="fa fa-circle-o"></i> <span>Daily Report</span></a></li>
				<li><a href="{{ url('report/agent_report') }}" ><i class="fa fa-circle-o"></i> <span>Agent Report</span></a></li>
				<li><a href="{{ url('report/agent_wise_collection_report') }}"><i class="fa fa-circle-o"></i> <span>Agent Daily Report</span></a></li>
				<li><a href="{{ url('Subscriber/non_paid_this_month_report') }}"><i class="fa fa-circle-o"></i> <span>Non paid this month</span></a></li>
				<li><a href="{{ url('report/miscellaneous_report') }}"><i class="fa fa-circle-o"></i> <span>Miscellaneous Report</span></a></li>
			</ul>
        </li>
		@endif
		@endif
		@if($ut !== '1')
		<li class="treeview">
			<a href="#"><i class="fa fa-automobile"></i> <span>GSoft</span> <i class="fa fa-angle-left pull-right"></i></a>
			<ul class="treeview-menu">
				<li><a href="{{ url('Transaction/monthly_bills') }}"><i class="fa fa-circle-o"></i> <span>Monthly bills</span></a></li>
			</ul>
        </li>
		@endif
      </ul>

      <!-- /.sidebar-menu -->
    </section>
    <!-- /.sidebar -->
</aside>
