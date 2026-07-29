@extends('layout.main') @section('content')
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('message') !!}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

@php
    $filter = $filter ?? 'all';
    $letterhead = \App\Support\Letterhead::ensureSynced();
@endphp

<section>
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link {{ $filter === 'all' ? 'active' : '' }}" href="{{ route('delivery.index') }}">Delivery List</a></li>
        <li class="nav-item"><a class="nav-link {{ $filter === 'pending' ? 'active' : '' }}" href="{{ route('delivery.index', ['filter' => 'pending']) }}">Delivery Sign.. Pending</a></li>
        <li class="nav-item"><a class="nav-link {{ $filter === 'signed' ? 'active' : '' }}" href="{{ route('delivery.index', ['filter' => 'signed']) }}">Signed Delivery</a></li>
    </ul>

    <div class="table-responsive">
        <table id="delivery-table" class="table">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{trans('file.Delivery Reference')}}</th>
                    <th>{{trans('file.Sale Reference')}}</th>
                    <th>{{trans('file.customer')}}</th>
                    <th>{{trans('file.Address')}}</th>
                    <th>{{trans('file.Status')}}</th>
                    <th>Signature</th>
                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lims_delivery_all as $key=>$delivery)
                <?php
                    $customer = optional($delivery->sale)->customer;
                    $status = $delivery->statusLabel();
                    $sigLabel = $delivery->isSigned() ? 'Signed' : 'Pending';
                    $verifyUrl = \App\Support\DeliveryVerifyQr::scanUrl($delivery);
                    $barcode = \DNS2D::getBarcodePNG($verifyUrl, 'QRCODE');
                    $linBarcode = \DNS1D::getBarcodePNG($delivery->reference_no, 'C128');
                    $deliveryPayload = [
                        date($general_setting->date_format, strtotime($delivery->created_at->toDateString())),
                        $delivery->reference_no,
                        optional($delivery->sale)->reference_no,
                        $status,
                        $delivery->id,
                        optional($customer)->name,
                        optional($customer)->phone_number,
                        $delivery->address,
                        optional($customer)->city,
                        $delivery->note,
                        optional($delivery->user)->name,
                        $delivery->delivered_by,
                        $delivery->recieved_by,
                        optional($delivery->user)->email,
                        $delivery->clientSignatureUrl(),
                        $sigLabel,
                        optional($delivery->client_signed_at)->format('d-m-Y H:i'),
                    ];
                ?>
                <tr class="delivery-link" data-id="{{$delivery->id}}" data-signed="{{ $delivery->isSigned() ? 1 : 0 }}" data-barcode="{{$barcode}}" data-linbarcode="{{$linBarcode}}" data-delivery='@json($deliveryPayload)'>
                    <td>{{$key}}</td>
                    <td>{{ $delivery->reference_no }}</td>
                    <td>{{ optional($delivery->sale)->reference_no }}</td>
                    <td>{{ optional($customer)->name }}</td>
                    <td>{{ $delivery->address }}</td>
                    <td>
                        @if($delivery->status == 1)<div class="badge badge-info">{{$status}}</div>
                        @elseif($delivery->status == 2)<div class="badge badge-primary">{{$status}}</div>
                        @else<div class="badge badge-success">{{$status}}</div>@endif
                    </td>
                    <td>
                        @if($delivery->isSigned())
                            <span class="badge badge-success">Signed</span>
                        @else
                            <span class="badge badge-warning">Pending</span>
                        @endif
                    </td>
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown">{{trans('file.action')}} <span class="caret"></span></button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li>
                                    <button type="button" data-id="{{$delivery->id}}" class="open-EditCategoryDialog btn btn-link"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}</button>
                                </li>
                                @if(!$delivery->isSigned())
                                <li>
                                    {{ Form::open(['route' => ['delivery.resend_signature', $delivery->id], 'method' => 'post']) }}
                                    <button type="submit" class="btn btn-link"><i class="fa fa-whatsapp"></i> Resend Signature Link</button>
                                    {{ Form::close() }}
                                </li>
                                @endif
                                <li class="divider"></li>
                                {{ Form::open(['route' => ['delivery.delete', $delivery->id], 'method' => 'post'] ) }}
                                <li>
                                  <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> {{trans('file.delete')}}</button>
                                </li>
                                {{ Form::close() }}
                            </ul>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<div id="delivery-details" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-lg">
      <div class="modal-content" id="delivery-invoice-print">
        <div class="container mt-2 pb-2 border-bottom d-print-none">
            <div class="row align-items-center">
                <div class="col-md-9">
                    <button id="print-btn" type="button" class="btn btn-default btn-sm"><i class="dripicons-print"></i> {{trans('file.Print')}}</button>
                    {{ Form::open(['route' => 'delivery.sendMail', 'method' => 'post', 'class' => 'd-inline'] ) }}
                        <input type="hidden" name="delivery_id">
                        <button class="btn btn-default btn-sm"><i class="dripicons-mail"></i> {{trans('file.Email')}}</button>
                    {{ Form::close() }}
                    <span id="whatsapp-wrap" style="display:none;">
                        {{ Form::open(['route' => 'delivery.sendWhatsapp', 'method' => 'post', 'class' => 'd-inline'] ) }}
                            <input type="hidden" name="delivery_id" id="wa-delivery-id">
                            <button class="btn btn-default btn-sm"><i class="fa fa-whatsapp"></i> WhatsApp</button>
                        {{ Form::close() }}
                    </span>
                </div>
                <div class="col-md-3 text-right">
                    <button type="button" data-dismiss="modal" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
            </div>
        </div>
        <div class="px-3 pb-3" style="position:relative;">
            @if(!empty($letterhead['has_header']))
                <img src="{{ $letterhead['header_url'] }}" alt="" style="display:block;width:100%;max-height:90px;object-fit:fill;margin:0 0 6px;">
            @endif
            <div class="mb-2">
                <div class="text-center"><strong style="font-size:16px;" id="delivery-doc-title">Delivery Note</strong></div>
                <div id="delivery-ref-line" style="font-size:12px;line-height:1.45;text-align:left;color:#1f2a44;"></div>
            </div>
            <table class="table table-sm table-bordered mb-2" id="delivery-content"><tbody></tbody></table>
            <style>
                table.product-delivery-list thead th { background:#d9ebe1 !important; color:#1f3d32; }
                table.product-delivery-list tbody tr:nth-child(even) td { background:#f2f8f4; }
            </style>
            <table class="table table-sm table-bordered product-delivery-list mb-2">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Location</th>
                        <th>Qty</th>
                        <th>Expiry</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            <div id="delivery-footer" class="mb-2" style="text-align:left;"></div>
            <div id="delivery-signature" class="mb-2"></div>
            <div id="delivery-codes-bottom" class="text-center mb-2">
                <div style="margin:0 0 6px;"><img id="delivery-qrcode" src="" alt="qrcode" height="56" width="56"></div>
                <div><img id="delivery-barcode" src="" alt="barcode" height="28" style="max-width:220px;"></div>
            </div>
            @if(!empty($letterhead['has_footer']))
                <img src="{{ $letterhead['footer_url'] }}" alt="" style="display:block;width:100%;max-height:70px;object-fit:fill;margin-top:8px;">
            @endif
        </div>
      </div>
    </div>
</div>

<div id="edit-delivery" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{trans('file.Update Delivery')}}</h5>
                <button type="button" data-dismiss="modal" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                {!! Form::open(['route' => 'delivery.update', 'method' => 'post', 'files' => true]) !!}
                <div class="row">
                    <div class="col-md-6 form-group"><label>{{trans('file.Delivery Reference')}}</label><p id="dr"></p></div>
                    <div class="col-md-6 form-group"><label>{{trans('file.Sale Reference')}}</label><p id="sr"></p></div>
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
                        <select name="delivered_by_customer_id" class="form-control selectpicker" data-live-search="true">
                            <option value="">—</option>
                            @foreach(($lims_customer_list ?? []) as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="delivered_by">
                    </div>
                    <div class="col-md-6 mt-2 form-group">
                        <label>{{trans('file.Recieved By')}}</label>
                        <select name="received_by_customer_id" class="form-control selectpicker" data-live-search="true">
                            <option value="">—</option>
                            @foreach(($lims_customer_list ?? []) as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="recieved_by">
                    </div>
                    <div class="col-md-6 form-group"><label>{{trans('file.customer')}} *</label><p id="customer"></p></div>
                    <div class="col-md-6 form-group"><label>{{trans('file.Attach File')}}</label><input type="file" name="file" class="form-control"></div>
                    <div class="col-md-6 form-group"><label>{{trans('file.Address')}} *</label><textarea rows="3" name="address" class="form-control" required></textarea></div>
                    <div class="col-md-6 form-group"><label>{{trans('file.Note')}}</label><textarea rows="3" name="note" class="form-control"></textarea></div>
                    <div class="col-md-12 form-group">
                        <label><input type="checkbox" name="resend_signature" value="1"> Resend signature link on update</label>
                    </div>
                </div>
                <input type="hidden" name="reference_no">
                <input type="hidden" name="delivery_id">
                <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $("ul#sale").siblings('a').attr('aria-expanded','true');
    $("ul#sale").addClass("show");
    @if(($filter ?? 'all') === 'pending')
        $("ul#sale #delivery-pending-menu").addClass("active");
    @elseif(($filter ?? 'all') === 'signed')
        $("ul#sale #delivery-signed-menu").addClass("active");
    @else
        $("ul#sale #delivery-menu").addClass("active");
    @endif

    var delivery_id = [];
    var user_verified = <?php echo json_encode(env('USER_VERIFIED')) ?>;

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    $("#print-btn").on("click", function(){
        var printRoot = document.getElementById('delivery-invoice-print');
        if (!printRoot) return;
        var newWin = window.open('', 'Print-Window', 'width=900,height=720');
        if (!newWin) { alert('Please allow pop-ups to print.'); return; }
        var css = '<link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css">' +
            '<style>@page{size:A4;margin:10mm}body{margin:10px;font-size:12px}.d-print-none{display:none!important}</style>';
        newWin.document.open();
        newWin.document.write('<!DOCTYPE html><html><head><title>Delivery</title>'+css+'</head><body>'+printRoot.innerHTML+'</body></html>');
        newWin.document.close();
        setTimeout(function(){ newWin.focus(); newWin.print(); setTimeout(function(){ try{newWin.close();}catch(e){} }, 400); }, 450);
    });

    function confirmDelete() { return confirm("Are you sure want to delete?"); }

    $("tr.delivery-link td:not(:first-child, :last-child)").on("click", function() {
        var tr = $(this).parent();
        deliveryDetails(tr.data('delivery'), tr.data('barcode'), tr.data('linbarcode'), tr.data('signed'));
    });

    function deliveryDetails(delivery, qr, barcode, signed) {
        $('input[name="delivery_id"]').val(delivery[4]);
        $('#wa-delivery-id').val(delivery[4]);
        $('#delivery-ref-line').html(
            '<strong>{{trans("file.reference")}}:</strong> ' + (delivery[1] || '') + '<br>' +
            '<strong>{{trans("file.Date")}}:</strong> ' + (delivery[0] || '')
        );
        $('#delivery-doc-title').text(signed == 1 || signed === true ? 'Signed Delivery' : 'Delivery Note');
        $('#whatsapp-wrap').toggle(signed == 1 || signed === true);
        $('#delivery-qrcode').attr('src', 'data:image/png;base64,' + qr);
        $('#delivery-barcode').attr('src', 'data:image/png;base64,' + barcode);

        $("#delivery-content tbody").remove();
        var newBody = $("<tbody>");
        var rows = '<tr><td style="width:50%;background:#f8f9fc;"><strong>Date:</strong> '+delivery[0]+
            '<br><strong>Delivery Reference:</strong> '+delivery[1]+
            '<br><strong>Sale Reference:</strong> '+delivery[2]+
            '<br><strong>Status:</strong> '+delivery[3]+
            '<br><strong>Signature:</strong> '+(delivery[15] || '')+
            '</td><td style="width:50%;background:#f8f9fc;"><strong>Customer</strong><br>'+delivery[5]+
            '<br>'+delivery[6]+'<br>'+delivery[7]+(delivery[8] ? ', '+delivery[8] : '')+'</td></tr>';
        newBody.append(rows);
        $("table#delivery-content").append(newBody);

        $.get('delivery/product_delivery/' + delivery[4], function(data) {
            $(".product-delivery-list tbody").remove();
            var code = data[0], description = data[1], qty = data[2], location = data[3];
            var batch_expiry = typeof data[5] !== "undefined" ? data[5] : null;
            var newBody = $("<tbody>");
            $.each(code, function(index) {
                var loc = (location && location[index] && location[index][0] != null) ? location[index][0] : '—';
                if (loc === 'null') loc = '—';
                var exp = '—';
                if (batch_expiry && batch_expiry[index] && batch_expiry[index][0]) exp = batch_expiry[index][0];
                var q = (qty && qty[index] && typeof qty[index][0] !== 'undefined') ? qty[index][0] : '';
                newBody.append('<tr><td><strong>'+(index+1)+'</strong></td><td>'+code[index]+'</td><td>'+description[index]+'</td><td>'+loc+'</td><td>'+q+'</td><td>'+exp+'</td></tr>');
            });
            $("table.product-delivery-list").append(newBody);
        });

        var htmlfooter = '<p style="text-align:left;margin:0;"><strong>Created By:</strong> '+(delivery[10]||'')+(delivery[13] ? '<br>'+delivery[13] : '')+'</p>';
        htmlfooter += '<p style="text-align:left;margin:6px 0 0;"><strong>Delivered By:</strong> '+(delivery[11]||'—')+'</p>';
        htmlfooter += '<p style="text-align:left;margin:6px 0 0;"><strong>Received By:</strong> '+(delivery[12]||'—')+'</p>';
        if (delivery[9]) htmlfooter += '<p style="text-align:left;margin:6px 0 0;"><strong>Note:</strong> '+delivery[9]+'</p>';
        $('#delivery-footer').html(htmlfooter);

        if (delivery[14]) {
            $('#delivery-signature').html('<div style="border:1px solid #dfe3ec;padding:8px;"><strong>Client signature</strong><br><img src="'+delivery[14]+'" alt="sig" style="max-height:80px;background:#fff;"><div class="text-muted" style="font-size:11px;">Signed '+(delivery[16]||'')+'</div></div>');
        } else {
            $('#delivery-signature').html('');
        }
        $('#delivery-details').modal('show');
    }

    $(document).ready(function() {
        $('.open-EditCategoryDialog').on('click', function(){
          var id = $(this).data('id').toString();
          $.get('delivery/' + id + '/edit', function(data){
                $('#dr').text(data[0]);
                $('#sr').text(data[1]);
                $('select[name="status"]').val(data[2]);
                $('select[name="delivered_by_customer_id"]').val(data[8] || '');
                $('select[name="received_by_customer_id"]').val(data[9] || '');
                $('input[name="delivered_by"]').val(data[3]);
                $('input[name="recieved_by"]').val(data[4]);
                $('.selectpicker').selectpicker('refresh');
                $('#customer').text(data[5]);
                $('textarea[name="address"]').val(data[6]);
                $('textarea[name="note"]').val(data[7]);
                $('input[name="reference_no"]').val(data[0]);
                $('input[name="delivery_id"]').val(id);
          });
          $("#edit-delivery").modal('show');
        });
    });

    $('#delivery-table').DataTable({
        "order": [],
        'language': {
            'lengthMenu': '_MENU_ {{trans("file.records per page")}}',
            "info": '<small>{{trans("file.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
            "search": '{{trans("file.Search")}}',
            'paginate': { 'previous': '<i class="dripicons-chevron-left"></i>', 'next': '<i class="dripicons-chevron-right"></i>' }
        },
        'columnDefs': [
            { "orderable": false, 'targets': [0, 7] },
            {
                'render': function(data, type){
                    if(type === 'display'){ data = '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>'; }
                    return data;
                },
                'checkboxes': { 'selectRow': true, 'selectAllRender': '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>' },
                'targets': [0]
            }
        ],
        'select': { style: 'multi', selector: 'td:first-child'},
        'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
        dom: '<"row"lfB>rtip',
        buttons: [
            { extend: 'pdf', text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>', exportOptions: { columns: ':visible:Not(.not-exported)', rows: ':visible' } },
            { extend: 'csv', text: '<i title="export to csv" class="fa fa-file-text-o"></i>', exportOptions: { columns: ':visible:Not(.not-exported)', rows: ':visible' } },
            { extend: 'print', text: '<i title="print" class="fa fa-print"></i>', exportOptions: { columns: ':visible:Not(.not-exported)', rows: ':visible' } },
            { extend: 'colvis', text: '<i title="column visibility" class="fa fa-eye"></i>', columns: ':gt(0)' },
        ],
    });
</script>
@endsection
