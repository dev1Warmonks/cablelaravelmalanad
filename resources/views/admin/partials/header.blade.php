<header class="main-header">
    <a href="{{ url('master') }}" class="logo">
      <span class="logo-mini"><b>Gsoft</b></span>
      <span class="logo-lg"><b>{{ session('networkname') }}</b></span>
    </a>
    <nav class="navbar navbar-static-top" role="navigation">
      <!-- Sidebar toggle button-->
      <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
        <span class="sr-only">Toggle navigation</span>
      </a>
      @if(session()->has('network_name'))
      <div class="navbar-custom-menu" style="float: left;">
        <ul class="nav navbar-nav">
          <li class="dropdown tasks-menu">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              {{ session('network_name') }}  <i class="fa fa-caret-down"></i>
              <span class="label label-danger">@if(count($network_list) != 0){{ count($network_list) }}@endif</span>
            </a>
            <ul class="dropdown-menu" style="left: 0;">
              <li class="header">You have @if(count($network_list) != 0){{ count($network_list) }}@endif Network</li>
              <li>
                <ul class="menu">
                  <li>
                  @foreach($network_list as $row)
                    <a style="color: #367fa9;" href="{{ url('master/change_network/'.$row['id']) }}">{{ $row['network_name'] }}</a>
                  @endforeach
                  </li>
                </ul>
              </li>
              <li class="footer">
                <a href="{{ url('master/network_management') }}">View all Network</a>
              </li>
            </ul>
          </li>
        </ul>
      </div>
      @endif
      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">
          <!-- User Account Menu -->
          <li class="dropdown user user-menu">
            <!-- Menu Toggle Button -->
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <!-- The user image in the navbar-->
              <img src="{{ url('/assets/img/avatar.png') }}" class="user-image" alt="User Image">
              <!-- hidden-xs hides the username on small devices so only the image appears. -->
              <span class="hidden-xs">{{ session('networkname') }}</span>
            </a>
            <ul class="dropdown-menu">
              <!-- The user image in the menu -->
              <li class="user-header">
                <img src="{{ url('/assets/img/avatar.png') }}" class="img-circle" alt="User Image">

                <p>
                {{ session('networkname') }}
                  <small>Member since APR. 2020</small>
                </p>
              </li>
              <li class="user-footer">
                <div class="pull-left">
                  <a href="#" class="btn btn-default btn-flat">Profile</a>
                </div>
                <div class="pull-right">
                  <a href="{{ url('login/logout/') }}" class="btn btn-default btn-flat">Sign out</a>
                </div>
              </li>
            </ul>
          </li>
          <!-- Control Sidebar Toggle Button -->
          <li>
            <a href="#" data-toggle="control-sidebar"><i class="fa fa-gears"></i></a>
          </li>
        </ul>
      </div>
    </nav>
</header>
