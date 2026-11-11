<script type="text/javascript">
    var csrf_cookie_name = '';
</script>
<div class="crud-form" data-unique-hash="{{ $unique_hash }}">
    <div class="gc-container">
        <div class="row">
            <div class="col-md-12">
                <div class="table-label">
                    <div class="floatL l5">
                        @if($mode === 'read'){{ $lang['list_view'] }}@elseif($mode === 'edit'){{ $lang['form_edit'] }}@else{{ $lang['form_add'] }}@endif {{ $subject }}
                    </div>
                    <div class="clear"></div>
                </div>
                <div class="form-container table-container">
                    <form action="{{ $mode === 'edit' || $mode === 'read' ? $update_url : $insert_url }}" method="post" id="crudForm"  enctype="multipart/form-data" accept-charset="utf-8">
                    @csrf

                    @foreach($fields as $field)
                        <div class="form-group {{ $field->name }}_form_group row">
                            <label class="col-sm-3 control-label">
                                {{ $field->display_as }}@if($mode === 'read'):@elseif($field->required)<span class='required'>*</span> @endif
                            </label>
                            <div class="col-sm-9{{ $mode === 'read' ? ' read-row' : '' }}">
                                {!! $field->input !!}
                            </div>
                        </div>
                    @endforeach
                    <!-- Start of hidden inputs -->
                    @foreach($hidden as $h){!! $h !!}@endforeach
                    <!-- End of hidden inputs -->

                    @if($mode !== 'read')
                    <div class='small-loading hidden' id='FormLoading'>{{ $mode === 'edit' ? $lang['form_update_loading'] : $lang['form_insert_loading'] }}</div>
                    @endif

                    <div class="form-group gcrud-form-group">
                        <div id='report-error' class='report-div error bg-danger' style="display:none"></div>
                        <div id='report-success' class='report-div success bg-success' style="display:none"></div>
                    </div>
                    <div class="form-group gcrud-form-group">
                        <div class="col-sm-offset-3 col-sm-7">
                            @if($mode !== 'read')
                            <button class="btn btn-secondary btn-success b10" type="submit" id="form-button-save">
                                <i class="el el-ok"></i>
                                {{ $mode === 'edit' ? $lang['form_update_changes'] : $lang['form_save'] }}
                            </button>
                            @endif
                            @if(!$unset_back_to_list)
                                @if($mode === 'read')
                                <button class="btn btn-secondary cancel-button" type="button" onclick="window.location = '{{ $list_url }}'" >
                                    <i class="el el-return-key"></i>
                                    {{ $lang['form_back_to_list'] }}
                                </button>
                                @else
                                <button class="btn btn-info b10" type="button" id="save-and-go-back-button">
                                    <i class="el el-return-key"></i>
                                    {{ $mode === 'edit' ? $lang['form_update_and_go_back'] : $lang['form_save_and_go_back'] }}
                                </button>
                                <button class="btn btn-secondary cancel-button b10" type="button" id="cancel-button">
                                    <i class="el el-warning-sign"></i>
                                    {{ $lang['form_cancel'] }}
                                </button>
                                @endif
                            @endif
                        </div>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    var validation_url = '{{ $validation_url }}';
    var list_url = '{{ $list_url }}';

    @if($mode === 'edit' || $mode === 'read')
    var message_alert_edit_form = "{{ $lang['alert_edit_form'] }}";
    var message_update_error = "{{ $lang['update_error'] }}";
    @else
    var message_alert_add_form = "{{ $lang['alert_add_form'] }}";
    var message_insert_error = "{{ $lang['insert_error'] }}";
    @endif
</script>
