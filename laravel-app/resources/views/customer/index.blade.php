@extends('layout.main') @section('content')

    <div id="ajax-message"></div>
    @if(session()->has('create_message'))
        <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('create_message') !!}</div>
    @endif
    @if(session()->has('edit_message'))
        <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('edit_message') }}</div>
    @endif
    @if(session()->has('import_message'))
        <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('import_message') !!}</div>
    @endif
    @if(session()->has('not_permitted'))
        <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
    @endif
    <style>
        @media (min-width: 576px) {
            .modal-dialog {
                max-width: 1000px;
            }
        }
        .customer-directory { padding: 4px 8px 28px; }
        .customer-directory-card {
            background: #fff;
            border: 1px solid #e7e9ef;
            border-radius: 18px;
            box-shadow: 0 12px 32px rgba(16, 24, 40, 0.05);
        }
        .customer-directory-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            padding: 22px 22px 4px;
        }
        .customer-directory-head h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 650;
            letter-spacing: -0.02em;
            color: #171923;
        }
        .customer-directory-head p {
            margin: 4px 0 0;
            color: #8b919c;
            font-size: 13px;
        }
        .customer-directory .dataTables_wrapper > .row:first-child {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin: 0;
            padding: 12px 18px 6px;
        }
        .customer-directory .dataTables_length,
        .customer-directory .dataTables_filter { float: none; margin: 0; }
        .customer-directory .dt-buttons { margin-left: auto; }
        .customer-directory .dataTables_length select,
        .customer-directory .dataTables_filter input {
            border: 1px solid #e6e8ee;
            border-radius: 10px;
            height: 36px;
            background: #fff;
            color: #171923;
        }
        .customer-directory .dataTables_filter input { min-width: 220px; padding: 0 12px; margin-left: 8px; }
        .customer-directory .dataTables_length select { padding: 0 8px; }
        .customer-directory .dt-buttons .btn { border-radius: 10px; margin-left: 6px; }
        .customer-directory .table-responsive { border: 0; margin: 0; padding: 0 8px 8px; }
        #customer-table { margin-top: 0 !important; border-collapse: separate; border-spacing: 0; }
        #customer-table thead th {
            border-top: 0;
            border-bottom: 1px solid #eef0f4;
            background: #fafbfc;
            color: #8b919c;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            padding: 12px 10px;
            white-space: nowrap;
        }
        #customer-table tbody td {
            border-top: 0;
            border-bottom: 1px solid #f2f4f7;
            padding: 14px 10px;
            vertical-align: middle;
            color: #3f4654;
            font-size: 13.5px;
        }
        #customer-table tbody tr:hover td { background: #f8f9fc; }
        #customer-table td.customer-name {
            min-width: 168px;
            color: #171923;
            font-weight: 650;
            line-height: 1.35;
        }
        #customer-table td.customer-company { min-width: 120px; color: #4b5563; }
        #customer-table td.customer-email,
        #customer-table td.customer-phone { white-space: nowrap; }
        #customer-table td.customer-address { min-width: 140px; line-height: 1.35; }
        #customer-table .editable-select.form-control {
            width: auto;
            min-width: 118px;
            height: 30px;
            margin: 0;
            padding: 0 22px 0 12px;
            border: 0;
            border-radius: 999px;
            background-color: #f3f1ff;
            color: #5b46d6;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .03em;
            box-shadow: none;
        }
        #customer-table td.customer-money {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            color: #374151;
        }
        #customer-table td.customer-money.is-zero { color: #c5c9d2; }
        #customer-table td.editable:focus {
            outline: 2px solid #d9d6fe;
            outline-offset: -2px;
            border-radius: 8px;
            background: #fff;
        }
        .customer-directory .dataTables_info,
        .customer-directory .dataTables_paginate { padding: 12px 18px 16px; }
        @media (max-width: 768px) {
            .customer-directory-head { padding: 16px 14px 0; }
            .customer-directory .dataTables_filter input { min-width: 0; width: 100%; }
            .customer-directory .dt-buttons { margin-left: 0; }
        }
    </style>

    <section class="customer-directory">
        <div class="customer-directory-card">
            <div class="customer-directory-head">
                <div>
                    <h1>{{ trans('file.customer') }}s</h1>
                    <p>{{ $lims_customer_all->count() }} {{ trans('file.customer') }}s</p>
                </div>
            </div>
        <div class="customer-directory-body">
{{--            <div class="row ">--}}
{{--                <div class="col-md-4 product-report-filter mt-4">--}}
{{--                    @if(in_array("customers-add", $all_permission))--}}
{{--                        <a href="{{route('customer.create')}}" class="btn btn-info"><i class="dripicons-plus"></i> {{trans('file.Add Customer')}}</a>&nbsp;--}}
{{--                        <a href="#" data-toggle="modal" data-target="#importCustomer" class="btn btn-primary"><i class="dripicons-copy"></i> {{trans('file.Import Customer')}}</a>--}}
{{--                    @endif--}}
{{--                </div>--}}
{{--                <form action="{{route('customer.index')}}" method="get">--}}
{{--                    @csrf--}}
{{--                    <div class="col-md-7 offset-md-1 product-report-filter ">--}}
{{--                        <div class="form-group row">--}}
{{--                            <label class="d-tc mt-2"><strong>{{trans('file.Choose Your Date')}}</strong> &nbsp;</label>--}}
{{--                            <div class="d-tc">--}}
{{--                                <div class="input-group">--}}
{{--                                    <input type="text" class="daterangepicker-field form-control" value="{{$start_date}} To {{$end_date}}" required />--}}
{{--                                    <input type="hidden" name="start_date" value="{{$start_date}}" />--}}
{{--                                    <input type="hidden" name="end_date" value="{{$end_date}}" />--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                            <div class="col-md-2 ">--}}
{{--                                <div class="form-group">--}}
{{--                                    <button class="btn btn-primary" type="submit">{{trans('file.Search')}}</button>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </form>--}}

