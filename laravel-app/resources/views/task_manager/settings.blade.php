@extends('layout.main')

@section('content')
@php $tmTab = 'tasks.settings'; @endphp
<section class="forms">
    <div class="container-fluid">
        @include('task_manager.partials.tabs')
        <h3 style="color:#0b3f90;">Task Settings</h3>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <ul class="nav nav-pills mb-3">
            <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#tm-cat">Categories</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#tm-tpl">Message Templates</a></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tm-cat">
                <div class="row">
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-body">
                                <h5>Add Category</h5>
                                <form method="POST" action="{{ route('tasks.settings.categories.store') }}">
                                    @csrf
                                    <div class="form-group">
                                        <label>Name</label>
                                        <input type="text" name="name" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Color</label>
                                        <input type="color" name="color" value="#3B82F6" class="form-control" style="max-width:80px;padding:2px;">
                                    </div>
                                    <button class="btn btn-primary">+ Add Category</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-body">
                                <h5>Existing Categories</h5>
                                <table class="table">
                                    <thead><tr><th>Name</th><th>Preview</th><th></th></tr></thead>
                                    <tbody>
                                        @foreach($categories as $cat)
                                            <tr>
                                                <td>{{ $cat->name }}</td>
                                                <td><span class="badge" style="border:1px solid {{ $cat->color }};color:{{ $cat->color }};background:transparent;">{{ $cat->name }}</span></td>
                                                <td>
                                                    <form method="POST" action="{{ route('tasks.settings.categories.destroy', $cat->id) }}" onsubmit="return confirm('Delete category?');">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-danger"><i class="dripicons-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tm-tpl">
                <div class="row">
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-body">
                                <h5>Add Message Template</h5>
                                <form method="POST" action="{{ route('tasks.settings.templates.store') }}">
                                    @csrf
                                    <div class="form-group">
                                        <label>Name</label>
                                        <input type="text" name="name" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Subject</label>
                                        <input type="text" name="subject" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Body</label>
                                        <textarea name="body" class="form-control" rows="6" required placeholder="Use {Name}, {Phone}, {Email}, {Address}, {subject}, {deadline}…"></textarea>
                                    </div>
                                    <button class="btn btn-primary">Save Template</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-body">
                                <h5>Templates</h5>
                                @forelse($templates as $tpl)
                                    <div class="border rounded p-3 mb-2">
                                        <div class="d-flex justify-content-between">
                                            <strong>{{ $tpl->name }}</strong>
                                            <form method="POST" action="{{ route('tasks.settings.templates.destroy', $tpl->id) }}" onsubmit="return confirm('Delete template?');">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-danger"><i class="dripicons-trash"></i></button>
                                            </form>
                                        </div>
                                        <pre class="small mb-0 mt-2" style="white-space:pre-wrap;">{{ $tpl->body }}</pre>
                                    </div>
                                @empty
                                    <p class="text-muted">No templates yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
