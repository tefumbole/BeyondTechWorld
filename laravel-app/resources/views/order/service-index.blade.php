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
                <th>{{trans('file.name')}}</th>
                <th>{{trans('file.phone')}}</th>
                <th>{{trans('file.email')}}</th>
                <th>{{trans('file.total')}}</th>
                <th>{{trans('file.payment status')}}</th>
                <th>Is approve</th>
                <th>Customer Doc</th>
                <th>Compile Doc</th>
                <th>{{trans('file.Date')}}</th>
                <th>{{trans('file.Action')}}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data as $key=>$item)
                <tr data-id="{{$item->id}}" class="clickable-row" style="cursor: pointer" data-href="{{ route('service.show', $item->id) }}">
                    <td><input type="checkbox" class="checkbox-options" name="ids[{{$item->id}}]"></td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->phone }}</td>
                    <td>{{ $item->email }}</td>
                    <td>{{ number_format($item->grand_total, 2)  }}</td>
                    @if($item->payment_status == 0)
                        <td><span class="badge badge-warning">Pendng</span></td>
                    @elseif($item->payment_status == 1)
                        <td><span class="badge badge-success">paid</span></td>
                    @elseif($item->payment_status == 2)
                        <td><span class="badge badge-danger">Rejected</span></td>
                    @endif
                    @if($item->is_approve == 0)
                        <td><span class="badge badge-warning">No</span></td>
                    @elseif($item->is_approve == 1)
                        <td><span class="badge badge-success">Yes</span></td>
                    @endif
                    <td>
                        @if($item->customer_doc)
                        <a href="{{url('public/images/customer/docs/', $item->customer_doc)}}" target="_blank"><span class="badge badge-warning">View</span></a>
                        @else
                            NAN
                        @endif
                    </td>
                    <td>
                        @if($item->result_doc)
                            <a href="{{url('public/images/customer/docs/', $item->result_doc)}}" target="_blank"><span class="badge badge-warning">View</span></a>
                        @else
                            NAN
                        @endif
                    </td>
                    <td>{{ $item->created_at->format('d-M, Y')}}</td>
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default">
                                    <li>
                                        <a href="{{ route('service.show', $item->id) }}" class="btn btn-link"><i class="fa fa-eye"></i> {{trans('file.View')}}</a>
                                    </li>
                                @if(in_array("services-edit", $all_permission))
                                    <li>
                                        <a href="{{ route('service.show', $item->id) }}" class="btn btn-link"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}</a>
                                    </li>
                                    <li class="divider"></li>
                                @endif
                                @if(in_array("services-delete", $all_permission))
                                    <li>
                                        <a href="{{ route('service.delete', $item->id) }}" onclick="return confirm('Are you sure you want to delete this item?');" class="btn btn-link"><i class="dripicons-trash"></i> Delete</a>
                                    </li>
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


<script type="text/javascript">
    $(document).ready(function($) {
        $('.clickable-row td:not(:first-child, :last-child, :nth-last-child(2), :nth-last-child(3))').click(function () {
            window.location = $(this).closest('tr').data("href");
        });
    });

    $('.checkbox-options').click(function () {
        $('.approve-btn').show();
    });

    $("ul#order").siblings('a').attr('aria-expanded','true');
    $("ul#order").addClass("show");
    $("ul#order #service-list-menu").addClass("active");

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