{{--            </div>--}}
{{--        </div>--}}
        <div class="table-responsive">
            <table id="customer-table" class="table">
                <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{trans('file.Customer Group')}}</th>
                    <th>{{trans('file.name')}}</th>
                    <th>{{trans('file.Company Name')}}</th>
                    <th>{{trans('file.Email')}}</th>
                    <th>{{trans('file.Phone Number')}}</th>
                    <th>{{trans('file.Tax Number')}}</th>
                    <th>{{trans('file.Address')}}</th>
                    <th>{{trans('file.Reward Points')}}</th>
                    <th>{{trans('file.Deposit')}}</th>
                    <th>Awaiting Payment</th>
                    <th>{{trans('file.Owing')}}</th>
                    <th>Total Payable</th>
                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($lims_customer_all as $key=>$customer)
                    <tr data-id="{{$customer->id}}">
                        <td>{{$key}}</td>
                        <td>
                            <select class="editable-select form-control" data-id="{{ $customer->id }}" data-field="customer_group_id">
                                @foreach($customer_groups as $group)
                                    <option value="{{ $group->id }}" {{ $customer->customer_group_id == $group->id ? 'selected' : '' }}>
                                        {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td contenteditable="true" class="editable customer-name" data-id="{{ $customer->id }}" data-field="name">{{ $customer->name }}</td>
                        <td class="customer-company">{{ $customer->company_name}}</td>
                        <td contenteditable="true" class="editable customer-email" data-id="{{ $customer->id }}" data-field="email">{{ $customer->email }}</td>
                        <td contenteditable="true" class="editable customer-phone" data-id="{{ $customer->id }}" data-field="phone_number">{{ $customer->phone_number }}</td>
                        <td>{{ $customer->tax_no}}</td>
                        <td contenteditable="true" class="editable customer-address" data-id="{{ $customer->id }}" data-field="address">{{ $customer->address }}</td>
                        <td class="customer-money {{ (float) $customer->points == 0 ? 'is-zero' : '' }}">{{$customer->points}}</td>
                        <td class="customer-money {{ (float) $customer->deposit > 0 ? '' : 'is-zero' }}">@if($customer->deposit > 0) {{ number_format($customer->deposit, 2) }} @else 0 @endif </td>
                        <td class="customer-money {{ (float) $customer->remaining == 0 ? 'is-zero' : '' }}">{{ number_format($customer->remaining, 2)}}</td>
                        <td class="customer-money {{ (float) $customer->deposit < 0 ? '' : 'is-zero' }}">@if($customer->deposit < 0) {{ number_format($customer->deposit * -1, 2) }} @else 0 @endif </td>
                        <td class="customer-money {{ ($customer->deposit - $customer->remaining) < 0 ? '' : 'is-zero' }}">@if($customer->deposit - $customer->remaining < 0 ) {{ number_format(abs($customer->deposit - $customer->remaining), 2) }} @else 0 @endif</td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                    <span class="caret"></span>
                                    <span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                    @if(in_array("customers-edit", $all_permission))
                                        <li>
                                            <a href="{{ route('customer.edit', $customer->id) }}" class="btn btn-link"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}</a>
                                        </li>
                                    @endif

                                    @if(in_array("payments-add", $all_permission))
                                        <li>
                                            <button type="button" data-id="{{$customer->id}}" data-phone_number="{{$customer->phone_number}}" class="deposit btn btn-link" data-toggle="modal" data-target="#depositModal" ><i class="dripicons-plus"></i> {{trans('file.Add Payment')}}</button>
                                        </li>
                                    @endif
                                    @if(in_array("payments-index", $all_permission))
                                        <li>
                                            <button type="button" data-id="{{$customer->id}}" class="getDeposit btn btn-link"><i class="fa fa-money"></i> {{trans('file.View Payment')}}</button>
                                        </li>
                                    @endif
                                    <li>
                                        <a href="{{ route('customer.awaiting.payments', $customer->id) }}" class="btn btn-link"><i class="fa fa-eye"></i> Awaiting Payment</a>
                                    </li>
                                    <li class="divider"></li>
                                    @if(in_array("customers-delete", $all_permission))
                                        {{ Form::open(['route' => ['customer.destroy', $customer->id], 'method' => 'DELETE'] ) }}
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
        </div>
        </div>
    </section>

    <div id="importCustomer" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                {!! Form::open(['route' => 'customer.import', 'method' => 'post', 'files' => true]) !!}
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Import Customer')}}</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                    <p>{{trans('file.The correct column order is')}} (customer_group*, name*, company_name, email, phone_number*, address*, city*, state, postal_code, country) {{trans('file.and you must follow this')}}.</p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{trans('file.Upload CSV File')}} *</label>
                                {{Form::file('file', array('class' => 'form-control','required'))}}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label> {{trans('file.Sample File')}}</label>
                                <a href="public/sample_file/sample_customer.csv" class="btn btn-info btn-block btn-md"><i class="dripicons-download"></i>  {{trans('file.Download')}}</a>
                            </div>
                        </div>
                    </div>
                    <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary" id="submit-button">
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>

    <div id="depositModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                {!! Form::open(['route' => 'customer.addDeposit', 'method' => 'post']) !!}
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Add Payment')}}</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <input type="hidden" name="customer_id">
                                <label>{{trans('file.Amount')}} *</label>
                                <input type="number" name="amount" step="any" value="" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{trans('file.Payment Method')}} <strong>*</strong> </label>
                                <select class="form-control selectpicker" name="payment_method" onchange='saveValue(this);'>
                                    <option value="1">Cash</option>
                                    {{--                            @if(in_array("JE-method", $all_permission))--}}
                                    {{--                                <option value="2">JE</option>--}}
                                    {{--                            @endif--}}
                                    <option value="3">Momo/Orange</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Depositor </label>
                                <select class="form-control selectpicker" name="depositor_id">
                                    <option value=""> --Choose Any --</option>
                                    @foreach($depositors as $depositor)
                                        <option value="{{ $depositor->id }}">{{ $depositor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{trans('file.MTN Momo Number')}}</label>
                                <input type="number" name="mtn_number" step="any" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{trans('file.Note')}}</label>
                                <textarea name="note" rows="4" class="form-control"></textarea>
                            </div>
                        </div>
                    </div>
                    <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary" id="submit-button">
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>

    <div id="view-deposit" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title">{{trans('file.All Payment')}}</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    <table class="table table-hover deposit-list">
                        <thead>
                        <tr>
                            <th>{{trans('file.date')}}</th>
                            <th>{{trans('file.Amount')}}</th>
                            <th>{{trans('file.Payment Method')}}</th>
                            <th>{{trans('file.Status')}}</th>
                            <th>{{trans('file.Note')}}</th>
                            <th>{{trans('file.Created By')}}</th>
                            <th>{{trans('file.action')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="edit-deposit" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Update Payment')}}</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    {!! Form::open(['route' => 'customer.updateDeposit', 'method' => 'post']) !!}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{trans('file.Amount')}} *</label>
                                <input type="number" name="amount" step="any" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{trans('file.Payment Method')}} <strong>*</strong> </label>
                                <select class="form-control selectpicker" name="payment_method" id="edit_payment_method" onchange='saveValue(this);'>
                                    <option value="1">Cash</option>
                                    {{--                            @if(in_array("JE-method", $all_permission))--}}
                                    {{--                                <option value="2">JE</option>--}}
                                    {{--                            @endif--}}
                                    <option value="3">Momo/Orange</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{trans('file.Note')}}</label>
                                <textarea name="note" rows="4" class="form-control"></textarea>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="deposit_id">
                    <button type="submit" class="btn btn-primary">{{trans('file.update')}}</button>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>

