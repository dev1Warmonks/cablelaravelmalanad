<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title></title>
  <link rel="icon" href="{{ url('favicon.ico') }}" type="image/x-icon" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <link rel="stylesheet" href="{{ url('/') }}/common/panel/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="{{ url('/') }}/vendor/cdn/cdnjs.cloudflare.com/ajax/libs/font-awesome/4.5.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="{{ url('/') }}/vendor/cdn/cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css">

  <link rel="stylesheet" type="text/css" href="{{ url('/') }}/vendor/cdn/cdn.datatables.net/v/dt/dt-1.10.20/datatables.min.css"/>

  <link rel="stylesheet" href="{{ url('/') }}/common/panel/plugins/select2/select2.min.css">
  <link rel="stylesheet" href="{{ url('/') }}/common/panel/dist/css/AdminLTE.min.css">
  <link rel="stylesheet" href="{{ url('/') }}/common/panel/dist/css/skins/skin-blue.min.css">
  <link rel="stylesheet" href="{{ url('/') }}/cssjs/mycss.css">
  <link rel="stylesheet" href="{{ url('/') }}/cssjs/themes/alertify.core.css" />

  <link rel="stylesheet" href="{{ url('/') }}/vendor/cdn/cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"/>
  <link rel="stylesheet" href="{{ url('/') }}/vendor/cdn/cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css"/>
  <link rel="stylesheet" href="{{ url('/') }}/vendor/cdn/cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/semantic.min.css"/>
  <link rel="stylesheet" href="{{ url('/') }}/vendor/cdn/cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/bootstrap.min.css"/>

  <link rel="stylesheet" href="{{ url('/') }}/vendor/cdn/cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css"/>
  <link rel="stylesheet" href="{{ url('/') }}/vendor/cdn/cdn.datatables.net/buttons/1.6.2/css/buttons.dataTables.min.css"/>

  <link rel="stylesheet" href="{{ url('/') }}/common/pagination/dist/simplePagination.css" id="toggleCSS" />
  <script src="{{ url('/') }}/common/panel/plugins/jQuery/jQuery-2.2.0.min.js"></script>
  <script src="{{ url('/') }}/vendor/cdn/cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
  <script>
    // Laravel CSRF: the old CI app had none. Add the token to every jQuery POST (same UI, protected requests).
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
  </script>
  @stack('css')
<link rel="stylesheet" href="{{ url('/') }}/css/style_v1.101.css">
</head>
<body class="hold-transition skin-blue sidebar-mini sidebar-collapse" oncontextmenu="return true">
<div class="wrapper">
  @include('agent.partials.header')
  @include('agent.partials.leftsidebar')
  <div class="content-wrapper">
    <section class="content-header">
      <h1>
        <span id="contentheader"></span>
        <small>Control Panel</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="{{ url('/') }}/"><i class="fa fa-home"></i>Home</a></li>
        <li class="active"><span id="currentlocation"></span></li>
      </ol>
    </section>

    <section class="content">
      @yield('content')
		<div id="container"></div>
    </section>
  </div>
  <div class="control-sidebar-bg"></div>
</div>
<div class="modaldiv"><!-- Place at bottom of page --></div>
<script src="{{ url('/') }}/common/panel/plugins/jQuery/jQuery-2.2.0.min.js"></script>
<script>
  // jQuery is loaded a second time here (as in the old template) and replaces the first copy, which drops the ajaxSetup made in <head>:
  // set the CSRF header again on the jQuery that the page scripts actually use.
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
</script>
@stack('js')
<script src="{{ url('/') }}/common/panel/dist/js/app.min.js"></script>
<script type="text/javascript" src="{{ url('/') }}/vendor/cdn/cdn.datatables.net/v/dt/dt-1.10.20/datatables.min.js"></script>
<script src="{{ url('/') }}/common/panel/plugins/select2/select2.full.min.js"></script>

<script src="{{ url('/') }}/vendor/cdn/cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
<script src="{{ url('/') }}/vendor/cdn/cdn.datatables.net/buttons/1.6.2/js/dataTables.buttons.min.js"></script>
<script src="{{ url('/') }}/vendor/cdn/cdn.datatables.net/buttons/1.6.2/js/buttons.flash.min.js"></script>
<script src="{{ url('/') }}/vendor/cdn/cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="{{ url('/') }}/vendor/cdn/cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="{{ url('/') }}/vendor/cdn/cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="{{ url('/') }}/vendor/cdn/cdn.datatables.net/buttons/1.6.2/js/buttons.html5.min.js"></script>
<script src="{{ url('/') }}/vendor/cdn/cdn.datatables.net/buttons/1.6.2/js/buttons.print.min.js"></script>
<script src="{{ url('/') }}/vendor/cdn/cdn.datatables.net/buttons/1.6.2/js/buttons.colVis.min.js"></script>
{{-- Old template left this <script> unclosed, so the grocery_crud jquery-1.11.1 tag after it was never loaded.
     Dropped here to keep the exact same runtime behaviour (loading it would replace jQuery 2.2.0). --}}
<script src="{{ url('/') }}/common/panel/bootstrap/js/bootstrap.min.js"></script>


</body>
</html>
