@extends('layout.main') @section('content')

@if($errors->has('name'))
<div class="alert alert-danger alert-dismissible text-center">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ $errors->first('name') }}</div>
@endif
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<section>
    <style>
        tr{
            cursor: pointer;
        }
    </style>
    <div class="container-fluid">
        <a href="{{route('asset.sale.all')}}" class="btn btn-warning"><i class="dripicons-plus"></i> Sale Asset</a>
    </div>
    <div class="table-responsive">
        <table id="role-table" class="table">
            <thead>
            <tr>
                <th class="not-exported"></th>
                <th>Barcode</th>
                <th>Image</th>
                <th>Name</th>
                <th>Buyer Name</th>
                <th>Price</th>
                <th>Remarks</th>
                <th>Sale Date</th>
                <th class="not-exported">{{trans('file.action')}}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data as $key=>$item)

                @php
                if(isset($item->AssetSaleDetails->asset_sale_id)) {
                    $sale = \App\AssetSale::find($item->AssetSaleDetails->asset_sale_id);
                }

                @endphp
                <tr data-id="{{$item->id}}" class="clickable-row" data-href="{{ route('asset.show', $item->id) }}">
                    <td>{{ $item->id }}</td>
                    <td>{{ $item->serial_no }}</td>
                    @if($item->image)
                        <td> <img src="{{url('public/images/assets',$item->image)}}" height="80" width="80">
                        </td>
                    @else
                        <td>No Image</td>
                    @endif
                    <td>{{ $item->name }}</td>
                    <td>{{ @$item->AssetSaleDetails->buyer_name}}</td>
                    <td>{{ number_format(@$item->AssetSaleDetails->price, 2)}}</td>
                    <td>{{ @$item->AssetSaleDetails->remark}}</td>
                    <td>{{ @$sale->date}}</td>
                    @if(isset($item->AssetSaleDetails->asset_sale_id))
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default">
                                <li><a href="{{ route('asset.sale.show', $sale->id) }}" class="btn btn-link"><i class="fa fa-eye"></i> {{trans('file.View')}}</a></li>
                                <li class="divider"></li>
                                <li><a href="{{ route('asset.sale.edit', $sale->id) }}" class="btn btn-link"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}</a></li>
                                <li><a href="{{ route('asset.sale.letter', $sale->id) }}" class="btn btn-link"><i class="fa fa-shopping-cart"></i> Generate Sale Certificate</a></li>
                            </ul>
                        </div>
                    </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>


<script type="text/javascript">
    $("ul#assets").siblings('a').attr('aria-expanded','true');
    $("ul#assets").addClass("show");
    $("ul#assets #assets-sale-menu").addClass("active");

    // $(document).ready(function($) {
    //     $('.clickable-row td:not(:last-child)').click(function () {
    //         window.location = $(this).closest('tr').data("href");
    //     });
    // });

    $(document).ready(function() {
    $(document).on('click', '.open-EditroleDialog', function() {
        var url = "role/"
        var id = $(this).data('id').toString();
        url = url.concat(id).concat("/edit");

        $.get(url, function(data) {
            $("input[name='name']").val(data['name']);
            $("textarea[name='description']").val(data['description']);
            $("input[name='role_id']").val(data['id']);
        });
    });

    $('#role-table').DataTable( {
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
                'targets': [0, 3]
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
                extend: 'pdfHtml5',
                text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>',
                orientation : 'landscape',
                pageSize : 'LEGAL',
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
                extend: 'colvis',
                text: '<i title="column visibility" class="fa fa-eye"></i>',
                columns: ':gt(0)'
            },
        ],
    } );
});
</script>

@endsection
