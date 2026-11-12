<header class="main-header">
    <nav class="navbar navbar-static-top" role="navigation">
      <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
        <span class="sr-only">Toggle navigation</span>
      </a>
      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">
          <li class="dropdown user user-menu">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <img src="{{ url('/assets/img/avatar.png') }}" class="user-image" alt="User Image">
              <span class="hidden-xs">{{ session('agentname') }}</span>
            </a>
            <ul class="dropdown-menu">
              <li class="user-header">
                <img src="{{ url('/assets/img/avatar.png') }}" class="img-circle" alt="User Image">
                <p>
                {{ session('agentname') }}
                  <small>Member since APR. 2020</small>
                </p>
              </li>
              <li class="user-footer">
                <div class="pull-left">
                  <a href="#" class="btn btn-default btn-flat">Profile</a>
                </div>
                <div class="pull-right">
                  <a href="{{ url('login/logout') }}/" class="btn btn-default btn-flat">Sign out</a>
                </div>
              </li>
            </ul>
          </li>
        </ul>
      </div>
    </nav>
</header>