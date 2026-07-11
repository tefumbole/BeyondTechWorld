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
        @if(in_array("asset-expense-add", $all_permission))
            <button class="btn btn-info" data-toggle="modal" data-target="#asset-expense-modal"><i class="dripicons-plus"></i> Add asset Expense </button>
        @endif
    </div>
    <div class="table-responsive">
        <table id="expense-table" class="table">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{trans('file.Date')}}</th>
                    <th>{{trans('file.category')}}</th>
                    <th>{{trans('file.Fixed Assets')}}</th>
                    <th>{{trans('file.Amount')}}</th>
                    <th>{{trans('file.Remarks')}}</th>
                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lims_expense_all as $key=>$expense)
                <?php

                    $expense_category = DB::table('expense_categories')->find($expense->expense_category_id);
                    $asset = DB::table('assets')->find($expense->asset_id);
                ?>
                <tr data-id="{{$expense->id}}">
                    <td>{{$key}}</td>
                    <td>{{$expense->date}}</td>
                    <td>{{ @$expense_category->name }}</td>
                    <td>{{ @$asset->name }}</td>
                    <td>{{ number_format($expense->amount, 2) }}</td>
                    <td>{{ $expense->note }}</td>
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li><button type="button" data-id="{{$expense->id}}" class="open-Showexpense_categoryDialog btn btn-link" data-toggle="modal" data-target="#showModal"><i class="fa fa-eye"></i> {{trans('file.View')}}</button></li>
                                @if(in_array("asset-expense-edit", $all_permission))
                                <li><button type="button" data-id="{{$expense->id}}" class="open-Editexpense_categoryDialog btn btn-link" data-toggle="modal" data-target="#editModal"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}</button></li>
                                @endif
                                @if(in_array("asset-expense-delete", $all_permission))
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
<!-- expense modal -->
<div id="asset-expense-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="exampleModalLabel" class="modal-title">Add Asset Expense</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                {!! Form::open(['route' => 'expense_asset.store', 'method' => 'post']) !!}
                <?php
                $lims_expense_category_list = DB::table('expense_categories')->where('is_active', true)->get();
                $lims_assets_list = \App\Asset::where('is_active', true)->get();
                $lims_account_list = \App\Account::where('is_active', true)->get();

                ?>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>{{trans('file.Expense Category')}} <strong>*</strong></label>
                        <select name="expense_category_id" class="selectpicker form-control" required data-live-search="true"  title="Select Expense Category...">
                            @foreach($lims_expense_category_list as $expense_category)
                                <option value="{{$expense_category->id}}">{{$expense_category->name . ' (' . $expense_category->code. ')'}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>{{trans('file.Fixed Assets')}} *</label>
                        <select name="asset_id" class="selectpicker form-control" required data-live-search="true"   title="Select Asset...">
                            @foreach($lims_assets_list as $asset)
                                <option value="{{$asset->id}}">{{$asset->name }} - {{ $asset->serial_no }}</option>
                            @endforeach
                        </select>
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
                        <label>Expense Amount </label>
                        <input type="number" name="amount" step="any" placeholder="expense" required class="form-control">
                        <input type="hidden" name="type" value="expense">
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
<div id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Update Expense')}}</h5>
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
                  <div class="form-group">
                      <input type="hidden" name="id">
                      <label>{{trans('file.reference')}}</label>
                      <p id="reference">{{'er-' . date("Ymd") . '-'. date("his")}}</p>
                  </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>{{trans('file.Expense Category')}}</label>
                            <select name="expense_category_id" id="expense_category_id" class="selectpicker form-control" data-live-search="true"   title="Select Expense Category...">
                                @foreach($lims_expense_category_list as $expense_category)
                                <option value="{{$expense_category->id}}">{{$expense_category->name . ' (' . $expense_category->code. ')'}}</option>
                                @endforeach
                            </select>
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
                <h5 id="exampleModalLabel" class="modal-title">Show Expenses & Activity</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                <table class="table table-striped">
                    <tr>
                        <th> Reference</th>
                        <td id="reference"></td>
                    </tr>
                    <tr>
                        <th>Account</th>
                        <td class="account"></td>
                    </tr>
                    <tr>
                        <th>Asset</th>
                        <td class="asset"></td>
                    </tr>
                    <tr>
                        <th>Amount</th>
                        <td class="amount"></td>
                    </tr>
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

    $(".milage").hide();
    $(".copy").hide();

    $('.activity').on('change', function() {
        if ($(this).val() == 'milage') {
            $(".copy").hide();
            $(".milage").show(300);
        } else if ($(this).val() == 'copy') {
            $(".milage").hide();
            $(".copy").show(300);
        } else {
            $(".milage").hide();
            $(".copy").hide();
        }
    });

    $("a#add-asset-expense").click(function(e){
        e.preventDefault();
        $('#asset-expense-modal').modal();
    });


    $("ul#assets").siblings('a').attr('aria-expanded','true');
    $("ul#assets").addClass("show");
    $("ul#assets #assets-expense-menu").addClass("active");

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
                $('#editModal #reference').text(data['reference_no']);
                $("#editModal select[name='expense_category_id']").val(data['expense_category_id']);
                $("#editModal select[name='account_id']").val(data['account_id']);
                $("#editModal input[name='amount']").val(data['amount']);
                $("#editModal input[name='id']").val(data['id']);


                $("#editModal textarea[name='note']").val(data['note']);
                $('.selectpicker').selectpicker('refresh');
            });
        });

        $(document).on('click', 'button.open-Showexpense_categoryDialog', function() {
            var url = "assets/show/";
            var id = $(this).data('id').toString();
            url = url.concat(id);
            $.get(url, function(data) {
                $('#showModal #reference').text(data['reference_no']);
                $("#showModal .amount").text(data['amount']);
                $("#showModal .asset").text(data['asset_name']);
                $("#showModal .category").text(data['category_name']);
                $("#showModal .account").text(data['account_name']);
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
