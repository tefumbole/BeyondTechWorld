@extends('layout.main') @section('content')
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif
<style>
    label {
        font-weight: 600;
    }
</style>
<section>
    <div class="container-fluid">
        @if(in_array("activity-add", $all_permission))
            <button class="btn btn-warning" data-toggle="modal" data-target="#asset-activity-modal"><i class="dripicons-plus"></i>  Add asset Activity </button>
        @endif
    </div>
    <div class="table-responsive">
        <table id="expense-table" class="table">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{trans('file.Date')}}</th>
                    <th>{{trans('file.Fixed Assets')}}</th>
                    <th>Activity Type</th>
                    <th>{{trans('file.Amount')}}</th>
                    <th>Account</th>
{{--                    <th>Approved By</th>--}}
                    <th>{{trans('file.Remarks')}}</th>
                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lims_expense_all as $key=>$expense)
                <?php

                    $asset = DB::table('assets')->find($expense->asset_id);
                        $account = DB::table('accounts')->find($expense->account_id);
                ?>
                <tr data-id="{{$expense->id}}">
                    <td>{{$key}}</td>
                    <td>{{$expense->date}}</td>
                    <td>{{ @$asset->name }}</td>
                    <td>{{ $expense->activity_type }}</td>
                    <td>{{ number_format($expense->amount, 2) }}</td>
                    <td>{{ @$account->name }} - {{ @$account->account_no }}</td>
{{--                    <td>{{ $expense->approved }}</td>--}}
                    <td>{{ $expense->note }}</td>
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li><button type="button" data-id="{{$expense->id}}" class="open-Showexpense_categoryDialog btn btn-link" data-toggle="modal" data-target="#showModal"><i class="fa fa-eye"></i> {{trans('file.View')}}</button></li>
                                @if(in_array("activity-edit", $all_permission))
                                <li><button type="button" data-id="{{$expense->id}}" class="open-Editexpense_categoryDialog btn btn-link" data-toggle="modal" data-target="#editModal"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}</button></li>
                                @endif
                                @if(in_array("activity-delete", $all_permission))
                                <li class="divider"></li>
                                {{ Form::open(['route' => ['expense_asset.destroy', $expense->id], 'method' => 'DELETE'] ) }}
                                <li>
                                    <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> {{trans('file.delete')}}</button>
                                </li>
                                {{ Form::close() }}
                                @endif
                            </ul>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>


