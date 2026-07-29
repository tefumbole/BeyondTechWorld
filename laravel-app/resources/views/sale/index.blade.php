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
                    <h3 class="text-center">{{trans('file.Sale List')}}</h3>
                </div>
                {!! Form::open(['route' => 'sales.index', 'method' => 'get']) !!}
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
            @if(in_array("sales-add", $all_permission))
                <a href="{{route('sales.create')}}" class="btn btn-info"><i class="dripicons-plus"></i> {{trans('file.Add Sale')}}</a>&nbsp;
                <a href="{{url('sales/sale_by_csv')}}" class="btn btn-primary"><i class="dripicons-copy"></i> {{trans('file.Import Sale')}}</a>
            @endif
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
                    <th>{{trans('file.Sale Status')}}</th>
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
                </tfoot>
            </table>
        </div>
    </section>

    <div id="sale-details" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog modal-lg">
            <div class="modal-content" id="sale-invoice-print">
                <div class="container mt-2 pb-2 border-bottom d-print-none">
                    <div class="row align-items-center">
                        <div class="col-md-9">
                            <button id="print-btn" type="button" class="btn btn-default btn-sm"><i class="dripicons-print"></i></button>
                            {{ Form::open(['route' => 'sale.sendmail', 'method' => 'post', 'class' => 'sendmail-form d-inline'] ) }}
                            <input type="hidden" name="sale_id">
                            <button class="btn btn-default btn-sm"><i class="dripicons-mail"></i></button>
                            {{ Form::close() }}
                            {{ Form::open(['route' => 'sale.sendwhatsapp', 'method' => 'post', 'class' => 'sendmail-form d-inline'] ) }}
                            <input type="hidden" name="sale_id">
                            <button class="btn btn-default btn-sm"><i class="fa fa-whatsapp"></i></button>
                            {{ Form::close() }}
                        </div>
                        <div class="col-md-3 text-right">
                            <button type="button" id="close-btn" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                        </div>
                    </div>
                </div>
                <div class="sale-invoice-body px-3 pb-3">
                    @if($general_setting->invoice_format == 'beyond_a4')
                        <img src="{{url('public/logo', $general_setting->email_header)}}" alt="" class="sale-letter-header" style="display:block;width:100%;max-height:90px;object-fit:contain;margin:0 0 6px;">
                        <img src="{{url('public/logo', $general_setting->email_water_mark)}}" alt="" class="sale-watermark" style="width:42%;position:absolute;left:29%;top:34%;opacity:0.07;pointer-events:none;z-index:0;">
                    @else
                        <h3 class="modal-title text-center">{{$general_setting->site_title}}</h3>
                    @endif
                    <div class="mb-2" style="position:relative;z-index:1;">
                        <div class="text-center"><strong style="font-size:16px;">Sales Invoice</strong></div>
                        <div id="sale-ref-line" style="font-size:12px;line-height:1.45;text-align:left;color:#1f2a44;"></div>
                    </div>
                    <div id="sale-content" class="modal-body p-0" style="position:relative;z-index:1;"></div>
                    <style>
                        table.product-sale-list thead th,
                        table.product-sale-list tbody tr:first-child th {
                            background: #d9ebe1 !important;
                            color: #1f3d32;
                        }
                        table.product-sale-list tbody tr.sale-alt td { background: #f2f8f4; }
                        table.product-sale-list tbody tr.sale-total td { background: #e5f2eb; font-weight: bold; }
                    </style>
                    <table class="table table-sm table-bordered product-sale-list mb-2" style="position:relative;z-index:1;">
                        <tbody></tbody>
                    </table>
                    <div class="text-center text-muted mb-2" style="position:relative;z-index:1;">{{trans('file.Thank you for shopping with us. Please come again')}}</div>
                    <div id="sale-footer" class="modal-body p-0 text-left mb-2" style="position:relative;z-index:1;"></div>
                    <div class="text-center mb-2" style="position:relative;z-index:1;">
                        <div style="margin:0 0 6px;">
                            <img id="sale-qrcode" src="" alt="qrcode" height="56" width="56">
                        </div>
                        <div>
                            <img id="sale-barcode" src="" alt="barcode" height="28" style="max-width:220px;">
                        </div>
                    </div>
                    @if($general_setting->invoice_format == 'beyond_a4')
                        <img src="{{url('public/logo', $general_setting->email_footer)}}" alt="" class="sale-letter-footer" style="display:block;width:100%;max-height:70px;object-fit:contain;margin-top:8px;">
                    @endif
                </div>
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
                    {!! Form::open(['route' => 'sale.add-payment', 'method' => 'post', 'files' => true, 'class' => 'payment-form' ]) !!}
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

                    <input type="hidden" name="sale_id">

                    <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>

    <div id="edit-payment" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Update Payment')}}</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    {!! Form::open(['route' => 'sale.update-payment', 'method' => 'post', 'class' => 'payment-form' ]) !!}
                    <div class="row">
                        <div class="col-md-6">
                            <label>{{trans('file.Recieved Amount')}} *</label>
                            <input type="text" name="edit_paying_amount" class="form-control numkey"  step="any" required>
                        </div>
                        <div class="col-md-6">
                            <label>{{trans('file.Paying Amount')}} *</label>
                            <input type="text" name="edit_amount" class="form-control"  step="any" required>
                        </div>
                        <div class="col-md-6 mt-1">
                            <label>{{trans('file.Change')}} : </label>
                            <p class="change ml-2">0.00</p>
                        </div>
                        <div class="col-md-6 mt-1">
                            <label>{{trans('file.Paid By')}}</label>
                            <select name="edit_paid_by_id" class="form-control selectpicker">
                                <option value="1">Cash</option>
                                <option value="2">Gift Card</option>
                                <option value="3">Credit Card</option>
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
                            @foreach($lims_gift_card_list as $gift_card)
                                <option value="{{$gift_card->id}}">{{$gift_card->card_no}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mt-2">
                        <div class="card-element" class="form-control">
                        </div>
                        <div class="card-errors" role="alert"></div>
                    </div>
                    <div id="edit-cheque">
                        <div class="form-group">
                            <label>{{trans('file.Cheque Number')}} *</label>
                            <input type="text" name="edit_cheque_no" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label> {{trans('file.Account')}}</label>
                        <select class="form-control selectpicker" name="account_id">
                            @foreach($lims_account_list as $account)
                                <option value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{trans('file.Payment Note')}}</label>
                        <textarea rows="3" class="form-control" name="edit_payment_note"></textarea>
                    </div>

                    <input type="hidden" name="payment_id">

                    <button type="submit" class="btn btn-primary">{{trans('file.update')}}</button>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>

    <div id="add-delivery" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Add Delivery')}}</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    {!! Form::open(['route' => 'delivery.store', 'method' => 'post', 'files' => true]) !!}
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>{{trans('file.Delivery Reference')}}</label>
                            <p id="dr"></p>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{trans('file.Sale Reference')}}</label>
                            <p id="sr"></p>
                        </div>
                        <div class="col-md-12 form-group">
                            <label>{{trans('file.Status')}} *</label>
                            <select name="status" required class="form-control selectpicker">
                                <option value="1">{{trans('file.Packing')}}</option>
                                <option value="2">{{trans('file.Delivering')}}</option>
                                <option value="3">{{trans('file.Delivered')}}</option>
                            </select>
                        </div>
                        <div class="col-md-6 mt-2 form-group">
                            <label>{{trans('file.Delivered By')}}</label>
                            <select name="delivered_by_customer_id" class="form-control selectpicker" data-live-search="true" title="Select customer">
                                <option value="">—</option>
                                @foreach(($lims_customer_list ?? []) as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }} @if($c->phone_number)({{ $c->phone_number }})@endif</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="delivered_by" value="">
                        </div>
                        <div class="col-md-6 mt-2 form-group">
                            <label>{{trans('file.Recieved By')}}</label>
                            <select name="received_by_customer_id" class="form-control selectpicker" data-live-search="true" title="Select customer" required>
                                <option value="">—</option>
                                @foreach(($lims_customer_list ?? []) as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }} @if($c->phone_number)({{ $c->phone_number }})@endif</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="recieved_by" value="">
                            <small class="text-muted">A WhatsApp signature link will be sent to this customer.</small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{trans('file.customer')}} *</label>
                            <p id="customer"></p>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{trans('file.Attach File')}}</label>
                            <input type="file" name="file" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{trans('file.Address')}} *</label>
                            <textarea rows="3" name="address" class="form-control" required></textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{trans('file.Note')}}</label>
                            <textarea rows="3" name="note" class="form-control"></textarea>
                        </div>
                    </div>
                    <input type="hidden" name="reference_no">
                    <input type="hidden" name="sale_id">
                    <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">

        $("ul#sale").siblings('a').attr('aria-expanded','true');
        $("ul#sale").addClass("show");
        $("ul#sale #sale-list-menu").addClass("active");
        var public_key = <?php echo json_encode($lims_pos_setting_data->stripe_public_key) ?>;
        var all_permission = <?php echo json_encode($all_permission) ?>;
        var reward_point_setting = <?php echo json_encode($lims_reward_point_setting_data) ?>;
        var sale_id = [];
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
            var printRoot = document.getElementById('sale-invoice-print');
            if (!printRoot) return;
            var newWin = window.open('', 'Print-Window', 'width=900,height=720');
            if (!newWin) {
                alert('Please allow pop-ups to print the invoice.');
                return;
            }
            var css = '<link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css">' +
                '<style type="text/css">' +
                '@page{size:A4;margin:10mm}' +
                'body{margin:10px;background:#fff;font-size:12px;color:#1f2a44}' +
                '.d-print-none{display:none!important}' +
                '.modal-content{border:0;box-shadow:none}' +
                '.sale-letter-header{max-height:90px!important;object-fit:contain}' +
                '.sale-letter-footer{max-height:70px!important;object-fit:contain}' +
                '.sale-watermark{opacity:0.07!important}' +
                'table.product-sale-list{font-size:11px}' +
                'table.product-sale-list th,table.product-sale-list td{padding:4px 6px}' +
                '</style>';
            newWin.document.open();
            newWin.document.write('<!DOCTYPE html><html><head><title>Invoice</title>' + css + '</head><body>' + printRoot.innerHTML + '</body></html>');
            newWin.document.close();
            setTimeout(function(){
                newWin.focus();
                newWin.print();
                setTimeout(function(){ try { newWin.close(); } catch (e) {} }, 400);
            }, 450);
        });

        $(document).on("click", "table.sale-list tbody .add-payment", function() {
            $("#cheque").hide();
            $(".gift-card").hide();
            $(".card-element").hide();
            $('select[name="paid_by_id"]').val(1);
            $('.selectpicker').selectpicker('refresh');
            rowindex = $(this).closest('tr').index();
            deposit = $('table.sale-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.deposit').val();
            var sale_id = $(this).data('id').toString();
            var balance = $('table.sale-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('td:nth-child(10)').text();
            balance = parseFloat(balance.replace(/,/g, ''));
            $('input[name="paying_amount"]').val(balance);
            $('#add-payment input[name="balance"]').val(balance);
            $('input[name="amount"]').val(balance);
            $('input[name="sale_id"]').val(sale_id);
        });

        $(document).on("click", "table.sale-list tbody .get-payment", function(event) {
            rowindex = $(this).closest('tr').index();
            deposit = $('table.sale-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.deposit').val();
            var id = $(this).data('id').toString();
            $.get('sales/getpayment/' + id, function(data) {
                $(".payment-list tbody").remove();
                var newBody = $("<tbody>");
                payment_date  = data[0];
                payment_reference = data[1];
                paid_amount = data[2];
                paying_method = data[3];
                payment_id = data[4];
                payment_note = data[5];
                cheque_no = data[6];
                gift_card_id = data[7];
                change = data[8];
                paying_amount = data[9];
                account_name = data[10];
                account_id = data[11];

                $.each(payment_date, function(index){
                    var newRow = $("<tr>");
                    var cols = '';

                    cols += '<td>' + payment_date[index] + '</td>';
                    cols += '<td>' + payment_reference[index] + '</td>';
                    cols += '<td>' + account_name[index] + '</td>';
                    cols += '<td>' + paid_amount[index] + '</td>';
                    cols += '<td>' + paying_method[index] + '</td>';
                    if(paying_method[index] != 'Paypal')
                        cols += '<td><div class="btn-group"><button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans("file.action")}}<span class="caret"></span><span class="sr-only">Toggle Dropdown</span></button><ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu"><li><button type="button" class="btn btn-link edit-btn" data-id="' + payment_id[index] +'" data-clicked=false data-toggle="modal" data-target="#edit-payment"><i class="dripicons-document-edit"></i> {{trans("file.edit")}}</button></li><li class="divider"></li>{{ Form::open(['route' => 'sale.delete-payment', 'method' => 'post'] ) }}<li><input type="hidden" name="id" value="' + payment_id[index] + '" /> <button type="submit" class="btn btn-link" onclick="return confirmPaymentDelete()"><i class="dripicons-trash"></i> {{trans("file.delete")}}</button></li>{{ Form::close() }}</ul></div></td>';
                    else
                        cols += '<td><div class="btn-group"><button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans("file.action")}}<span class="caret"></span><span class="sr-only">Toggle Dropdown</span></button><ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">{{ Form::open(['route' => 'sale.delete-payment', 'method' => 'post'] ) }}<li><input type="hidden" name="id" value="' + payment_id[index] + '" /> <button type="submit" class="btn btn-link" onclick="return confirmPaymentDelete()"><i class="dripicons-trash"></i> {{trans("file.delete")}}</button></li>{{ Form::close() }}</ul></div></td>';

                    newRow.append(cols);
                    newBody.append(newRow);
                    $("table.payment-list").append(newBody);
                });
                $('#view-payment').modal('show');
            });
        });

        $("table.payment-list").on("click", ".edit-btn", function(event) {
            $(".edit-btn").attr('data-clicked', true);
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

        $('select[name="paid_by_id"]').on("change", function() {
            var id = $(this).val();
            $('input[name="cheque_no"]').attr('required', false);
            $('#add-payment select[name="gift_card_id"]').attr('required', false);
            $(".payment-form").off("submit");
            if(id == 2){
                $(".gift-card").show();
                $(".debit").hide();
                $(".debit").hide();
                $("#cheque").hide();
                $('#add-payment select[name="gift_card_id"]').attr('required', true);
            }
            else if (id == 3) {
                $.getScript( "public/vendor/stripe/checkout.js" );
                // $(".card-element").show();
                $(".debit").show();
                $(".gift-card").hide();
                $("#cheque").hide();
            } else if (id == 4) {
                $("#cheque").show();
                $(".gift-card").hide();
                $(".debit").hide();
                $('input[name="cheque_no"]').attr('required', true);
            } else if (id == 5) {
                $(".debit").hide();
                $(".gift-card").hide();
                $("#cheque").hide();
            } else {
                $(".debit").hide();
                $(".gift-card").hide();
                $("#cheque").hide();
                if(id == 6){
                    if($('#add-payment input[name="amount"]').val() > parseFloat(deposit))
                        alert('Amount exceeds customer deposit! Customer deposit : ' + deposit);
                }
                else if(id==7) {
                    pointCalculation($('#add-payment input[name="amount"]').val());
                }
            }
        });

        $('#add-payment select[name="gift_card_id"]').on("change", function() {
            var id = $(this).val();
            if(expired_date[id] < current_date)
                alert('This card is expired!');
            else if($('#add-payment input[name="amount"]').val() > balance[id]){
                alert('Amount exceeds card balance! Gift Card balance: '+ balance[id]);
            }
        });

        $('input[name="paying_amount"]').on("input", function() {
            $(".change").text(parseFloat( $(this).val() - $('input[name="amount"]').val() ).toFixed(2));
        });

        $('input[name="amount"]').on("input", function() {
            if( $(this).val() > parseFloat($('input[name="paying_amount"]').val()) ) {
                alert('Paying amount cannot be bigger than recieved amount');
                $(this).val('');
            }
            else if( $(this).val() > parseFloat($('input[name="balance"]').val()) ) {
                alert('Paying amount cannot be bigger than due amount');
                $(this).val('');
            }
            $(".change").text(parseFloat($('input[name="paying_amount"]').val() - $(this).val()).toFixed(2));
            var id = $('#add-payment select[name="paid_by_id"]').val();
            var amount = $(this).val();
            if(id == 2){
                id = $('#add-payment select[name="gift_card_id"]').val();
                if(amount > balance[id])
                    alert('Amount exceeds card balance! Gift Card balance: '+ balance[id]);
            }
            else if(id == 6){
                if(amount > parseFloat(deposit))
                    alert('Amount exceeds customer deposit! Customer deposit : ' + deposit);
            }
            else if(id==7) {
                pointCalculation(amount);
            }
        });

        $('select[name="edit_paid_by_id"]').on("change", function() {
            var id = $(this).val();
            $('input[name="edit_cheque_no"]').attr('required', false);
            $('#edit-payment select[name="gift_card_id"]').attr('required', false);
            $(".payment-form").off("submit");
            if(id == 2){
                $(".debit").hide();
                $("#edit-cheque").hide();
                $('.gift-card').show();
                $('#edit-payment select[name="gift_card_id"]').attr('required', true);
            }
            else if (id == 3) {
                $(".edit-btn").attr('data-clicked', true);
                $.getScript( "public/vendor/stripe/checkout.js" );
                // $(".card-element").show();
                $(".debit").show();
                $("#edit-cheque").hide();
                $('.gift-card').hide();
            } else if (id == 4) {
                $("#edit-cheque").show();
                $(".debit").hide();
                $('.gift-card').hide();
                $('input[name="edit_cheque_no"]').attr('required', true);
            } else {
                $(".debit").hide();
                $("#edit-cheque").hide();
                $('.gift-card').hide();
                if(id == 6) {
                    if($('input[name="edit_amount"]').val() > parseFloat(deposit))
                        alert('Amount exceeds customer deposit! Customer deposit : ' + deposit);
                }
                else if(id==7) {
                    pointCalculation($('input[name="edit_amount"]').val());
                }
            }
        });

        $('#edit-payment select[name="gift_card_id"]').on("change", function() {
            var id = $(this).val();
            if(expired_date[id] < current_date)
                alert('This card is expired!');
            else if($('#edit-payment input[name="edit_amount"]').val() > balance[id])
                alert('Amount exceeds card balance! Gift Card balance: '+ balance[id]);
        });

        $('input[name="edit_paying_amount"]').on("input", function() {
            $(".change").text(parseFloat( $(this).val() - $('input[name="edit_amount"]').val() ).toFixed(2));
        });

        $('input[name="edit_amount"]').on("input", function() {
            if( $(this).val() > parseFloat($('input[name="edit_paying_amount"]').val()) ) {
                alert('Paying amount cannot be bigger than recieved amount');
                $(this).val('');
            }
            $(".change").text(parseFloat($('input[name="edit_paying_amount"]').val() - $(this).val()).toFixed(2));
            var amount = $(this).val();
            var id = $('#edit-payment select[name="gift_card_id"]').val();
            if(amount > balance[id]){
                alert('Amount exceeds card balance! Gift Card balance: '+ balance[id]);
            }
            var id = $('#edit-payment select[name="edit_paid_by_id"]').val();
            if(id == 6){
                if(amount > parseFloat(deposit))
                    alert('Amount exceeds customer deposit! Customer deposit : ' + deposit);
            }
            else if(id==7) {
                pointCalculation(amount);
            }
        });

        $(document).on("click", "table.sale-list tbody .add-delivery", function(event) {
            var id = $(this).data('id').toString();
            $.get('delivery/create/'+id, function(data) {
                $('#dr').text(data[0]);
                $('#sr').text(data[1]);

                $('select[name="status"]').val(data[2] || 1);
                $('select[name="delivered_by_customer_id"]').val(data[8] || '');
                $('select[name="received_by_customer_id"]').val(data[9] || '');
                $('input[name="delivered_by"]').val(data[3] || '');
                $('input[name="recieved_by"]').val(data[4] || '');
                $('.selectpicker').selectpicker('refresh');
                $('#customer').text(data[5]);
                $('textarea[name="address"]').val(data[6]);
                $('textarea[name="note"]').val(data[7]);
                $('input[name="reference_no"]').val(data[0]);
                $('input[name="sale_id"]').val(id);
                $('#add-delivery').modal('show');
            });
        });
        $('#add-delivery').on('changed.bs.select', 'select[name="delivered_by_customer_id"]', function () {
            var t = $(this).find('option:selected').text().replace(/\s*\(.*$/,'').trim();
            $('#add-delivery input[name="delivered_by"]').val(t);
        });
        $('#add-delivery').on('changed.bs.select', 'select[name="received_by_customer_id"]', function () {
            var t = $(this).find('option:selected').text().replace(/\s*\(.*$/,'').trim();
            $('#add-delivery input[name="recieved_by"]').val(t);
        });

        function pointCalculation(amount) {
            availablePoints = $('table.sale-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.points').val();
            required_point = Math.ceil(amount / reward_point_setting['per_point_amount']);
            if(required_point > availablePoints) {
                alert('Customer does not have sufficient points. Available points: '+availablePoints+'. Required points: '+required_point);
            }
        }

        var starting_date = $("input[name=starting_date]").val();
        var ending_date = $("input[name=ending_date]").val();
        var warehouse_id = $("#warehouse_id").val();

        $('#sale-table').DataTable( {
            "processing": true,
            "serverSide": true,
            "ajax":{
                url:"sales/sale-data",
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
                //alert(data);
                $(row).addClass('sale-link');
                $(row).attr('data-sale', data['sale']);
            },
            "columns": [
                {"data": "key"},
                {"data": "date"},
                {"data": "reference_no"},
                {"data": "biller"},
                {"data": "customer"},
                {"data": "sale_status"},
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
                            sale_id.length = 0;
                            $(':checkbox:checked').each(function(i){
                                if(i){
                                    var sale = $(this).closest('tr').data('sale');
                                    sale_id[i-1] = sale[13];
                                }
                            });
                            if(sale_id.length && confirm("Are you sure want to delete?")) {
                                $.ajax({
                                    type:'POST',
                                    url:'sales/deletebyselection',
                                    data:{
                                        saleIdArray: sale_id
                                    },
                                    success:function(data){
                                        alert(data);
                                        //dt.rows({ page: 'current', selected: true }).deselect();
                                        dt.rows({ page: 'current', selected: true }).remove().draw(false);
                                    }
                                });
                            }
                            else if(!sale_id.length)
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

        function parseSaleAmt(v){
            var n = parseFloat(String(v == null ? '' : v).replace(/,/g, '').trim());
            return isNaN(n) ? 0 : n;
        }

        function saleDetails(sale){
            $("#sale-details input[name='sale_id']").val(sale[13]);
            $('#sale-ref-line').html(
                '<strong>{{trans("file.reference")}}:</strong> ' + (sale[1] || '') + '<br>' +
                '<strong>{{trans("file.Date")}}:</strong> ' + (sale[0] || '')
            );

            var refSafe = encodeURIComponent(String(sale[1] || '').replace(/[^A-Za-z0-9\-_]/g, ''));
            $('#sale-barcode').attr('src', '{{ url("sales/barcode") }}/' + refSafe);
            $('#sale-qrcode').attr('src', '{{ url("sales/qrcode") }}/' + sale[13]);

            var customerLines = [sale[9], sale[10], sale[11], sale[12]].filter(function(v){
                return v && String(v).trim() !== '' && String(v).trim().toLowerCase() !== 'null';
            }).join('<br>');

            var htmltext = '<table class="table table-sm table-bordered mb-2" style="font-size:12px;">' +
                '<tr>' +
                '<td style="width:50%;vertical-align:top;background:#f8f9fc;">' +
                '<strong>{{trans("file.reference")}}:</strong> ' + sale[1] + '<br>' +
                '<strong>{{trans("file.Date")}}:</strong> ' + sale[0] + '<br>' +
                '<strong>{{trans("file.Warehouse")}}:</strong> ' + sale[27] + '<br>' +
                '<strong>{{trans("file.Sale Status")}}:</strong> ' + sale[2] +
                '</td>' +
                '<td style="width:50%;vertical-align:top;background:#f8f9fc;">' +
                '<strong>{{trans("file.To")}}</strong><br>' + customerLines +
                '</td>' +
                '</tr></table>';

            $.get('sales/product_sale/' + sale[13], function(data){
                $(".product-sale-list tbody").remove();
                var name_code = data[0];
                var qty = data[1];
                var unit_code = data[2];
                var tax = data[3];
                var tax_rate = data[4];
                var discount = data[5];
                var subtotal = data[6];
                var batch_no = data[7];
                var newBody = $("<tbody>");
                var newHead = "<tr> <th>#</th> <th>{{trans('file.product')}}</th> <th>{{trans('file.Qty')}}</th> <th>{{trans('file.Unit Price')}}</th> <th>{{trans('file.Tax')}}</th> <th>{{trans('file.Discount')}}</th> <th>{{trans('file.Subtotal')}}</th> </tr>";
                newBody.append(newHead);
                $.each(name_code, function(index){
                    var newRow = $("<tr class='" + (index % 2 === 1 ? 'sale-alt' : '') + "'>");
                    var cols = '';
                    cols += '<td><strong>' + (index+1) + '</strong></td>';
                    cols += '<td>' + name_code[index] + '</td>';
                    cols += '<td>' + qty[index] + '</td>';
                    cols += '<td>' + numberWithCommas(subtotal[index] / qty[index]) +'</td>';
                    cols += '<td>' + tax[index] + '(' + tax_rate[index] + '%)' + '</td>';
                    cols += '<td>' + discount[index] + '</td>';
                    cols += '<td>' + numberWithCommas(subtotal[index]) + '</td>';
                    newRow.append(cols);
                    newBody.append(newRow);
                });

                var newRow = $("<tr class='sale-total'>");
                var cols = '';
                cols += '<td colspan=4 style="text-align:right;"><strong>{{trans("file.Total")}}:</strong></td>';
                cols += '<td>' + numberWithCommas(sale[14]) + '</td>';
                cols += '<td>' + numberWithCommas(sale[15]) + '</td>';
                cols += '<td>' + numberWithCommas(sale[16]) + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                if (parseSaleAmt(sale[17]) > 0) {
                    newRow = $("<tr>");
                    cols = '<td colspan=6><strong>{{trans("file.Order Tax")}}:</strong></td>';
                    cols += '<td>' + sale[17] + '(' + sale[18] + '%)' + '</td>';
                    newRow.append(cols);
                    newBody.append(newRow);
                }

                if (parseSaleAmt(sale[19]) > 0) {
                    newRow = $("<tr>");
                    cols = '<td colspan=6><strong>{{trans("file.Order Discount")}}:</strong></td>';
                    cols += '<td>' + sale[19] + '</td>';
                    newRow.append(cols);
                    newBody.append(newRow);
                }

                if (sale[28] && parseSaleAmt(sale[29]) > 0) {
                    newRow = $("<tr>");
                    cols = '<td colspan=6><strong>{{trans("file.Coupon Discount")}} ['+sale[28]+']:</strong></td>';
                    cols += '<td>' + sale[29] + '</td>';
                    newRow.append(cols);
                    newBody.append(newRow);
                }

                if (parseSaleAmt(sale[20]) > 0) {
                    newRow = $("<tr>");
                    cols = '<td colspan=6><strong>{{trans("file.Shipping Cost")}}:</strong></td>';
                    cols += '<td>' + sale[20] + '</td>';
                    newRow.append(cols);
                    newBody.append(newRow);
                }

                newRow = $("<tr>");
                cols = '<td colspan=6><strong>{{trans("file.grand total")}}:</strong></td>';
                cols += '<td>' + numberWithCommas(sale[21]) + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                newRow = $("<tr>");
                cols = '<td colspan=6><strong>Amount Paid:</strong></td>';
                cols += '<td>' + numberWithCommas(sale[22]) + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var pendingAmt = Math.max(0, parseSaleAmt(sale[21]) - parseSaleAmt(sale[22]));
                newRow = $("<tr>");
                cols = '<td colspan=6><strong>Amount Pending:</strong></td>';
                cols += '<td>' + numberWithCommas(pendingAmt) + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                $("table.product-sale-list").append(newBody);
            });

            var saleNote = String(sale[23] || '').replace(/^<br>|<br>$/g, '').trim();
            var staffNote = String(sale[24] || '').replace(/^<br>|<br>$/g, '').trim();
            var htmlfooter = '';
            if (saleNote && saleNote.toLowerCase() !== 'null') {
                htmlfooter += '<p class="mb-1"><strong>{{trans("file.Sale Note")}}:</strong> ' + saleNote + '</p>';
            }
            if (staffNote && staffNote.toLowerCase() !== 'null') {
                htmlfooter += '<p class="mb-1"><strong>{{trans("file.Staff Note")}}:</strong> ' + staffNote + '</p>';
            }
            htmlfooter += '<p class="mb-0 text-left" style="font-size:12px;line-height:1.4;text-align:left;"><strong>{{trans("file.Created By")}}:</strong> ' + sale[25] + (sale[26] ? '<br>' + sale[26] : '') + '</p>';
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
