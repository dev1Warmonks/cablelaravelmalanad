@php
    $unset_add = $unset['add']; $unset_edit = $unset['edit']; $unset_read = $unset['read']; $unset_delete = $unset['delete'];
    $buttons_counter = 0;
    if (!$unset_edit) { $buttons_counter++; }
    if (!$unset_read) { $buttons_counter++; }
    if (!$unset_delete) { $buttons_counter++; }
    $search_column_string = $lang['list_search_column'];
    $list_displaying = str_replace(
        ['{start}', '{end}', '{results}'],
        ['<span class="paging-starts">1</span>', '<span class="paging-ends">10</span>', '<span class="current-total-results">'.$total.'</span>'],
        $lang['list_displaying']);
    [$show_lang_string, $entries_lang_string] = explode('{paging}', $lang['list_show_entries']);
@endphp
<script type="text/javascript">
    var csrf_cookie_name = '';
</script>
<script type='text/javascript'>
    var base_url = '{{ $base_url }}';

    var subject = '{{ $subject }}';
    var ajax_list_info_url = '{{ $ajax_list_info_url }}';
    var ajax_list_url = '{{ $ajax_list_url }}';
    var unique_hash = '{{ $unique_hash }}';

    var message_alert_delete = "{{ $lang['alert_delete'] }}";
    var THEME_VERSION = '1.4.6';