<!-- activity modal -->
<div id="asset-activity-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="exampleModalLabel" class="modal-title">Add Asset Activity</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                {!! Form::open(['route' => 'expense_asset.store', 'method' => 'post']) !!}
                <?php
                $lims_assets_list = \App\Asset::where('is_active', true)->get();
                $lims_account_list = \App\Account::where('is_active', true)->get();
                ?>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>{{trans('file.Fixed Assets')}} *</label>
                        <select name="asset_id" class="selectpicker form-control" required data-live-search="true"   title="Select Asset...">
                            @foreach($lims_assets_list as $asset)
                                <option value="{{$asset->id}}">{{$asset->name }} - {{ @$asset->serial_no }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label> Activity Type <strong>*</strong></label>
                        <select class="form-control selectpicker activity" required name="activity_type">
                            <option value="">--choose--</option>
                            <option value="General Activity">General Activity</option>
                            <option value="milage">Milage</option>
                            <option value="copy">Pages Photocopies</option>
                            <option value="Repair">Repair</option>
                        </select>
                    </div>

{{--                    milage--}}
                    <div class="col-md-6 form-group milage">
                        <label>Start in KM</label>
                        <input type="number" name="start_km" step="any" class="form-control start_km">
                        <input type="hidden" name="type" value="activity">
                    </div>
                    <div class="col-md-6 form-group milage">
                        <label>End in KM</label>
                        <input type="number" name="end_km" step="any" class="form-control end_km">
                    </div>
                    <div class="col-md-6 form-group milage">
                        <label>Total in KM</label>
                        <input type="number" name="total_km" step="any" class="form-control total_km">
                    </div>
                    <div class="col-md-6 form-group milage">
                        <label>Reason for Trip</label>
                        <input type="text" name="reason_for_trip" step="any" class="form-control">
                    </div>
                    <div class="col-md-6 form-group milage">
                        <label>Approved By</label><br>
                        <input type="text" name="approved" class="form-control" placeholder="Approved By">
                    </div>

{{--                    Pages Photocopies--}}
                    <div class="col-md-6 form-group copy">
                        <label>Number of Photocopies</label>
                        <input type="number" name="num_of_photocopies" step="any" class="form-control">
                    </div>
{{--                    end--}}

                    {{-- Repair--}}
                    <div class="col-md-12 repair">
                        <br><h4>Repairer Info</h4><hr>
                        <div class="row">
                            <div class="col-md-6 form-group ">
                                <label>Repairer name</label>
                                <input type="text" name="repairer_name" step="any" class="form-control" placeholder="Repairer Name">
                            </div>
                            <div class="col-md-6 form-group ">
                                <label>Repairer Address</label>
                                <input type="text" name="repairer_address" step="any" class="form-control" placeholder="Repairer Address">
                            </div>
                            <div class="col-md-6 form-group ">
                                <label>Repairer Phone</label>
                                <input type="number" name="repairer_phone" step="any" class="form-control" placeholder="Repairer Phone">
                            </div>
                            <div class="col-md-6 form-group ">
                                <label>Repairer Location</label>
                                <input type="text" name="repairer_location" step="any" class="form-control" placeholder="Repairer Location">
                            </div>
                            <div class="col-md-6 form-group ">
                                <label>Repair Status</label>
                                <select name="repair_status" class="form-control">
                                    <option value="Done">Done</option>
                                    <option value="Backlock" selected>Backlock</option>
                                    <option value="In progress">In progress</option>
                                    <option value="Dead">Dead</option>
                                </select>
                            </div>
                        </div>
                        <br><hr>
                    </div>

                    {{--                    end--}}

                    <div class="col-md-6 form-group">
                        <label>{{trans('file.Amount')}} *</label>
                        <input type="number" name="amount" step="any" required class="form-control" value="0">
                    </div>
                    <div class="col-md-6 form-group">
                        <label> {{trans('file.Account')}}</label>
                        <select class="form-control selectpicker" name="account_id">
                            @foreach($lims_account_list as $account)
                                @if($account->is_default)
                                    <option selected value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                                @else
                                    <option value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>{{trans('file.Date')}} *</label>
                        <input type="date" name="date" required class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>{{trans('file.Remarks')}}</label>
                    <textarea name="note" rows="3" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>
<!-- end expense modal -->

<!-- end expense modal -->
<div id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="exampleModalLabel" class="modal-title">Update Activity</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
              <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                {!! Form::open(['route' => ['expense_asset.update', 1], 'method' => 'put']) !!}
                <?php
                    $lims_expense_category_list = DB::table('expense_categories')->where('is_active', true)->get();
                    $lims_assets_list = DB::table('assets')->where('is_active', true)->get();
                    $lims_account_list = \App\Account::where('is_active', true)->get();
                ?>
                    <div class="row">
                        <div class="col-md-6 form-group ">
                            <input type="hidden" name="id">
                            <label>{{trans('file.reference')}}</label>
                            <p id="reference">{{'er-' . date("Ymd") . '-'. date("his")}}</p>
                        </div>
                        <div class="col-md-6 form-group ">
                            <label>Name</label>
                            <p id="asset_name"></p>
                        </div>
                        {{--                    milage--}}
                        <div class="col-md-6 form-group milage">
                            <label>Start in KM (milage)</label>
                            <input type="number" name="start_km" step="any" class="form-control">
                        </div>
                        <div class="col-md-6 form-group milage">
                            <label>End in KM (milage)</label>
                            <input type="number" name="end_km" step="any" class="form-control">
                        </div>
                        <div class="col-md-6 form-group milage">
                            <label>Total in KM (milage)</label>
                            <input type="number" name="total_km" step="any" class="form-control">
                        </div>
                        <div class="col-md-6 form-group milage">
                            <label>Reason for Trip (milage)</label>
                            <input type="text" name="reason_for_trip" step="any" class="form-control">
                        </div>
                        <div class="col-md-6 form-group milage">
                            <label>Approved By (milage)</label><br>
                            <input type="text" name="approved" step="any" class="form-control">
                        </div>

                        {{--                    Pages Photocopies--}}
                        <div class="col-md-6 form-group copy">
                            <label>Number of Photocopies</label>
                            <input type="number" name="num_of_photocopies" step="any" class="form-control">
                        </div>
                        {{--                    end--}}

                        {{--                    Pages Photocopies--}}
                        <div class="col-md-12 repair">
                            <br><h4>Repairer Info</h4><hr>
                            <div class="row">
                                <div class="col-md-6 form-group ">
                                    <label>Repairer name</label>
                                    <input type="text" name="repairer_name" step="any" class="form-control" placeholder="Repairer Name">
                                </div>
                                <div class="col-md-6 form-group ">
                                    <label>Repairer Address</label>
                                    <input type="text" name="repairer_address" step="any" class="form-control" placeholder="Repairer Address">
                                </div>
                                <div class="col-md-6 form-group ">
                                    <label>Repairer Phone</label>
                                    <input type="number" name="repairer_phone" step="any" class="form-control" placeholder="Repairer Phone">
                                </div>
                                <div class="col-md-6 form-group ">
                                    <label>Repairer Location</label>
                                    <input type="text" name="repairer_location" step="any" class="form-control" placeholder="Repairer Location">
                                </div>
                                <div class="col-md-6 form-group ">
                                    <label>Repair Status</label>
                                    <select name="repair_status" class="form-control">
                                        <option value="Done">Done</option>
                                        <option value="Backlock">Backlock</option>
                                        <option value="In progress">In progress</option>
                                        <option value="Dead">Dead</option>
                                    </select>
                                </div>
                            </div>
                            <br><hr>
                        </div>

                        {{--                    end--}}

                        <div class="col-md-6 form-group">
                            <label>{{trans('file.Amount')}} *</label>
                            <input type="number" name="amount" step="any" required class="form-control">
                        </div>
                    </div>
                  <div class="form-group">
                      <label>{{trans('file.Remarks')}}</label>
                      <textarea name="note" rows="3" class="form-control"></textarea>
                  </div>
                  <div class="form-group">
                      <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                  </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>
{{--end edit--}}

<div id="showModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="exampleModalLabel" class="modal-title">Show Activity</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                <table class="table table-striped">
                    <tr>
                        <th> Reference</th>
                        <td id="reference"></td>
                    </tr>
                    <tr>
                        <th>Asset</th>
                        <td class="asset"></td>
                    </tr>
                    <tr>
                        <th>Amount</th>
                        <td class="amount"></td>
                    </tr>
                    <tr class="milage">
                        <th>Start in KM</th>
                        <td class="start_in_km"></td>
                    </tr>
                    <tr class="milage">
                        <th>End in KM</th>
                        <td class="end_in_km"></td>
                    </tr>
                    <tr class="milage">
                        <th>Total in KM</th>
                        <td class="total_in_km"></td>
                    </tr>
                    <tr class="milage">
                        <th>Reason for Trip</th>
                        <td class="reason_for_trip"></td>
                    </tr>
                    <tr class="milage">
                        <th>Approved By:</th>
                        <td class="reason_for_trip approved"></td>
                    </tr>
                    <tr class="copy">
                        <th>Number of Photocopies</th>
                        <td class="num_of_page"></td>
                    </tr>
{{--                    repair--}}
                    <tr class="repair">
                        <th>Repairer Name</th>
                        <td class="repairer_name"></td>
                    </tr>
                    <tr class="repair">
                        <th>Repairer Address</th>
                        <td class="repairer_address"></td>
                    </tr>
                    <tr class="repair">
                        <th>Repairer Phone</th>
                        <td class="repairer_phone"></td>
                    </tr>
                    <tr class="repair">
                        <th>Repairer Location</th>
                        <td class="repairer_location"></td>
                    </tr>
                    <tr class="repair">
                        <th>Repair Status</th>
                        <td class="repair_status"></td>
                    </tr>
{{--                    end--}}
                    <tr>
                        <th>Remarks</th>
                        <td class="remarks"></td>
                    </tr>
                    <tr>
                        <th>Date</th>
                        <td class="date"></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
{{--end show--}}
<script type="text/javascript">

    $('.start_km').on('change keyup paste', function() {
        var start = $(".start_km").val();
        console.log(start);
        var end = $(".end_km").val();
        $(".total_km").val(total);
    });
    $('.end_km').on('change keyup paste', function() {
        var start = $(".start_km").val();
        var end = $(".end_km").val();
        var total = end - start;
        $(".total_km").val(total);
    });

    $(".milage").hide();
    $(".copy").hide();
    $(".repair").hide();

    $('.activity').on('change', function() {
        $(".milage").hide();
        $(".copy").hide();
        $(".repair").hide();
        if ($(this).val() == 'milage') {
            $(".milage").show(300);
        } else if ($(this).val() == 'copy') {
            $(".copy").show(300);
        } else if ($(this).val() == 'Repair') {
            $(".repair").show(300);
        }
    });

    $("a#add-asset-expense").click(function(e){
        e.preventDefault();
        $('#asset-expense-modal').modal();
    });

    $("ul#assets").siblings('a').attr('aria-expanded','true');
    $("ul#assets").addClass("show");
    $("ul#assets #assets-activity-menu").addClass("active");

    var id = [];
    var user_verified = <?php echo json_encode(env('USER_VERIFIED')) ?>;
    var all_permission = <?php echo json_encode($all_permission) ?>;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });


    $(document).ready(function() {
        $(document).on('click', 'button.open-Editexpense_categoryDialog', function() {
            var url = "assets/edit/";
            var id = $(this).data('id').toString();
            url = url.concat(id);
            $.get(url, function(data) {
                $(".milage").hide();
                $(".copy").hide();
                $(".repair").hide();

                $('#editModal #reference').text(data['reference_no']);
                $('#editModal #asset_name').text(data['asset_name']);
                $("#editModal select[name='expense_category_id']").val(data['expense_category_id']);
                $("#editModal select[name='account_id']").val(data['account_id']);
                $("#editModal input[name='amount']").val(data['amount']);
                $("#editModal input[name='id']").val(data['id']);
                if(data['activity_type'] == 'milage') {
                    $(".milage").show();
                }
                if(data['activity_type'] == 'copy') {
                    $(".copy").show();
                }
                if(data['activity_type'] == 'Repair') {
                    $(".repair").show();
                }
                $("#editModal input[name='start_km']").val(data['start_km']);
                $("#editModal input[name='end_km']").val(data['end_km']);
                $("#editModal input[name='approved']").val(data['approved']);
                $("#editModal input[name='total_km']").val(data['total_km']);
                $("#editModal input[name='reason_for_trip']").val(data['reason_for_trip']);
                $("#editModal input[name='num_of_photocopies']").val(data['num_of_photocopies']);

                $("#editModal input[name='repairer_name']").val(data['repairer_name']);
                $("#editModal input[name='repairer_phone']").val(data['repairer_phone']);
                $("#editModal input[name='repairer_address']").val(data['repairer_address']);
                $("#editModal input[name='repairer_location']").val(data['repairer_name']);
                $("#editModal select[name='repair_status']").val(data['repair_status']).change();

                $("#editModal textarea[name='note']").val(data['note']);
                $('.selectpicker').selectpicker('refresh');
            });
        });

        $(document).on('click', 'button.open-Showexpense_categoryDialog', function() {
            var url = "assets/show/";
            var id = $(this).data('id').toString();
            url = url.concat(id);
            $.get(url, function(data) {
                $(".milage").hide();
                $(".copy").hide();
                $(".repair").hide();
                $('#showModal #reference').text(data['reference_no']);
                $("#showModal .amount").text(data['amount']);
                $("#showModal .asset").text(data['asset_name']);
                $("#showModal .category").text(data['category_name']);
                if(data['activity_type'] == 'milage') {
                    $(".milage").show();
                }
                if(data['activity_type'] == 'copy') {
                    $(".copy").show();
                }
                if(data['activity_type'] == 'Repair') {
                    $(".repair").show();
                }
                $("#showModal .start_in_km").text(data['start_km']);
                $("#showModal .end_in_km").text(data['end_km']);
                $("#showModal .total_in_km").text(data['total_km']);
                $("#showModal .reason_for_trip").text(data['reason_for_trip']);
                $("#showModal .num_of_page").text(data['num_of_photocopies']);
                $("#showModal .approved").text(data['approved']);

                $("#showModal .repairer_name").text(data['repairer_name']);
                $("#showModal .repairer_phone").text(data['repairer_phone']);
                $("#showModal .repairer_address").text(data['repairer_address']);
                $("#showModal .repairer_location").text(data['repairer_location']);
                $("#showModal .repair_status").text(data['repair_status']);

                $("#showModal .remarks").text(data['note']);
                $("#showModal .date").text(data['date']);
            });
        });
    });

