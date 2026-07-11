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
    <div class="container-fluid">
        @if(in_array("asset-type-add", $all_permission))
            <a href="{{route('assetCategory.create')}}" class="btn btn-info"><i class="dripicons-plus"></i> {{trans('file.Add Assets Type')}} </a>
        @endif
    </div>
    <div class="table-responsive">
        <table id="role-table" class="table">
            <thead>
            <tr>
                <th class="not-exported"></th>
                <th>{{trans('file.Image')}}</th>
                <th>{{trans('file.name')}}</th>
                <th>{{trans('file.Parent Category')}}</th>
                <th class="not-exported">{{trans('file.action')}}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($lims_category_all as $key=>$item)
                @php
                @$parent = \App\AssetCategory::where('id', $item->parent_id)->first();
                @endphp
                <tr data-id="{{$item->id}}">
                    <td>{{$key}}</td>
                    @if($item->image)
                        <td> <img src="{{url('public/images/assetCategory',$item->image)}}" height="80" width="80">
                        </td>
                    @else
                        <td>No Image</td>
                    @endif
                    <td>{{ $item->name }}</td>
                    <td>{{ @$parent->name }}</td>
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default">
                                @if(in_array("asset-type-edit", $all_permission))
                                    <li><a href="{{ route('assetCategory.edit', $item->id) }}" class="btn btn-link"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}</a></li>
                                @endif
                                <li class="divider"></li>
                                @if(in_array("asset-type-delete", $all_permission))
                                {{ Form::open(['route' => ['assetCategory.destroy', $item->id], 'method' => 'DELETE'] ) }}
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
</section>


<script type="text/javascript">
    $("ul#assets").siblings('a').attr('aria-expanded','true');
    $("ul#assets").addClass("show");
    $("ul#assets #assets-category-menu").addClass("active");

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
