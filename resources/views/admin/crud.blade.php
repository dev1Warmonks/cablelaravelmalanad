@extends('admin.layout')

@push('css')
@foreach($crud['css_files'] as $file)
	<link type="text/css" rel="stylesheet" href="{{ $file }}" />
@endforeach
@endpush

@section('content')
{!! $crud['output'] !!}
@endsection

@push('js')
@foreach($crud['js_files'] as $file)
      <script src="{{ $file }}"></script>
@endforeach
<script>
  // CSRF for the Grocery_CRUD theme's own jQuery (it replaces the page jQuery): same-origin POSTs carry the token.
  (function ($) {
    $.ajaxPrefilter(function (options, original, jqXHR) {
      if (!options.crossDomain) { jqXHR.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}'); }
    });
  })(window.jQuery);
</script>
@endpush
