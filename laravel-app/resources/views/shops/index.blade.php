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
    <div class="table-responsive">
        <table id="role-table" class="table">
            <thead>
            <tr>
                <th>Select</th>
                <th>Shop Nane</th>
                <th>Vendor Nane</th>
                <th>Vendor Phone</th>
                <th>Status</th>
                <th>{{trans('file.Date')}}</th>
                @if(auth()->user()->role_id == 1)
                    <th>{{trans('file.Action')}}</th>
                @endif
            </tr>
            </thead>
            <tbody>
            @foreach($data as $key=>$item)
                <tr data-id="{{$item->id}}" class="clickable-row" style="cursor: pointer" data-href="{{ route('shop.show', $item->id) }}">
                    <td><input type="checkbox" class="checkbox-options" name="ids[{{$item->id}}]"></td>
                    <td>{{ $item->company_name }}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->phone }}</td>
                    @if($item->is_active == 0)
                        <td><span class="badge badge-warning">In Active</span></td>
                    @elseif($item->is_active == 1)
                        <td><span class="badge badge-success">Active</span></td>
                    @endif
                    <td>{{ $item->created_at->format('d-M, Y')}}</td>

                    @if(auth()->user()->role_id == 1)
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                    <span class="caret"></span>
                                    <span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default">
                                    <li>
                                        <a href="{{ route('shop.products', $item->id) }}" class="btn btn-link"><i class="dripicons-card"></i> Products </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('shop.orders', $item->id) }}" class="btn btn-link"><i class="dripicons-document"></i> Orders </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('shop.payments', $item->id) }}" class="btn btn-link"><i class="fa fa-money"></i> Payment Request </a>
                                    </li>
                                    @if(in_array("shops-edit", $all_permission))
                                        <li>
                                            <a href="{{ route('shop.edit', $item->id) }}" class="btn btn-link"><i class="fa fa-wrench"></i> Shop Settings</a>
                                        </li>
                                        <li class="divider"></li>
                                    @endif
                                    @if(in_array("shops-delete", $all_permission))
                                        <li>
                                            <a href="{{ route('shop.delete', $item->id) }}" onclick="return confirm('Are you sure you want to delete this item?');" class="btn btn-link"><i class="dripicons-trash"></i> Delete</a>
                                        </li>
                                    @endif

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
    @if(auth()->user()->role_id == 1)
    $(document).ready(function($) {
        $('.clickable-row td:not(:last-child, :first-child)').click(function () {
            window.location = $(this).closest('tr').data("href");
        });
    });
    @endif

    $('.checkbox-options').click(function () {
        $('.approve-btn').show();
    });

    $("ul#shop").siblings('a').attr('aria-expanded','true');
    $("ul#shop").addClass("show");
    $("ul#shop #shop-list-menu").addClass("active");

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
                    'targets': [0, 6]
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
                    extend: 'colvis',
                    text: '<i title="column visibility" class="fa fa-eye"></i>',
                    columns: ':gt(0)'
                },
            ],
        } );
    });
</script>

@endsection
