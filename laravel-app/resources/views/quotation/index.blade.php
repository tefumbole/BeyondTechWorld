@extends('layout.main') @section('content')
    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('message') !!}</div>
    @endif
    @if(session()->has('not_permitted'))
        <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
    @endif

    <section>
        <div class="container-fluid">
            @if(in_array("quotes-add", $all_permission))
                <a href="{{route('quotations.create')}}" class="btn btn-info"><i class="dripicons-plus"></i> {{trans('file.Add Quotation')}}</a>
            @endif
            <div class="mt-3">
                @include('quotation.partials.tabs')
            </div>
        </div>
        <div class="table-responsive">
            <table id="quotation-table" class="table quotation-list">
                <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{trans('file.Date')}}</th>
                    <th>{{trans('file.reference')}}</th>
                    <th>{{trans('file.Biller')}}</th>
                    <th>{{trans('file.customer')}}</th>
                    <th>{{trans('file.Supplier')}}</th>
                    <th>{{trans('file.Quotation Status')}}</th>
                    <th>Client comment</th>
                    <th>{{trans('file.grand total')}}</th>
                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($lims_quotation_all as $key=>$quotation)
                        <?php
                        $status = \App\Quotation::statusLabel($quotation->quotation_status);
                        $st = (int) $quotation->quotation_status;
                        ?>
                    <tr class="quotation-link" data-quotation='["{{date($general_setting->date_format, strtotime($quotation->created_at->toDateString()))}}", "{{$quotation->reference_no}}", "{{$status}}", "{{@$quotation->biller->name}}", "{{@$quotation->biller->company_name}}","{{@$quotation->biller->email}}", "{{@$quotation->biller->phone_number}}", "{{@$quotation->biller->address}}", "{{@$quotation->biller->city}}", "{{@$quotation->customer->name}}", "{{@$quotation->customer->phone_number}}", "{{@$quotation->customer->address}}", "{{@$quotation->customer->city}}", "{{$quotation->id}}", "{{$quotation->total_tax}}", "{{$quotation->total_discount}}", "{{$quotation->total_price}}", "{{$quotation->order_tax}}", "{{$quotation->order_tax_rate}}", "{{$quotation->order_discount}}", "{{$quotation->shipping_cost}}", "{{$quotation->grand_total}}", {!! json_encode(trim(preg_replace('/\s+/', ' ', \App\Support\BookingNoteFormatter::forPlainText($quotation->note)))) !!}, "{{@$quotation->user->name}}", "{{@$quotation->user->email}}"]'>
                        <td>{{$key}}</td>
                        <td>{{ date($general_setting->date_format, strtotime($quotation->created_at->toDateString())) . ' '. $quotation->created_at->toTimeString() }}</td>
                        <td>{{ $quotation->reference_no }}</td>
                        <td>{{ optional($quotation->biller)->name }}</td>
                        <td>{{ optional($quotation->customer)->name }}</td>
                        @if($quotation->supplier_id)
                            <td>{{ optional($quotation->supplier)->name }}</td>
                        @else
                            <td>N/A</td>
                        @endif
                        <td>
                            @if($st === \App\Quotation::STATUS_APPROVED)
                                <div class="badge badge-success">{{$status}}</div>
                            @elseif($st === \App\Quotation::STATUS_REJECTED)
                                <div class="badge badge-danger">{{$status}}</div>
                            @elseif($st === \App\Quotation::STATUS_AWAITING)
                                <div class="badge badge-warning">{{$status}}</div>
                            @else
                                <div class="badge badge-secondary">{{$status}}</div>
                            @endif
                        </td>
                        <td style="max-width:220px;white-space:normal;">
                            {{ $quotation->client_comment ?: '—' }}
                            @if($st === \App\Quotation::STATUS_APPROVED && $quotation->clientSignatureUrl())
                                <div class="mt-1">
                                    <a href="{{ $quotation->clientSignatureUrl() }}" target="_blank" rel="noopener">View signature</a>
                                    @if($quotation->client_signed_at)
                                        <br><small class="text-muted">Signed {{ $quotation->client_signed_at->format('Y-m-d H:i') }}</small>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>{{ number_format($quotation->grand_total, 2, '.', ',') }}</td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                    <span class="caret"></span>
                                    <span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                    <li>
                                        <button type="button" class="btn btn-link view"><i class="fa fa-eye"></i>  {{trans('file.View')}}</button>
                                    </li>
                                    @if(in_array("quotes-edit", $all_permission) && in_array($st, [\App\Quotation::STATUS_PENDING, \App\Quotation::STATUS_AWAITING, \App\Quotation::STATUS_REJECTED, \App\Quotation::STATUS_APPROVED], true))
                                        <li>
                                            <a class="btn btn-link" href="{{ route('quotations.edit', $quotation->id) }}"><i class="dripicons-document-edit"></i> {{trans('file.edit')}} / resend</a>
                                        </li>
                                    @endif
                                    @if(in_array("quotes-add", $all_permission))
                                        <li>
                                            <a class="btn btn-link" href="{{ route('quotation.clone', $quotation->id) }}"><i class="dripicons-copy"></i> Clone</a>
                                        </li>
                                    @endif
                                    @if(in_array($st, [\App\Quotation::STATUS_PENDING, \App\Quotation::STATUS_AWAITING, \App\Quotation::STATUS_REJECTED, \App\Quotation::STATUS_APPROVED], true))
                                        <li>
                                            {{ Form::open(['route' => ['quotation.resend_approval', $quotation->id], 'method' => 'POST', 'style' => 'display:inline'] ) }}
                                            <button type="submit" class="btn btn-link" onclick="return confirm('Send / resend approval link to the client via WhatsApp?');"><i class="fa fa-whatsapp"></i> Resend for approval</button>
                                            {{ Form::close() }}
                                        </li>
                                    @endif
                                    @if($st === \App\Quotation::STATUS_APPROVED)
                                        <li>
                                            <a class="btn btn-link" href="{{ route('quotation.create_sale', ['id' => $quotation->id]) }}"><i class="fa fa-shopping-cart"></i> {{trans('file.Create Sale')}}</a>
                                        </li>
                                    @endif
                                    <li class="divider"></li>
                                    @if(in_array("quotes-delete", $all_permission))
                                        {{ Form::open(['route' => ['quotations.destroy', $quotation->id], 'method' => 'DELETE'] ) }}
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
                </tfoot>
            </table>
        </div>
    </section>

    @php extract(\App\Support\Letterhead::viewVars(), EXTR_SKIP); @endphp
    <div id="quotation-details" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog {{ !empty($quotationLetterhead) ? 'modal-lg' : '' }}">
            <div class="modal-content quotation-details-sheet">
                <div class="container mt-3 pb-2 {{ !empty($quotationLetterhead) ? '' : 'border-bottom' }}">
                    <div class="row">
                        <div class="col-md-5">
                            <button id="print-btn" type="button" class="btn btn-default btn-sm d-print-none"><i class="dripicons-print"></i></button>
                            {{ Form::open(['route' => 'quotation.sendmail', 'method' => 'post', 'class' => 'sendmail-form'] ) }}
                            <input type="hidden" name="quotation_id">
                            <button class="btn btn-default btn-sm d-print-none"><i class="dripicons-mail"></i></button>
                            {{ Form::close() }}
                            {{ Form::open(['route' => 'quotation.sendwhatsapp', 'method' => 'post', 'class' => 'sendmail-form'] ) }}
                            <input type="hidden" name="quotation_id">
                            <button class="btn btn-default btn-sm d-print-none"><i class="fa fa-whatsapp"></i></button>
                            {{ Form::close() }}
                        </div>
                        <div class="col-md-4"></div>
                        <div class="col-md-3">
                            <button type="button" id="close-btn" data-dismiss="modal" aria-label="Close" class="close d-print-none"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                        </div>
                        @if(!empty($quotationLetterhead))
                            <div class="col-md-12 px-0 quotation-system-header">
                                <img src="{{ $quotationHeaderUrl }}" alt="Header" style="width:100%;display:block;">
                            </div>
                            <div class="col-md-12">
                                <center><h3 class="mt-3 mb-2">Quotation Details</h3></center>
                            </div>
                        @else
                            <div class="col-md-12 text-center">
                                <h3 id="exampleModalLabel" class="modal-title">{{ $general_setting->site_title }}</h3>
                                <i style="font-size: 15px;">Quotation Details</i>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="quotation-details-body" style="position:relative;">
                    @if(!empty($quotationWatermarkUrl))
                        <img src="{{ $quotationWatermarkUrl }}" alt="" class="quotation-system-watermark">
                    @endif
                    <div id="quotation-content" class="modal-body"></div>
                    <br>
                    <table class=" table-bordered product-quotation-list">
                        <tbody></tbody>
                    </table>
                    <div id="quotation-footer" class="modal-body"></div>
                </div>
                @if(!empty($quotationLetterFooter))
                    <img src="{{ $quotationFooterUrl }}" alt="Footer" class="quotation-system-footer" style="width:100%;display:block;">
                @endif
            </div>
        </div>
    </div>
    <style>
        .quotation-system-header { position: relative; }
        .quotation-details-body { position: relative; min-height: 200px; }
        .quotation-system-watermark {
            width: 45%;
            max-width: 280px;
            position: absolute;
            top: 40%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.12;
            pointer-events: none;
            z-index: 0;
        }
        .quotation-details-body .modal-body,
        .quotation-details-body table { position: relative; z-index: 1; }
        @media print {
            .quotation-details-sheet { box-shadow: none !important; border: 0 !important; }
            .quotation-system-footer { page-break-inside: avoid; }
        }
    </style>

    <script type="text/javascript">

        $("ul#quotation").siblings('a').attr('aria-expanded','true');
        $("ul#quotation").addClass("show");
        $("ul#quotation #quotation-list-menu").addClass("active");
        var all_permission = <?php echo json_encode($all_permission) ?>;
        var quotation_id = [];
        var user_verified = <?php echo json_encode(env('USER_VERIFIED')) ?>;

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        function confirmDelete() {
            if (confirm("Are you sure want to delete?")) {
                return true;
            }
            return false;
        }

        $("tr.quotation-link td:not(:first-child, :last-child)").on("click", function(){
            var quotation = $(this).parent().data('quotation');
            quotationDetails(quotation);
        });

        $(".view").on("click", function(){
            var quotation = $(this).parent().parent().parent().parent().parent().data('quotation');
            quotationDetails(quotation);
        });

        $("#print-btn").on("click", function(){
            var divToPrint=document.getElementById('quotation-details');
            var newWin=window.open('','Print-Window');
            newWin.document.open();
            newWin.document.write('<link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css"><style type="text/css">@media print {.modal-dialog { max-width: 1000px;} }</style><body onload="window.print()">'+divToPrint.innerHTML+'</body>');
            newWin.document.close();
            setTimeout(function(){newWin.close();},30);
        });

        $('#quotation-table').DataTable( {
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
                    'targets': [0, 9]
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
                            quotation_id.length = 0;
                            $(':checkbox:checked').each(function(i){
                                if(i){
                                    var quotation = $(this).closest('tr').data('quotation');
                                    quotation_id[i-1] = quotation[13];
                                }
                            });
                            if(quotation_id.length && confirm("Are you sure want to delete?")) {
                                $.ajax({
                                    type:'POST',
                                    url:'quotations/deletebyselection',
                                    data:{
                                        quotationIdArray: quotation_id
                                    },
                                    success:function(data){
                                        alert(data);
                                        dt.rows({ page: 'current', selected: true }).remove().draw(false);
                                    }
                                });

                            }
                            else if(!quotation_id.length)
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
            }
            else {
                $( dt_selector.column( 7 ).footer() ).html(dt_selector.cells( rows, 7, { page: 'current' } ).data().sum().toFixed(2));
            }
        }

        if(all_permission.indexOf("quotes-delete") == -1)
            $('.buttons-delete').addClass('d-none');

        function quotationDetails(quotation){
            $('input[name="quotation_id"]').val(quotation[13]);
            var htmltext = '<strong>{{trans("file.Date")}}: </strong>'+quotation[0]+'<br><strong>{{trans("file.reference")}}: </strong>'+quotation[1]+'<br><strong>{{trans("file.Status")}}: </strong>'+quotation[2]+'<br><br><table class="table"><tr><th><strong>{{trans("file.From")}}:</strong><br>'+quotation[3]+'<br>'+quotation[4]+'<br>'+quotation[5]+'<br>'+quotation[6]+'<br>'+quotation[7]+'<br>'+quotation[8]+'</th><th><div class="float-right"><strong>{{trans("file.To")}}:</strong><br>'+quotation[9]+'<br>'+quotation[10]+'<br>'+quotation[11]+'<br>'+quotation[12]+'</div></th></tr></table>';
            $.get('quotations/product_quotation/' + quotation[13], function(data){
                $(".product-quotation-list tbody").remove();
                var name_code = data[0];
                var qty = data[1];
                var unit_code = data[2];
                var tax = data[3];
                var tax_rate = data[4];
                var discount = data[5];
                var subtotal = data[6];
                var batch_no = data[7];
                var newBody = $("<tbody>");
                var newHead = "<tr> <th>#</th> <th>{{trans('file.product')}}</th> <th>{{trans('file.Batch No')}}</th> <th>Qty</th> <th>{{trans('file.Unit Price')}}</th> <th>{{trans('file.Tax')}}</th> <th>{{trans('file.Discount')}}</th> <th>{{trans('file.Subtotal')}}</th></tr>";
                newBody.append(newHead);
                $.each(name_code, function(index){
                    var newRow = $("<tr>");
                    var cols = '';
                    cols += '<td><strong>' + (index+1) + '</strong></td>';
                    cols += '<td>' + name_code[index] + '</td>';
                    cols += '<td>' + batch_no[index] + '</td>';
                    cols += '<td>' + qty[index] + ' ' + unit_code[index] + '</td>';
                    cols += '<td>' + (subtotal[index] / qty[index]).toLocaleString("en-US") + '</td>';
                    cols += '<td>' + tax[index] + '(' + tax_rate[index] + '%)' + '</td>';
                    cols += '<td>' + discount[index] + '</td>';
                    cols += '<td>' + subtotal[index].toLocaleString("en-US") + '</td>';
                    newRow.append(cols);
                    newBody.append(newRow);
                });

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=5><strong>{{trans("file.Total")}}:</strong></td>';
                cols += '<td>' + quotation[14].toLocaleString("en-US") + '</td>';
                cols += '<td>' + numberWithCommas(quotation[15]) + '</td>';
                cols += '<td>' + numberWithCommas(quotation[16]) + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=7><strong>{{trans("file.Order Tax")}}:</strong></td>';
                cols += '<td>' + quotation[17] + '(' + quotation[18] + '%)' + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=7><strong>{{trans("file.Order Discount")}}:</strong></td>';
                cols += '<td>' + quotation[19].toLocaleString("en-US") + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=7><strong>{{trans("file.Shipping Cost")}}:</strong></td>';
                cols += '<td>' + quotation[20] + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=7><strong>{{trans("file.grand total")}}:</strong></td>';
                cols += '<td>' + numberWithCommas(quotation[21]) + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                $("table.product-quotation-list").append(newBody);
            });
            var noteSafe = $('<div>').text(quotation[22] || '').html();
            var htmlfooter = '<p><strong>{{trans("file.Note")}}:</strong> '+noteSafe+'</p><strong>{{trans("file.Created By")}}:</strong><br>'+quotation[23]+'<br>'+quotation[24];
            $('#quotation-content').html(htmltext);
            $('#quotation-footer').html(htmlfooter);
            $('#quotation-details').modal('show');
        }
    </script>
@endsection