</script>
    <br/>
    <div class="container-fluid gc-container">
        <div class="success-message hidden">@if($success_message !== null){!! $success_message !!} &nbsp; &nbsp;@endif</div>

 		<div class="row">
        	<div class="table-section">
                <div class="table-label">
                    <div class="floatL l5">
                        {{ $subject_plural }}
                    </div>
                    <div class="clear"></div>
                </div>
                <div class="table-container">
                    <form action="" method="post" autocomplete="off" id="gcrud-search-form" accept-charset="utf-8">
                    @csrf
                        <div class="header-tools">
                            @if(!$unset_add)
                                <div class="floatL t5">
                                    <a class="btn btn-default btn-outline-dark" href="{{ $add_url }}">
                                        <i class="el el-plus"></i> &nbsp; {{ $lang['list_add'] }} {{ $subject }}
                                    </a>
                                </div>
                            @endif
                            <div class="floatR">
                                @if(!$unset['export'])
                                    <a class="btn btn-default btn-outline-dark t5 gc-export" data-url="{{ $export_url }}" href="javascript:;">
                                        <i class="el el-share floatL t3"></i>
                                        <span class="hidden-xs floatL l5">
                                            {{ $lang['list_export'] }}
                                        </span>
                                        <div class="clear"></div>
                                    </a>
                                @endif
                                @if(!$unset['print'])
                                    <a class="btn btn-default btn-outline-dark t5 gc-print" data-url="{{ $print_url }}" href="javascript:;">
                                        <i class="el el-print floatL t3"></i>
                                        <span class="hidden-xs floatL l5">
                                            {{ $lang['list_print'] }}
                                        </span>
                                        <div class="clear"></div>
                                    </a>
                                @endif

                                <a class="btn btn-primary search-button t5" href="javascript:;">
                                    <i class="el el-search"></i>
                                    <input type="text" name="search" class="search-input" />
                                </a>
                            </div>
                            <div class="clear"></div>
                        </div>
                        <div class="scroll-if-required">
        			        <table class="table table-bordered grocery-crud-table table-hover">
        					<thead>
        						<tr>
        							<th colspan="2" @if($buttons_counter === 0)class="hidden"@endif>
                                        {{ $lang['list_actions'] }}
                                    </th>
                                    @foreach($columns as $column)
                                        <th class="column-with-ordering" data-order-by="{{ $column->field_name }}">{{ $column->display_as }}</th>
                                    @endforeach
        						</tr>

        						<tr class="filter-row gc-search-row">
        							<td class="no-border-right @if($buttons_counter === 0)hidden @endif">
                                        @if(!$unset_delete)
            							     <div class="floatL t5">
            							         <input type="checkbox" class="select-all-none" />
            							     </div>
                                        @endif
        							 </td>
        							<td class="no-border-left @if($buttons_counter === 0)hidden @endif">
                                        <div class="floatL">
                                            <a href="javascript:void(0);" title="{{ $lang['list_delete'] }}"
                                               class="hidden btn btn-outline-dark delete-selected-button">
                                                <i class="el el-remove text-danger"></i>
                                                <span class="text-danger">{{ $lang['list_delete'] }}</span>
                                            </a>
                                        </div>
                                        <div class="floatR l5">
                                            <a href="javascript:void(0);" class="btn btn-default btn-outline-dark gc-refresh">
                                                <i class="el el-refresh"></i>
                                            </a>
                                        </div>
                                        <div class="clear"></div>
                                    </td>
                                    @foreach($columns as $column)
                                        <td>
                                            <input
                                                type="text"
                                                class="form-control searchable-input floatL"
                                                placeholder="{{ str_replace('{column_name}', $column->display_as, $search_column_string) }}"
                                                name="{{ $column->field_name }}" />
                                        </td>
                                    @endforeach
        						</tr>

        					</thead>
        					<tbody>
                                @include('crud.list_tbody')
        					</tbody>
                            </table>
                        </div>
                            <!-- Table Footer -->
        					<div class="footer-tools">

                                            <!-- "Show 10/25/50/100 entries" (dropdown per-page) -->
                                            <div class="floatL t20 l5">
                                                <div class="floatL t8">
                                                    {{ $show_lang_string }}
                                                </div>
                                                <div class="floatL r5 l5">
                                                    <select name="per_page" class="per_page form-control">
                                                        @foreach($paging_options as $option)
                                                            <option value="{{ $option }}"
                                                                    @if($option == $default_per_page)selected="selected"@endif>
                                                                        {{ $option }}&nbsp;&nbsp;
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="floatL t8">
                                                    {{ $entries_lang_string }}
                                                </div>
                                                <div class="clear"></div>
                                            </div>
                                            <!-- End of "Show 10/25/50/100 entries" (dropdown per-page) -->

                                            <!-- Start of: Settings button -->
                                            <div class="btn-group floatR t20 l5 r5 settings-button-container">
                                                <button type="button" class="btn btn-default btn-outline-primary settings-button gc-bootstrap-dropdown dropdown-toggle">
                                                    <i class="el el-cog r5"></i>
                                                    <span class="caret"></span>
                                                </button>

                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a href="javascript:void(0)" class="clear-filtering dropdown-item">
                                                        <i class="el el-broom"></i> Clear filtering
                                                    </a>
                                                </div>
                                            </div>
                                            <!-- End of: Settings button -->


                                            <div class="floatR r5 gc-pagination-container">

                                                <!-- Buttons - First,Previous,Next,Last Page -->
                                                <ul class="pagination">
                                                    <li class="disabled paging-first page-item">
                                                        <a href="javascript:;" class="page-link">
                                                            <i class="el el-step-backward"></i>
                                                        </a>
                                                    </li>
                                                    <li class="prev disabled paging-previous page-item">
                                                        <a href="javascript:;" class="page-link">
                                                            <i class="el el-chevron-left"></i>
                                                        </a>
                                                    </li>
                                                    <li class="page-item">
                                                        <span class="page-number-input-container page-link">
                                                            <input type="number" value="1" class="form-control page-number-input" />
                                                        </span>
                                                    </li>
                                                    <li class="next paging-next page-item">
                                                        <a href="#" class="page-link">
                                                            <i class="el el-chevron-right"></i>
                                                        </a>
                                                    </li>
                                                    <li class="paging-last page-item">
                                                        <a href="#" class="page-link">
                                                            <i class="el el-step-forward"></i>
                                                        </a>
                                                    </li>
                                                </ul>
                                                <!-- End of Buttons - First,Previous,Next,Last Page -->

                                                <input type="hidden" name="page_number" class="page-number-hidden" value="1" />
                                            </div>

                                            <!-- "Displaying 1 to 10 of 116 items" -->
                                            <div class="floatR r10 displaying-paging-items">
                                                {!! $list_displaying !!}
                                                <span class="full-total-container hidden">
                                                    {!! str_replace('{total_results}', "<span class='full-total'>".$total.'</span>', $lang['list_filtered_from']) !!}
                                                </span>
                                            </div>
                                            <!-- End of "Displaying 1 to 10 of 116 items" -->

                                            <div class="clear"></div>
                            </div>
                            <!-- End of: Table Footer -->

                    </form>
                </div>
        	</div>

            <!-- Delete confirmation dialog -->
            <div class="delete-confirmation modal fade">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ $lang['list_delete'] }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        </div>
                        <div class="modal-body">
                            <p>{{ $lang['alert_delete'] }}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ $lang['form_cancel'] }}</button>
                            <button type="button" class="btn btn-danger delete-confirmation-button">{{ $lang['list_delete'] }}</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End of Delete confirmation dialog -->

            <!-- Delete Multiple confirmation dialog -->
            <div class="delete-multiple-confirmation modal fade">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ $lang['list_delete'] }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        </div>
                        <div class="modal-body">
                            <p class="alert-delete-multiple hidden">
                                {!! str_replace('{items_amount}', '<span class="delete-items-amount"></span>', $lang['alert_delete_multiple']) !!}
                            </p>
                            <p class="alert-delete-multiple-one hidden">
                                {{ $lang['alert_delete_multiple_one'] }}
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                {{ $lang['form_cancel'] }}
                            </button>
                            <button type="button" class="btn btn-danger delete-multiple-confirmation-button"
                                    data-target="{{ $delete_multiple_url }}">
                                {{ $lang['list_delete'] }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End of Delete Multiple confirmation dialog -->

            </div>
