@php $ut = (string) session('usertype'); @endphp
<script>
	function generateBillClick()
	{
		alertify.confirm("Would u like to generate bill for this month?", function (e) {
			if (e) {
				generateBill();
			} else {
				alertify.error("Operation Cancelled");
			}
		});
	}
</script>
<aside class="control-sidebar control-sidebar-dark">
   <div class="tab-content">
      <!-- Home tab content -->
      <div class="tab-pane active" id="control-sidebar-home-tab">
        <h3 class="control-sidebar-heading">Admin Settings</h3>
        @if(session('block_network') != 1)
        @if(in_array(8, $menulist))
		    <ul class="control-sidebar-menu">
          <li>
            <a href="{{ url('master/agent_management') }}" id="operatorswitch" name="operatorswitch" onClick="createAgent(); return false;">
              <i class="menu-icon fa fa-anchor bg-green"></i>

              <div class="menu-info">
                <h4 class="control-sidebar-subheading">Add Employee</h4>
                <p>Add Employee</p>
              </div>
            </a>
          </li>
        </ul>
        @endif
        @if(in_array(2, $menulist))
		    <ul class="control-sidebar-menu">
          <li>
            <a href="{{ url('master/package_management') }}" id="apimanagement" name="apimanagement" onClick="createPackage(); return false;">
              <i class="menu-icon fa fa-binoculars bg-red"></i>

              <div class="menu-info">
                <h4 class="control-sidebar-subheading">Add Package</h4>
                <p>Add Package</p>
              </div>
            </a>
          </li>
        </ul>
        @endif
        <h3 class="control-sidebar-heading">Import</h3>
          @if($ut === '1')
          <ul class="control-sidebar-menu">
              <li>
                <a href="{{ url('import/importsuscriberfromezybill') }}" id="operatorswitch" name="operatorswitch">
                  <i class="menu-icon fa fa-anchor bg-green"></i>
                  <div class="menu-info">
                    <h4 class="control-sidebar-subheading">Import subscribers from list customer</h4>
                  </div>
                </a>
              </li>
            </ul>
            <ul class="control-sidebar-menu">
              <li>
                <a href="{{ url('import/importsuscriberfromezybill_f2') }}" id="operatorswitch" name="operatorswitch">
                  <i class="menu-icon fa fa-anchor bg-green"></i>
                  <div class="menu-info">
                    <h4 class="control-sidebar-subheading">Import subscribers from list customer format 2</h4>
                  </div>
                </a>
              </li>
            </ul>
          @endif
          @if(in_array(12, $menulist))
            <ul class="control-sidebar-menu">
              <li>
                <a href="{{ url('import/importsubscriberopeningbalance') }}" id="operatorswitch" name="operatorswitch">
                  <i class="menu-icon fa fa-anchor bg-green"></i>
                  <div class="menu-info">
                    <h4 class="control-sidebar-subheading">Import opening balance</h4>
                  </div>
                </a>
              </li>
            </ul>
          @endif
          @if($ut === '1')
          <ul class="control-sidebar-menu">
            <li>
              <a href="{{ url('import/subscribers') }}" id="operatorswitch" name="operatorswitch" onClick="createSubscriber(); return false;">
                <i class="menu-icon fa fa-anchor bg-green"></i>
                <div class="menu-info">
                  <h4 class="control-sidebar-subheading">Import Invoices from Ezybill</h4>
                </div>
              </a>
            </li>
          </ul>
          @endif
          @if(in_array(1, $menulist))
            <ul class="control-sidebar-menu">
              <li>
                <a href="{{ url('import/importproducts') }}" id="operatorswitch" name="operatorswitch">
                  <i class="menu-icon fa fa-anchor bg-green"></i>
                  <div class="menu-info">
                    <h4 class="control-sidebar-subheading">Import products</h4>
                  </div>
                </a>
              </li>
            </ul>
            <ul class="control-sidebar-menu">
              <li>
                <a href="{{ url('AdminSettings/backup') }}">
                  <i class="menu-icon fa fa-anchor bg-green"></i>
                  <div class="menu-info">
                    <h4 class="control-sidebar-subheading">Backup</h4>
                  </div>
                </a>
              </li>
            </ul>
          @endif
          @endif
      </div>
    </div>
  </aside>
