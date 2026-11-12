s<aside class="main-sidebar">
    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">
      <div class="user-panel">
        <div class="pull-left image">
          <img src="{{ url('/assets/img/avatar.png') }}" class="img-circle" alt="User Image">
        </div>
        <div class="pull-left info">
          <p>{{ session('agentname') }}</p>
          <!-- Status -->
          <a href="#"><i class="fa fa-circle text-success"></i> Online</a>
        </div>
      </div>
      <ul class="sidebar-menu">
        <li class="header">MAIN NAVIGATION</li>
        <!-- Optionally, you can add icons to the links -->
        <li><a href="{{ url('') }}/"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
        <li><a href="{{ url('/agent/payment') }}"><i class="fas fa-money-check"></i> <span>Payment</span></a></li>
        <li class="treeview">
          <a href="#"><i class="fa fa-internet-explorer"></i> <span>Reporting</span> <i class="fa fa-angle-left pull-right"></i></a>
          <ul class="treeview-menu">
            <li><a href="{{ url('agent/date_wise_report') }}"><i class="fa fa-circle-o"></i> <span>Daily Report</span></a></li>
          </ul>
        </li>
      </ul> 
     
		
    </section>
</aside>