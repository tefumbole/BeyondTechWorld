@extends('layout.main') @section('content')
    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('message') !!}</div>
    @endif
    @if(session()->has('not_permitted'))
        <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
    @endif
    <section>
        <div class="container-fluid">
            <div class="card">
                <div class="card-header mt-2">
                    <h3 class="text-center">Booking List</h3>
                </div>
                {!! Form::open(['route' => 'online.booking.index', 'method' => 'get']) !!}
                <div class="row mb-3">
                    <div class="col-md-4 offset-md-2 mt-3">
                        <div class="d-flex">
                            <label class="">{{trans('file.Date')}} &nbsp;</label>
                            <div class="">
                                <div class="input-group">
                                    <input type="text" class="daterangepicker-field form-control" value="{{$starting_date}} To {{$ending_date}}" required />
                                    <input type="hidden" name="starting_date" value="{{$starting_date}}" />
                                    <input type="hidden" name="ending_date" value="{{$ending_date}}" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mt-3 @if(\Auth::user()->role_id > 2){{'d-none'}}@endif">
                        <div class="d-flex">
                            <label class="">{{trans('file.Warehouse')}} &nbsp;</label>
                            <div class="">
                                <select id="warehouse_id" name="warehouse_id" class="selectpicker form-control" data-live-search="true" >
                                    <option value="0">{{trans('file.All Warehouse')}}</option>
                                    @foreach($lims_warehouse_list as $warehouse)
                                        @if($warehouse->id == $warehouse_id)
                                            <option selected value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                                        @else
                                            <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mt-3">
                        <div class="form-group">
                            <button class="btn btn-primary" id="filter-btn" type="submit">{{trans('file.submit')}}</button>
                        </div>
                    </div>
                </div>
                {!! Form::close() !!}
            </div>
{{--            @if(in_array("sales-add", $all_permission))--}}
{{--                <a href="{{route('sales.create')}}" class="btn btn-info"><i class="dripicons-plus"></i> {{trans('file.Add Sale')}}</a>&nbsp;--}}
{{--                <a href="{{url('sales/sale_by_csv')}}" class="btn btn-primary"><i class="dripicons-copy"></i> {{trans('file.Import Sale')}}</a>--}}
{{--            @endif--}}
        </div>
        <div class="table-responsive">
            <table id="sale-table" class="table sale-list" style="width: 100%">
                <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{trans('file.Date')}}</th>
                    <th>{{trans('file.reference')}}</th>
                    <th>{{trans('file.Biller')}}</th>
                    <th>{{trans('file.customer')}}</th>
                    <th>Payment Method</th>
                    <th>Booking Status</th>
                    <th>{{trans('file.Payment Status')}}</th>
                    <th>{{trans('file.grand total')}}</th>
                    <th>{{trans('file.Paid')}}</th>
                    <th>{{trans('file.Due')}}</th>
                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
                </thead>

                <tfoot class="tfoot active">
                <th></th>
                <th>{{trans('file.Total')}}</th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                </tfoot>
            </table>
        </div>
    </section>

    <div id="sale-details" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                <div class="container mt-3 pb-2 border-bottom">
                    <div class="row">
                        <div class="col-md-3">
                            <button id="print-btn" type="button" class="btn btn-default btn-sm d-print-none"><i class="dripicons-print"></i></button>

                            {{ Form::open(['route' => 'booking.sendmail', 'method' => 'post', 'class' => 'sendmail-form'] ) }}
                            <input type="hidden" name="booking_id">
                            <button class="btn btn-default btn-sm d-print-none"><i class="dripicons-mail"></i></button>
                            {{ Form::close() }}
                            {{ Form::open(['route' => 'booking.sendwhatsapp', 'method' => 'post', 'class' => 'sendmail-form'] ) }}
                            <input type="hidden" name="booking_id">
                            <button class="btn btn-default btn-sm d-print-none"><i class="fa fa-whatsapp"></i></button>
                            {{ Form::close() }}
                        </div>
                        @if($general_setting->invoice_format != 'beyond_a4')
                            <div class="col-md-6">
                                <h3 id="exampleModalLabel" class="modal-title text-center container-fluid">{{$general_setting->site_title}}</h3>
                            </div>
                            <div class="col-md-3">
                                <button type="button" id="close-btn" data-dismiss="modal" aria-label="Close" class="close d-print-none"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                            </div>
                            <div class="col-md-12 text-center">
                                <i style="font-size: 15px;">Booking Details</i>
                            </div>
                        @else
                            <div class="col-md-6">
                            </div>
                            <div class="col-md-3">
                                <button type="button" id="close-btn" data-dismiss="modal" aria-label="Close" class="close d-print-none"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                            </div>
                            <div class="col-md-12">
                                <img src="{{url('public/logo', $general_setting->email_header)}}" style=" width: 100%;">
                                <img src="{{url('public/logo', $general_setting->email_water_mark)}}" style="width: 20%; position: absolute; top: 330%; right: 330px; opacity: 0.3;">
                            </div>
                            <div class="col-md-12 text-center">
                                <i style="font-size: 15px;">Booking Details</i>
                            </div>
                        @endif
                    </div>
                </div>
                <div id="sale-content" class="modal-body">
                </div>
                <br>
                <table class=" table-bordered product-sale-list">

                    <tbody>
                    </tbody>

                </table>
                <div style="margin-top:10px">
                    <div  style="text-align: center;"><h3>{{trans('file.Thank you for shopping with us. Please come again')}}</h3></div>
                    <div style="text-align: center;">
                        <?php echo '<img style="margin-top:10px;" src="data:image/png;base64,' . DNS1D::getBarcodePNG('posr-20221129-055423', 'C128') . '" width="300" alt="barcode"   />';?>
                        <br>
                        <?php echo '<img style="margin-top:10px;" src="data:image/png;base64,' . DNS2D::getBarcodePNG('posr-20221129-055423', 'QRCODE') . '" alt="barcode"   />';?>
                    </div>
                </div>
                <div id="sale-footer" class="modal-body"></div>
                @if($general_setting->invoice_format == 'beyond_a4')
                    <img src="{{url('public/logo', $general_setting->email_footer)}}" style=" width: 100%;">
                @endif
            </div>
        </div>
    </div>

    <div id="view-payment" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title">{{trans('file.All')}} {{trans('file.Payment')}}</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    <table class="table table-hover payment-list">
                        <thead>
                        <tr>
                            <th>{{trans('file.date')}}</th>
                            <th>{{trans('file.reference')}}</th>
                            <th>{{trans('file.Account')}}</th>
                            <th>{{trans('file.Amount')}}</th>
                            <th>{{trans('file.Paid By')}}</th>
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

    <div id="add-payment" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Add Payment')}}</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    {!! Form::open(['route' => 'booking.add-payment', 'method' => 'post', 'files' => true, 'class' => 'payment-form' ]) !!}
                    <div class="row">
                        <input type="hidden" name="balance">
                        <div class="col-md-6">
                            <label>{{trans('file.Due Amount')}} *</label>
                            <input type="text" name="paying_amount" class="form-control numkey" step="any" required>
                        </div>
                        <div class="col-md-6">
                            <label>{{trans('file.Paying Amount')}} *</label>
                            <input type="text" id="amount" name="amount" class="form-control"  step="any" required>
                        </div>
                        <div class="col-md-6 mt-1">
                            <label>{{trans('file.Change')}} : </label>
                            <p class="change ml-2">0.00</p>
                        </div>
                        <div class="col-md-6 mt-1">
                            <label>{{trans('file.Paid By')}}</label>
                            <select name="paid_by_id" class="form-control">
                                <option value="1">Cash</option>
                                <option value="2">Gift Card</option>
{{--                                <option value="3">Credit Card</option>--}}
                                @if(in_array("JE-method", $all_permission))
                                    <option value="3">JE</option>
                                @endif
                                <option value="4">Cheque</option>
                                <option value="5">Paypal</option>
                                <option value="6">Deposit</option>
                                @if($lims_reward_point_setting_data->is_active)
                                    <option value="7">Points</option>
                                @endif
                            </select>
                        </div>
                    </div>
                    <div class="gift-card form-group">
                        <label> {{trans('file.Gift Card')}} *</label>
                        <select id="gift_card_id" name="gift_card_id" class="selectpicker form-control" data-live-search="true"   title="Select Gift Card...">
                            @php
                                $balance = [];
                                $expired_date = [];
                            @endphp
                            @foreach($lims_gift_card_list as $gift_card)
                                    <?php
                                    $balance[$gift_card->id] = $gift_card->amount - $gift_card->expense;
                                    $expired_date[$gift_card->id] = $gift_card->expired_date;
                                    ?>
                                <option value="{{$gift_card->id}}">{{$gift_card->card_no}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mt-2">
                        <div class="card-element" class="form-control">
                        </div>
                        <div class="card-errors" role="alert"></div>
                    </div>
                    <div id="cheque">
                        <div class="form-group">
                            <label>{{trans('file.Cheque Number')}} *</label>
                            <input type="text" name="cheque_no" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label> {{trans('file.Account')}}</label>
                        <select class="form-control selectpicker" name="account_id" data-live-search="true">
                            @foreach($lims_account_list as $account)
                                @if($account->is_default)
                                    <option selected value="{{$account->id}}">{{$account->name}} / {{$account->account_no}} - {{$account->departments->code}}</option>
                                @else
                                    <option value="{{$account->id}}">{{$account->name}} / {{$account->account_no}} - {{$account->departments->code}}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group debit">
                        <label>{{trans('file.Debit')}}</label>
                        <select name="account_id_debit" class="form-control selectpicker" data-live-search="true">
                            @foreach($lims_account_list as $account)
                                @if($account->is_default_debit)
                                    <option selected value="{{$account->id}}">{{$account->name}} / {{$account->account_no}} - {{$account->departments->code}}</option>
                                @else
                                    <option value="{{$account->id}}">{{$account->name}} / {{$account->account_no}} - {{$account->departments->code}}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{trans('file.Payment Note')}}</label>
                        <textarea rows="3" class="form-control" name="payment_note"></textarea>
                    </div>

                    <input type="hidden" name="booking_id">

                    <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">

        $("ul#order").siblings('a').attr('aria-expanded','true');
        $("ul#order").addClass("show");
        $("ul#order #online-booking-index-menu").addClass("active");

        var public_key = <?php echo json_encode($lims_pos_setting_data->stripe_public_key) ?>;
        var all_permission = <?php echo json_encode($all_permission) ?>;
        var reward_point_setting = <?php echo json_encode($lims_reward_point_setting_data) ?>;
        var booking_id = [];
        var user_verified = <?php echo json_encode(env('USER_VERIFIED')) ?>;

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $(".daterangepicker-field").daterangepicker({
            callback: function(startDate, endDate, period){
                var starting_date = startDate.format('YYYY-MM-DD');
                var ending_date = endDate.format('YYYY-MM-DD');
                var title = starting_date + ' To ' + ending_date;
                $(this).val(title);
                $('input[name="starting_date"]').val(starting_date);
                $('input[name="ending_date"]').val(ending_date);
            }
        });

        $('.selectpicker').selectpicker('refresh');

        var balance = <?php echo json_encode($balance) ?>;
        var expired_date = <?php echo json_encode($expired_date) ?>;
        var current_date = <?php echo json_encode(date("Y-m-d")) ?>;
        var payment_date = [];
        var payment_reference = [];
        var paid_amount = [];
        var paying_method = [];
        var payment_id = [];
        var payment_note = [];
        var account = [];
        var deposit;

        $(".gift-card").hide();
        $(".debit").hide();
        $(".card-element").hide();
        $("#cheque").hide();
        $('#view-payment').modal('hide');

        $(document).on("click", "tr.sale-link td:not(:first-child, :last-child)", function() {
            var sale = $(this).parent().data('sale');
            saleDetails(sale);
        });

        $(document).on("click", ".view", function(){
            var sale = $(this).parent().parent().parent().parent().parent().data('sale');
            saleDetails(sale);
        });

        $("#print-btn").on("click", function(){
            var divToPrint=document.getElementById('sale-details');
            var newWin=window.open('','Print-Window');
            newWin.document.open();
            newWin.document.write('<link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css"><style type="text/css">@media print {.modal-dialog { max-width: 1000px;} }</style><body onload="window.print()">'+divToPrint.innerHTML+'</body>');
            newWin.document.close();
            setTimeout(function(){newWin.close();},30);
        });


        $("table.payment-list").on("click", ".edit-btn", function(event) {
            $(".edit-btn").attr('data-clicked', true);
            $(".card-element").hide();
            $(".card-element").hide();
            $("#edit-cheque").hide();
            $('.gift-card').hide();
            $('#edit-payment select[name="edit_paid_by_id"]').prop('disabled', false);
            var id = $(this).data('id').toString();
            $.each(payment_id, function(index){
                if(payment_id[index] == parseFloat(id)){
                    $('input[name="payment_id"]').val(payment_id[index]);
                    $('#edit-payment select[name="account_id"]').val(account_id[index]);
                    if(paying_method[index] == 'Cash')
                        $('select[name="edit_paid_by_id"]').val(1);
                    else if(paying_method[index] == 'Gift Card'){
                        $('select[name="edit_paid_by_id"]').val(2);
                        $('#edit-payment select[name="gift_card_id"]').val(gift_card_id[index]);
                        $('.gift-card').show();
                        $('#edit-payment select[name="edit_paid_by_id"]').prop('disabled', true);
                    }
                    else if(paying_method[index] == 'Credit Card'){
                        $('select[name="edit_paid_by_id"]').val(3);
                        $.getScript( "public/vendor/stripe/checkout.js" );
                        // $(".card-element").show();
                        $(".debit").show();
                        $('#edit-payment select[name="edit_paid_by_id"]').prop('disabled', true);
                    }
                    else if(paying_method[index] == 'Cheque'){
                        $('select[name="edit_paid_by_id"]').val(4);
                        $("#edit-cheque").show();
                        $('input[name="edit_cheque_no"]').val(cheque_no[index]);
                        $('input[name="edit_cheque_no"]').attr('required', true);
                    }
                    else if(paying_method[index] == 'Deposit')
                        $('select[name="edit_paid_by_id"]').val(6);
                    else if(paying_method[index] == 'Points'){
                        $('select[name="edit_paid_by_id"]').val(7);
                    }

                    $('.selectpicker').selectpicker('refresh');
                    $("#payment_reference").html(payment_reference[index]);
                    $('input[name="edit_paying_amount"]').val(paying_amount[index]);
                    $('#edit-payment .change').text(change[index]);
                    $('input[name="edit_amount"]').val(paid_amount[index]);
                    $('textarea[name="edit_payment_note"]').val(payment_note[index]);
                    return false;
                }
            });
            $('#view-payment').modal('hide');
        });

        var starting_date = $("input[name=starting_date]").val();
        var ending_date = $("input[name=ending_date]").val();
        var warehouse_id = $("#warehouse_id").val();

        $('#sale-table').DataTable( {
            "processing": true,
            "serverSide": true,
            "ajax":{
                url:"/bookings/sale-data-online",
                data:{
                    all_permission: all_permission,
                    starting_date: starting_date,
                    ending_date: ending_date,
                    warehouse_id: warehouse_id
                },
                dataType: "json",
                type:"post"
            },
            "createdRow": function( row, data, dataIndex ) {
                console.log(data);
                $(row).addClass('sale-link');
                $(row).attr('data-sale', data['sale']);
            },
            "columns": [
                {"data": "key"},
                {"data": "date"},
                {"data": "reference_no"},
                {"data": "biller"},
                {"data": "customer"},
                {"data": "paying_method"},
                {"data": "booking_status"},
                {"data": "payment_status"},
                {"data": "grand_total"},
                {"data": "paid_amount"},
                {"data": "due"},
                {"data": "options"},
            ],
            'language': {

                'lengthMenu': '_MENU_ {{trans("file.records per page")}}',
                "info":      '<small>{{trans("file.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
                "search":  '{{trans("file.Search")}}',
                'paginate': {
                    'previous': '<i class="dripicons-chevron-left"></i>',
                    'next': '<i class="dripicons-chevron-right"></i>'
                }
            },
            order:[['1', 'desc']],
            'columnDefs': [
                {
                    "orderable": false,
                    'targets': [0, 3, 4, 5, 6, 9, 10]
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
                            booking_id.length = 0;
                            $(':checkbox:checked').each(function(i){
                                if(i){
                                    var sale = $(this).closest('tr').data('sale');
                                    booking_id[i-1] = sale[13];
                                }
                            });
                            if(booking_id.length && confirm("Are you sure want to delete?")) {
                                $.ajax({
                                    type:'POST',
                                    url:'/bookings/deletebyselection',
                                    data:{
                                        saleIdArray: booking_id
                                    },
                                    success:function(data){
                                        alert(data);
                                        //dt.rows({ page: 'current', selected: true }).deselect();
                                        dt.rows({ page: 'current', selected: true }).remove().draw(false);
                                    }
                                });
                            }
                            else if(!booking_id.length)
                                alert('Nothing is selected!');
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

                $( dt_selector.column( 7 ).footer() ).html(dt_selector.cells( rows, 7, { page: 'current' } ).data().sum().toFixed(2));
                $( dt_selector.column( 8 ).footer() ).html(dt_selector.cells( rows, 8, { page: 'current' } ).data().sum().toFixed(2));
                $( dt_selector.column( 9 ).footer() ).html(dt_selector.cells( rows, 9, { page: 'current' } ).data().sum().toFixed(2));
            }
            else {
                $( dt_selector.column( 7 ).footer() ).html(dt_selector.cells( rows, 7, { page: 'current' } ).data().sum().toFixed(2));
                $( dt_selector.column( 8 ).footer() ).html(dt_selector.cells( rows, 8, { page: 'current' } ).data().sum().toFixed(2));
                $( dt_selector.column( 9 ).footer() ).html(dt_selector.cells( rows, 9, { page: 'current' } ).data().sum().toFixed(2));
            }
        }

        function saleDetails(sale){
            $("#sale-details input[name='booking_id']").val(sale[13]);

            var htmltext = '<strong>{{trans("file.Date")}}: </strong>'+sale[0]+'<br><strong>{{trans("file.reference")}}: </strong>'+sale[1]+'<br><strong>{{trans("file.Warehouse")}}: </strong>'+sale[27]+'<br><strong>Booking Status: </strong>'+sale[2]+'<br><br><div class="row"><div class="col-md-6"><strong>{{trans("file.From")}}:</strong><br>'+sale[3]+'<br>'+sale[4]+'<br>'+sale[5]+'<br>'+sale[6]+'<br>'+sale[7]+'<br>'+sale[8]+'</div><div class="col-md-6"><div class="float-right"><strong>{{trans("file.To")}}:</strong><br>'+sale[9]+'<br>'+sale[10]+'<br>'+sale[11]+'<br>'+sale[12]+'</div></div></div>';
            $.get('/bookings/product_sale/' + sale[13], function(data){
                $(".product-sale-list tbody").remove();
                var name_code = data[0];
                var qty = data[1];
                var unit_code = data[2];
                var tax = data[3];
                var tax_rate = data[4];
                var discount = data[5];
                var subtotal = data[6];
                var batch_no = data[7];
                var start = data[7];
                var end = data[8];
                var newBody = $("<tbody>");
                var newHead = "<tr> <th>#</th> <th>{{trans('file.product')}}</th> <th>{{trans('file.Batch No')}}</th> <th>{{trans('file.Qty')}}</th> <th>{{trans('file.Unit')}}</th> <th>{{trans('file.Unit Price')}}</th>  <th>Book</th> <th>Return</th> <th>{{trans('file.Tax')}}</th> <th>{{trans('file.Discount')}}</th><th>{{trans('file.Subtotal')}}</th> </tr>";
                newBody.append(newHead);
                $.each(name_code, function(index){
                    var newRow = $("<tr>");
                    var cols = '';
                    cols += '<td><strong>' + (index+1) + '</strong></td>';
                    cols += '<td>' + name_code[index] + '</td>';
                    cols += '<td>' + batch_no[index] + '</td>';
                    cols += '<td>' + qty[index] + '</td>';
                    cols += '<td>' + unit_code[index] + '</td>';
                    cols += '<td>' + numberWithCommas(subtotal[index] / qty[index]) + '</td>';
                    cols += '<td>' + start[index] + '</td>';
                    cols += '<td>' + end[index] + '</td>';
                    cols += '<td>' + tax[index] + '(' + tax_rate[index] + '%)' + '</td>';
                    cols += '<td>' + discount[index] + '</td>';
                    cols += '<td>' + numberWithCommas(subtotal[index]) + '</td>';
                    newRow.append(cols);
                    newBody.append(newRow);
                });

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=8><strong>{{trans("file.Total")}}:</strong></td>';
                cols += '<td>' + sale[14] + '</td>';
                cols += '<td>' + sale[15] + '</td>';
                // cols = '<td></td>';
                // cols = '<td></td>';
                cols += '<td>' + numberWithCommas(sale[16]) + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=10><strong>{{trans("file.Order Tax")}}:</strong></td>';
                cols += '<td>' + sale[17] + '(' + sale[18] + '%)' + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=10><strong>{{trans("file.Order Discount")}}:</strong></td>';
                cols += '<td>' + sale[19] + '</td>';
                newRow.append(cols);
                newBody.append(newRow);
                if(sale[28]) {
                    var newRow = $("<tr>");
                    cols = '';
                    cols += '<td colspan=10><strong>{{trans("file.Coupon Discount")}} ['+sale[28]+']:</strong></td>';
                    cols += '<td>' + sale[29] + '</td>';
                    newRow.append(cols);
                    newBody.append(newRow);
                }

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=10><strong>{{trans("file.Shipping Cost")}}:</strong></td>';
                cols += '<td>' + sale[20] + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=10><strong>{{trans("file.grand total")}}:</strong></td>';
                cols += '<td>' + numberWithCommas(sale[21]) + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=10><strong>{{trans("file.Paid Amount")}}:</strong></td>';
                cols += '<td>' + numberWithCommas(sale[22]) + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=10><strong>{{trans("file.Due")}}:</strong></td>';
                cols += '<td>' + numberWithCommas(sale[21] - sale[22]) + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                $("table.product-sale-list").append(newBody);
            });
            var htmlfooter = '<p><strong>{{trans("file.Sale Note")}}:</strong> '+sale[23]+'</p><p><strong>{{trans("file.Staff Note")}}:</strong> '+sale[24]+'</p><strong>{{trans("file.Created By")}}:</strong><br>'+sale[25]+'<br>'+sale[26];
            $('#sale-content').html(htmltext);
            $('#sale-footer').html(htmlfooter);
            $('#sale-details').modal('show');
        }

        $(document).on('submit', '.payment-form', function(e) {
            if( $('input[name="paying_amount"]').val() < parseFloat($('#amount').val()) ) {
                alert('Paying amount cannot be bigger than recieved amount');
                $('input[name="amount"]').val('');
                $(".change").text(parseFloat( $('input[name="paying_amount"]').val() - $('#amount').val() ).toFixed(2));
                e.preventDefault();
            }
            else if( $('input[name="edit_paying_amount"]').val() < parseFloat($('input[name="edit_amount"]').val()) ) {
                alert('Paying amount cannot be bigger than recieved amount');
                $('input[name="edit_amount"]').val('');
                $(".change").text(parseFloat( $('input[name="edit_paying_amount"]').val() - $('input[name="edit_amount"]').val() ).toFixed(2));
                e.preventDefault();
            }

            $('#edit-payment select[name="edit_paid_by_id"]').prop('disabled', false);
        });

        if(all_permission.indexOf("sales-delete") == -1)
            $('.buttons-delete').addClass('d-none');

        function confirmDelete() {
            if (confirm("Are you sure want to delete?")) {
                return true;
            }
            return false;
        }

        function confirmPaymentDelete() {
            if (confirm("Are you sure want to delete? If you delete this money will be refunded.")) {
                return true;
            }
            return false;
        }

    </script>
@endsection

@section('scripts')
{{--    <script type="text/javascript" src="https://js.stripe.com/v3/"></script>--}}

@endsection
