@extends('layout.main')
@section('content')

@if(session()->has('not_permitted'))
    <div class="alert alert-danger alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        {{ session()->get('not_permitted') }}
    </div>
@endif
@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        {!! session()->get('message') !!}
    </div>
@endif

<section>
    <div class="container-fluid">
        <a href="{{ route('online_invitation.categories.create') }}" class="btn btn-info"><i class="dripicons-plus"></i> Add Category</a>
    </div>
    <div class="table-responsive">
        <table id="oi-category-table" class="table">
            <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th class="not-exported">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data as $key => $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->name }}</td>
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                {{ trans('file.action') }} <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li>
                                    <a href="{{ route('online_invitation.categories.edit', $row->id) }}" class="btn btn-link"><i class="dripicons-document-edit"></i> {{ trans('file.edit') }}</a>
                                </li>
                                <li class="divider"></li>
                                <li>
                                    <form action="{{ route('online_invitation.categories.destroy', $row->id) }}" method="POST" onsubmit="return confirmDelete()">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link"><i class="dripicons-trash"></i> {{ trans('file.delete') }}</button>
                                    </form>
                                </li>
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
    $("ul#online_invitation").siblings('a').attr('aria-expanded','true');
    $("ul#online_invitation").addClass("show");
    $("ul#online_invitation #online-invitation-category-menu").addClass("active");

    $('#oi-category-table').DataTable({
        order: []
    });
</script>

@endsection

