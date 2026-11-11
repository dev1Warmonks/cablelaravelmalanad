@php
    $unset_edit = $unset['edit']; $unset_read = $unset['read']; $unset_delete = $unset['delete']; $unset_clone = $unset['clone'];
    $buttons_counter = 0;
    if (!$unset_edit) { $buttons_counter++; }
    if (!$unset_read) { $buttons_counter++; }
    if (!$unset_delete) { $buttons_counter++; }
    if (!$unset_clone) { $buttons_counter++; }
    $show_more_button = $buttons_counter > 2;
    $more_string = $lang['list_more'];
    $clone_string = $lang['list_clone'];
@endphp
@foreach($list as $row)
    <tr>
        <td @if($unset_delete) style="border-right: none;"@endif
            @if($buttons_counter === 0)class="hidden"@endif>
            @if(!$unset_delete)
                <input type="checkbox" class="select-row" data-id="{{ $row->primary_key_value }}" />
            @endif
        </td>
        <td @if($unset_delete) style="border-left: none;"@endif
            @if($buttons_counter === 0)class="hidden"@endif>
                <div class="only-desktops"  style="white-space: nowrap">
                    @if(!$unset_edit)
                        <a class="btn btn-default btn-outline-dark" href="{{ $row->edit_url }}"><i class="el el-pencil"></i> {{ $lang['list_edit'] }}</a>
                    @endif
                    @if(!$unset_read || !$unset_delete || !$unset_clone)
                        @if($show_more_button)
                            <div class="btn-group">
                                <button type="button" class="btn btn-default btn-outline-dark gc-bootstrap-dropdown dropdown-toggle">
                                    {{ $more_string }}
                                    <span class="caret"></span>
                                </button>

                                <div class="dropdown-menu">
                                    @if(!$unset_read)
                                        <a href="{{ $row->read_url }}" class="dropdown-item">
                                            <i class="el el-eye-open"></i> {{ $lang['list_view'] }}
                                        </a>
                                    @endif
                                    @if(!$unset_clone)
                                        <a href="{{ $row->clone_url }}" class="dropdown-item">
                                            <i class="el el-fork"></i> {{ $clone_string }}
                                        </a>
                                    @endif
                                    @if(!$unset_delete)
                                        <a data-target="{{ $row->delete_url }}" href="javascript:void(0)" title="{{ $lang['list_delete'] }}" class="delete-row dropdown-item">
                                            <i class="el el-remove text-danger"></i>
                                            <span class="text-danger">{{ $lang['list_delete'] }}</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @else
                            @if(!$unset_read)
                                <a href="{{ $row->read_url }}" class="btn btn-default btn-outline-dark">
                                    <i class="el el-eye-open"></i> {{ $lang['list_view'] }}
                                </a>
                            @endif
                            @if(!$unset_clone)
                                <a href="{{ $row->clone_url }}" class="btn btn-default btn-outline-dark">
                                    <i class="el el-fork"></i> {{ $clone_string }}
                                </a>
                            @endif
                            @if(!$unset_delete)
                                <a data-target="{{ $row->delete_url }}" href="javascript:void(0)" title="{{ $lang['list_delete'] }}" class="delete-row btn btn-default btn-outline-dark">
                                    <i class="el el-remove text-danger"></i>
                                    <span class="text-danger">{{ $lang['list_delete'] }}</span>
                                </a>
                            @endif
                        @endif
                    @endif
                </div>
                <div class="only-mobiles">
                    @if($buttons_counter > 0)
                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary gc-bootstrap-dropdown dropdown-toggle">
                            {{ $lang['list_actions'] }}
                            <span class="caret"></span>
                        </button>
                        <div class="dropdown-menu">
                            @if(!$unset_edit)
                                <a href="{{ $row->edit_url }}" class="dropdown-item">
                                    <i class="el el-pencil"></i> {{ $lang['list_edit'] }}
                                </a>
                            @endif
                            @if(!$unset_read)
                                <a href="{{ $row->read_url }}" class="dropdown-item">
                                    <i class="el el-eye-open"></i> {{ $lang['list_view'] }}
                                </a>
                            @endif
                            @if(!$unset_clone)
                                <a href="{{ $row->clone_url }}" class="dropdown-item">
                                    <i class="el el-fork"></i> {{ $clone_string }}
                                </a>
                            @endif
                            @if(!$unset_delete)
                                <a data-target="{{ $row->delete_url }}" href="javascript:void(0)" title="{{ $lang['list_delete'] }}" class="delete-row dropdown-item">
                                    <i class="el el-remove text-danger"></i> <span class="text-danger">{{ $lang['list_delete'] }}</span>
                                </a>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
        </td>
        @foreach($columns as $column)
            <td>
                {!! ($row->{$column->field_name} ?? '') != '' ? $row->{$column->field_name} : '&nbsp;' !!}
            </td>
        @endforeach
    </tr>
@endforeach