function confirmDelete() {
    if (confirm("Are you sure want to delete?")) {
        return true;
    }
    return false;
}

    $('#expense-table').DataTable( {
        "order": [],
        'language': {
            'lengthMenu': '_MENU_ {{trans("file.records per page")}}',
             "info":      '<small>{{trans("file.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
            "search":  '{{trans("file.Search")}}',
            'paginate': {
                    'previous': '<i class="dripicons-chevron-left"></i>',
                    'next': '<i class="dripicons-chevron-right"></i>'
            }
        },
        'columnDefs': [
            {
                "orderable": false
            },
            {
                'render': function(data, type, row, meta){
                    if(type === 'display'){
                        data = '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>';
                    }

                   return data;
                },
                'checkboxes': {
                   'selectRow': true,
                   'selectAllRender': '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>'
                },
                'targets': [0]
            }
        ],
        'select': { style: 'multi',  selector: 'td:first-child'},
        'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
        dom: '<"row"lfB>rtip',
        buttons: [
            {
                extend: 'pdf',
                text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    datatable_sum(dt, true);
                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(this, e, dt, button, config);
                    datatable_sum(dt, false);
                },
                footer:true
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    datatable_sum(dt, true);
                    $.fn.dataTable.ext.buttons.csvHtml5.action.call(this, e, dt, button, config);
                    datatable_sum(dt, false);
                },
                footer:true
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    datatable_sum(dt, true);
                    $.fn.dataTable.ext.buttons.print.action.call(this, e, dt, button, config);
                    datatable_sum(dt, false);
                },
                footer:true
            },
            {
                text: '<i title="delete" class="dripicons-cross"></i>',
                className: 'buttons-delete',
                action: function ( e, dt, node, config ) {
                    if(user_verified == '1') {
                        expense_id.length = 0;
                        $(':checkbox:checked').each(function(i){
                            if(i){
                                expense_id[i-1] = $(this).closest('tr').data('id');
                            }
                        });
                        if(expense_id.length && confirm("Are you sure want to delete?")) {
                            $.ajax({
                                type:'POST',
                                url:'expenses/deletebyselection',
                                data:{
                                    expenseIdArray: expense_id
                                },
                                success:function(data){
                                    alert(data);
                                }
                            });
                            dt.rows({ page: 'current', selected: true }).remove().draw(false);
                        }
                        else if(!expense_id.length)
                            alert('No expense is selected!');
                    }
                    else
                        alert('This feature is disable for demo!');
                }
            },
            {
                extend: 'colvis',
                text: '<i title="column visibility" class="fa fa-eye"></i>',
                columns: ':gt(0)'
            },
        ],
        drawCallback: function () {
            var api = this.api();
            datatable_sum(api, false);
        }
    } );

    function datatable_sum(dt_selector, is_calling_first) {
        if (dt_selector.rows( '.selected' ).any() && is_calling_first) {
            var rows = dt_selector.rows( '.selected' ).indexes();
            $( dt_selector.column( 5 ).footer() ).html(dt_selector.cells( rows, 5, { page: 'current' } ).data().sum().toFixed(2));
        }
        else {
            $( dt_selector.column( 5 ).footer() ).html(dt_selector.cells( rows, 5, { page: 'current' } ).data().sum().toFixed(2));
        }
    }

    if(all_permission.indexOf("expenses-delete") == -1)
        $('.buttons-delete').addClass('d-none');

</script>
@endsection