<script type="text/javascript">
    $("ul#people").siblings('a').attr('aria-expanded','true');
    $("ul#people").addClass("show");
    $("ul#people #customer-list-menu").addClass("active");

        function confirmDelete() {
            if (confirm("Are you sure want to delete?")) {
                return true;
            }
            return false;
        }

        var customer_id = [];
        var user_verified = <?php echo json_encode(env('USER_VERIFIED')) ?>;
        var all_permission = <?php echo json_encode($all_permission) ?>;

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $(".deposit").on("click", function() {
            var id = $(this).data('id').toString();
            var phone_number = ($(this).data('phone_number') || '').toString();
            $("#depositModal input[name='customer_id']").val(id);
            $("#depositModal input[name='mtn_number']").val(phone_number);
        });

        $(".daterangepicker-field").daterangepicker({
            callback: function(startDate, endDate, period){
                var start_date = startDate.format('YYYY-MM-DD');
                var end_date = endDate.format('YYYY-MM-DD');
                var title = start_date + ' To ' + end_date;
                $(this).val(title);
                $(".product-report-filter input[name=start_date]").val(start_date);
                $(".product-report-filter input[name=end_date]").val(end_date);
            }
        });

        $(".getDeposit").on("click", function() {
            var id = $(this).data('id').toString();
            $.get('customer/getDeposit/' + id)
                .done(function(data) {
                    var $tbody = $(".deposit-list tbody");
                    if (!$tbody.length) {
                        $(".deposit-list").append('<tbody></tbody>');
                        $tbody = $(".deposit-list tbody");
                    }
                    $tbody.empty();

                    if (!data[0] || !data[0].length) {
                        $tbody.append('<tr><td colspan="7" class="text-center text-muted">No payments recorded.</td></tr>');
                        $("#view-deposit").modal('show');
                        return;
                    }

                    $.each(data[0], function(index){
                        var cols = '';
                        cols += '<td>' + data[1][index] + '</td>';
                        cols += '<td>' + data[2][index] + '</td>';
                        if(data[7][index] == 1) {
                            cols += '<td>Cash</td>';
                        } else if(data[7][index] == 2){
                            cols += '<td>JE</td>';
                        } else {
                            cols += '<td>Momo/MTN</td>';
                        }
                        if(data[6][index] == 1) {
                            cols += '<td>Completed</td>';
                        } else if(data[6][index] == 2){
                            cols += '<td>Rejected</td>';
                        } else {
                            cols += '<td>Pending</td>';
                        }
                        if(data[3][index])
                            cols += '<td>' + data[3][index] + '</td>';
                        else
                            cols += '<td>N/A</td>';
                        cols += '<td>' + data[4][index] + '<br>' + data[5][index] + '</td>';
                        cols += '<td><div class="btn-group"><button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans("file.action")}}<span class="caret"></span><span class="sr-only">Toggle Dropdown</span></button><ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu"><li class="divider"></li>{{ Form::open(['route' => 'customer.deleteDeposit', 'method' => 'post'] ) }}<li><input type="hidden" name="id" value="' + data[0][index] + '" /> <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> {{trans("file.delete")}}</button></li>{{ Form::close() }}</ul></div></td>';
                        $tbody.append('<tr>' + cols + '</tr>');
                    });
                    $("#view-deposit").modal('show');
                })
                .fail(function() {
                    alert('Could not load payment history. Please try again.');
                });
        });

        $("table.deposit-list").on("click", ".edit-btn", function(event) {
            var id = $(this).data('id');
            var method = $(this).data('method');
            console.log(method);
            var rowindex = $(this).closest('tr').index();
            var amount = $('table.deposit-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('td:nth-child(2)').text();
            var note = $('table.deposit-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('td:nth-child(5)').text();
            if(note == 'N/A')
                note = '';

            $('#edit-deposit input[name="deposit_id"]').val(id);
            $('#edit-deposit input[name="amount"]').val(amount);
            $('#edit-deposit textarea[name="note"]').val(note);
            $("#edit-deposit select[name='payment_method']").val(method);
            $('#view-deposit').modal('hide');
        });


        // Handle text fields
        $(document).on("blur", ".editable", function() {
            let id = $(this).data("id");
            let field = $(this).data("field");
            let value = $(this).text().trim();

            $.ajax({
                url: "{{ route('customer.inlineUpdate') }}",
                type: "POST",
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    id: id,
                    field: field,
                    value: value
                },
                success: function(res) {
                    $("#ajax-message").html(`
                        <div class="alert alert-success alert-dismissible text-center">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            ${res.message}
                        </div>
                    `);
                }
            });
        });

        // Handle dropdown change
        $(document).on("change", ".editable-select", function() {
            let id = $(this).data("id");
            let field = $(this).data("field");
            let value = $(this).val();

            $.ajax({
                url: "{{ route('customer.inlineUpdate') }}",
                type: "POST",
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    id: id,
                    field: field,
                    value: value
                },
                success: function(res) {
                    $("#ajax-message").html(`
                        <div class="alert alert-success alert-dismissible text-center">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            ${res.message}
                        </div>
                    `);
                }
            });
        });


        var table = $('#customer-table').DataTable( {
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
                    "orderable": false,
                    'targets': [0, 10]
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
                },
                {
                    extend: 'csv',
                    text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                    exportOptions: {
                        columns: ':visible:Not(.not-exported)',
                        rows: ':visible'
                    },
                },
                {
                    extend: 'print',
                    text: '<i title="print" class="fa fa-print"></i>',
                    exportOptions: {
                        columns: ':visible:Not(.not-exported)',
                        rows: ':visible'
                    },
                },
                {
                    text: '<i title="delete" class="dripicons-cross"></i>',
                    className: 'buttons-delete',
                    action: function ( e, dt, node, config ) {
                        if(user_verified == '1') {
                            customer_id.length = 0;
                            $(':checkbox:checked').each(function(i){
                                if(i){
                                    customer_id[i-1] = $(this).closest('tr').data('id');
                                }
                            });
                            if(customer_id.length && confirm("Are you sure want to delete?")) {
                                $.ajax({
                                    type:'POST',
                                    url:'customer/deletebyselection',
                                    data:{
                                        customerIdArray: customer_id
                                    },
                                    success:function(data){
                                        alert(data);
                                    }
                                });
                                dt.rows({ page: 'current', selected: true }).remove().draw(false);
                            }
                            else if(!customer_id.length)
                                alert('No customer is selected!');
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
        } );

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        if(all_permission.indexOf("customers-delete") == -1)
            $('.buttons-delete').addClass('d-none');

        $("#export").on("click", function(e){
            e.preventDefault();
            var customer = [];
            $(':checkbox:checked').each(function(i){
                customer[i] = $(this).val();
            });
            $.ajax({
                type:'POST',
                url:'/exportcustomer',
                data:{
                    customerArray: customer
                },
                success:function(data){
                    alert('Exported to CSV file successfully! Click Ok to download file');
                    window.location.href = data;
                }
            });
        });
    </script>
@endsection
