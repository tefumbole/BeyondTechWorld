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
    <form method="POST" action="{{ route('letter.multiple.send') }}">
        @csrf
        <div class="container-fluid">
            @if(in_array("letter_create", $all_permission))
                <a href="{{route('letter.create')}}" class="btn btn-info"><i class="dripicons-plus"></i> {{trans('file.Add Letter')}} </a>
            @endif
            <input type="submit" class="btn btn-success approve-btn" value="Send Multiple" style="display: none">
{{--                <button class="btn btn-primary approve-btn" style="display: none">Download PDF Multiple</button>--}}
        </div>
    <div class="table-responsive">
        <table id="role-table" class="table">
            <thead>
            <tr>
                <th>Select</th>
                <th>{{trans('file.name')}}</th>
                <th>{{trans('file.Reference')}}</th>
                <th>{{trans('file.category')}}</th>
                <th>{{trans('file.Subject')}}</th>
                <th>{{trans('file.Status')}}</th>
                <th>{{trans('file.Comment')}}</th>
                <th>{{trans('file.Created By')}}</th>
                <th>{{trans('file.Edit By')}}</th>
                <th>{{trans('file.Approved By')}}</th>
                <th>{{trans('file.Signed By')}}</th>
                <th>{{trans('file.Sent By')}}</th>
                <th>{{trans('file.Date')}}</th>
                <th>{{trans('file.Action')}}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data as $key=>$item)
                <tr  data-id="{{$item->id}}" class="clickable-row" style="cursor: pointer" data-href="{{ route('letter.show', $item->id) }}">
                    <td><input type="checkbox" class="checkbox-options" name="ids[{{$item->id}}]"></td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->reference }}</td>
                    <td>{{ $item->category ? $item->category->name : 'N/A' }}</td>
                    <td>{{ $item->subject}}</td>
                    <td><span class="badge badge-success">Sent</span></td>
                    <td><span title="{{ $item->comment ? $item->comment : '' }}">{{ $item->comment ? '****' : 'NAN'  }}</span></td>
                    <td>{{ $item->createdBy ? $item->createdBy->name : 'N/N'}}</td>
                    <td>{{ $item->editedBy ? $item->editedBy->name : 'N/N'}}</td>
                    <td>{{ $item->approvedBy ? $item->approvedBy->name : 'N/N'}}</td>
                    <td>{{ $item->signedBy ? $item->signedBy->name : 'N/N'}}</td>
                    <td>{{ $item->sentBy ? $item->sentBy->name : 'N/N'}}</td>
                    <td>{{ $item->created_at->format('d-M, Y')}}</td>
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default">
                                <li>
                                    <a href="{{ route('letter.show', $item->id) }}" class="btn btn-link"><i class="fa fa-eye"></i> {{trans('file.View')}}</a>
                                </li>
                                @if($item->is_approve == 0)
                                    @if(in_array("letter_edit", $all_permission))
                                        <li>
                                            <a href="{{ route('letter.edit', $item->id) }}" class="btn btn-link"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}</a>
                                        </li>
                                        <li class="divider"></li>
                                    @endif
                                        <li>
                                            <a href="{{ route('letter.approve', $item->id) }}" class="btn btn-link"><i class="fa fa-check"></i> {{trans('file.Approve')}}</a>
                                        </li>
                                    @if(in_array("letter_delete", $all_permission))
                                        {{ Form::open(['route' => ['letter.destroy', $item->id], 'method' => 'DELETE'] ) }}
                                        <li>
                                            <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> {{trans('file.delete')}}</button>
                                        </li>
                                        {{ Form::close() }}
                                    @endif
                                @endif
                                @if(in_array("letter_send", $all_permission))
                                    <li>
                                        <a href="{{ route('letter.send', $item->id) }}" class="btn btn-link"><i class="fa fa-send"></i> Send Again</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('letter.send.download', $item->id) }}" class="btn btn-link"><i class="fa fa-download"></i> Download</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('letter.send.print', $item->id) }}" class="btn btn-link"><i class="fa fa-print"></i> Print</a>
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
    </form>
</section>


<script type="text/javascript">
    $(document).ready(function($) {
        $('.clickable-row td:not(:last-child, :first-child)').click(function () {
            window.location = $(this).closest('tr').data("href");
        });
    });

    $('.checkbox-options').click(function () {
        $('.approve-btn').show();
    });

    $("ul#letter").siblings('a').attr('aria-expanded','true');
    $("ul#letter").addClass("show");
    $("ul#letter #letter-sent-menu").addClass("active");

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
