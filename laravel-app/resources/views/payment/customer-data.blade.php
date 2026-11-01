@extends('layout.main') @section('content')
    @if($errors->has('title'))
        <div class="alert alert-danger alert-dismissible text-center">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ $errors->first('title') }}</div>
    @endif
    @if($errors->has('image'))
        <div class="alert alert-danger alert-dismissible text-center">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ $errors->first('image') }}</div>
    @endif
    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
    @endif
    @if(session()->has('not_permitted'))
        <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
    @endif

    <section class="container-fluid pay-app">
        @include('payout.partials.style')
        <style>
            .pay-app .dataTables_wrapper { padding: 8px 12px 16px; }
            .pay-app table.dataTable thead th { background: #f8fafc; color: #6f7b91; font-size: 12px; letter-spacing: .04em; text-transform: uppercase; }
            .pay-app .pay-toolbar { justify-content: flex-start; }
            .await-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        </style>

        <div class="pay-toolbar">
            <div class="await-actions">
                <button class="pay-go" type="button" id="awaiting-request">Request for payment</button>
                <button class="pay-danger" type="button" id="awaiting-delete">Delete selected</button>
            </div>
        </div>
        <form id="awaiting-request-form" method="POST" action="{{ route('payment.request_selection') }}" style="display:none">@csrf</form>
        <form id="awaiting-delete-form" method="POST" action="{{ route('payment.delete_selection') }}" style="display:none">@csrf</form>

        <div class="pay-card">
        <div class="pay-card-head"><h2>Awaiting Payment</h2></div>
        <div class="table-responsive">
            <table id="biller-table" class="table sale-list">
                <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{trans('file.Customer Name')}}</th>
                    <th>Unique ID</th>
                    <th>Order Id</th>
                    <th>{{trans('file.Phone Number')}}</th>
                    <th>{{trans('file.Amount')}}</th>
                    <th>{{trans('file.Paid Amount')}}</th>
                    <th>{{trans('file.Due')}}</th>
                    <th>{{trans('file.Payment Status')}}</th>
                    <th>{{trans('file.Date')}}</th>
                    <th>{{trans('file.Add Payment')}}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($sales as $key=>$sale)
                    <tr data-id="{{$sale->id}}">
                        <td>{{$key}}</td>
                        <td>{{ optional($sale->customer)->name }}</td>
                        <td>{{ optional($sale->customer)->unique_id }}</td>
                        <td>{{ $sale->reference_no }}</td>
                        <td>{{ optional($sale->customer)->phone_number }}</td>
                        <td>{{ number_format($sale->grand_total, 2) }}</td>
                        <td>{{ number_format($sale->paid_amount, 2) ?? 0 }}</td>
                        <td>{{ number_format($sale->grand_total - $sale->paid_amount, 2) }}</td>
                        <td><span class="pay-pill pay-pill-wait">{{ $sale->payment_status == 1 ? 'Pending' : 'Due' }}</span></td>
                        <td>{{ $sale->created_at }}</td>
                        <td>
                            <button type="button" class="add-payment btn btn-sm btn-success" data-id="{{ $sale->id }}" data-toggle="modal" data-target="#add-payment"><i class="fa fa-plus"></i>{{ trans('file.Add Payment') }}</button>
                            <button type="button" class="get-payment btn btn-info btn-sm" data-id = "{{ $sale->id }}"><i class="fa fa-money"></i>{{ trans('file.View') }}</button>
                            <a class="get-payment btn btn-warning btn-sm" href="{{ route('sales.edit',$sale->id ) }}"><i class="fa fa-pencil"></i> Edit Sale</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        </div>
    </section>

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
                            <label>Received Amount *</label>
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
                                @if(in_array("JE-method", $all_permission))
                                    <option value="3">JE</option>
                                @endif
                                <option value="6">Deposit</option>
                                <option value="8">Momo/Orange</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group mt-2">
                        <div class="card-element" class="form-control">
                        </div>
                        <div class="card-errors" role="alert"></div>
                    </div>
                    <div class="form-group">
                        <label> {{trans('file.Account')}}</label>
                        <select class="form-control selectpicker" name="account_id" data-live-search="true">
                            @foreach($lims_account_list as $account)
                                @if($account->is_default)
                                    <option selected value="{{$account->id}}">{{$account->name}} / {{$account->account_no}} - {{@$account->departments->code}}</option>
                                @else
                                    <option value="{{$account->id}}">{{$account->name}} / {{$account->account_no}} - {{@$account->departments->code}}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group debit">
                        <label>{{trans('file.Debit')}}</label>
                        <select name="account_id_debit" class="form-control selectpicker" data-live-search="true">
                            @foreach($lims_account_list as $account)
                                @if($account->is_default_debit)
                                    <option selected value="{{$account->id}}">{{$account->name}} / {{$account->account_no}} - {{@$account->departments->code}}</option>
                                @else
                                    <option value="{{$account->id}}">{{$account->name}} / {{$account->account_no}} - {{@$account->departments->code}}</option>
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


    <script type="text/javascript">

        $("ul#payments").siblings('a').attr('aria-expanded','true');
        $("ul#payments").addClass("show");
        $("ul#payments #payment-index-menu").addClass("active");

        $(".debit").hide();

        var user_verified = <?php echo json_encode(env('USER_VERIFIED')) ?>;

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $('select[name="paid_by_id"]').on("change", function() {
            var id = $(this).val();
            $(".debit").hide();
            if(id == 3){
                $(".debit").show(300);
            }
        });

        $( "#select_all" ).on( "change", function() {
            if ($(this).is(':checked')) {
                $("tbody input[type='checkbox']").prop('checked', true);
            }
            else {
                $("tbody input[type='checkbox']").prop('checked', false);
            }
        });

        $(document).on("click", "table.sale-list tbody .add-payment", function() {
            $('select[name="paid_by_id"]').val(1);
            $('.selectpicker').selectpicker('refresh');
            rowindex = $(this).closest('tr').index();
            var sale_id = $(this).data('id').toString();
            var balance = $('table.sale-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('td:nth-child(8)').text();
            balance = parseFloat(balance.replace(/,/g, ''));
            $('input[name="paying_amount"]').val(balance);
            $('#add-payment input[name="balance"]').val(balance);
            $('input[name="amount"]').val(balance);
            $('input[name="sale_id"]').val(sale_id);
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

        });

        $(document).on("click", "table.sale-list tbody .get-payment", function(event) {
            rowindex = $(this).closest('tr').index();
            deposit = $('table.sale-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.deposit').val();
            var id = $(this).data('id').toString();
            $.get('/sales/getpayment/' + id, function(data) {
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
                    newRow.append(cols);
                    newBody.append(newRow);
                    $("table.payment-list").append(newBody);
                });
                $('#view-payment').modal('show');
            });
        });

        $(document).ready(function() {
            $(document).on('click', '.open-EditbrandDialog', function() {
                var url = "/reason/"
                var id = $(this).data('id').toString();
                url = url.concat(id).concat("/edit");

                $.get(url, function(data) {
                    $("input[name='reason']").val(data['reason']);
                    $("input[name='reason_id']").val(data['id']);

                });
            });
        });

        var awaitingTable = $('#biller-table').DataTable( {
            "order": [],
            "deferRender": true,
            "pageLength": 10,
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
                    'targets': [0, 1, 3]
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
                        rows: ':visible',
                        stripHtml: false
                    },
                    customize: function(doc) {
                        for (var i = 1; i < doc.content[1].table.body.length; i++) {
                            if (doc.content[1].table.body[i][0].text.indexOf('<img src=') !== -1) {
                                var imagehtml = doc.content[1].table.body[i][0].text;
                                var regex = /<img.*?src=['"](.*?)['"]/;
                                var src = regex.exec(imagehtml)[1];
                                var tempImage = new Image();
                                tempImage.src = src;
                                var canvas = document.createElement("canvas");
                                canvas.width = tempImage.width;
                                canvas.height = tempImage.height;
                                var ctx = canvas.getContext("2d");
                                ctx.drawImage(tempImage, 0, 0);
                                var imagedata = canvas.toDataURL("image/png");
                                delete doc.content[1].table.body[i][0].text;
                                doc.content[1].table.body[i][0].image = imagedata;
                                doc.content[1].table.body[i][0].fit = [30, 30];
                            }
                        }
                    },
                },
                {
                    extend: 'csv',
                    text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                    exportOptions: {
                        columns: ':visible:Not(.not-exported)',
                        rows: ':visible',
                        format: {
                            body: function ( data, row, column, node ) {
                                if (column === 0 && (data.indexOf('<img src=') !== -1)) {
                                    var regex = /<img.*?src=['"](.*?)['"]/;
                                    data = regex.exec(data)[1];
                                }
                                return data;
                            }
                        }
                    },
                },
                {
                    extend: 'print',
                    text: '<i title="print" class="fa fa-print"></i>',
                    exportOptions: {
                        columns: ':visible:Not(.not-exported)',
                        rows: ':visible',
                        stripHtml: false
                    },
                },
                {
                    extend: 'colvis',
                    text: '<i title="column visibility" class="fa fa-eye"></i>',
                    columns: ':gt(0)'
                },
            ],
        } );

        function awaitingIds() {
            var ids = [];
            $('#biller-table tbody input.dt-checkboxes:checked').each(function () {
                var id = $(this).closest('tr').data('id');
                if (id) ids.push(id);
            });
            return ids;
        }
        function postAwaiting(formId, ids) {
            var form = document.getElementById(formId);
            ids.forEach(function (id) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                form.appendChild(input);
            });
            form.submit();
        }
        $('#awaiting-delete').on('click', function () {
            var ids = awaitingIds();
            if (!ids.length) { alert('Select the payments you want to delete.'); return; }
            if (!confirm('Delete ' + ids.length + (ids.length === 1 ? ' payment?' : ' payments?'))) return;
            postAwaiting('awaiting-delete-form', ids);
        });
        $('#awaiting-request').on('click', function () {
            var ids = awaitingIds();
            if (!ids.length) { alert('Select the payments you want to request.'); return; }
            if (!confirm('Send a payment request for ' + ids.length + (ids.length === 1 ? ' sale?' : ' sales?'))) return;
            postAwaiting('awaiting-request-form', ids);
        });

    </script>
@endsection
