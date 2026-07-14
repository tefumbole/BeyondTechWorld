@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <div class="d-flex justify-content-between mb-3">
        <h4>Contract Templates</h4>
        <a href="{{ route('events.settings.categories') }}" class="btn btn-light">← Event Settings</a>
    </div>
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    <div class="row">
        <div class="col-lg-4 mb-3">
            <div class="card"><div class="card-header font-weight-bold">New template</div><div class="card-body">
                <form method="POST" action="{{ route('events.settings.contract-templates.store') }}">@csrf
                    <div class="form-group"><label>Name</label><input name="name" class="form-control" required></div>
                    <div class="form-group"><label>Header</label><input name="header" class="form-control"></div>
                    <div class="form-group"><label>Body (placeholders: {{worker_name}}, {{event_name}}, {{role}}, {{daily_rate}}, etc.)</label><textarea name="body" class="form-control" rows="8" required></textarea></div>
                    <div class="form-group"><label>Footer</label><input name="footer" class="form-control"></div>
                    <button class="btn btn-primary btn-block">Save template</button>
                </form>
            </div></div>
        </div>
        <div class="col-lg-8">
            @foreach($templates as $t)
                <div class="card mb-3"><div class="card-body">
                    <form method="POST" action="{{ route('events.settings.contract-templates.update', $t->id) }}">@csrf
                        <div class="form-group"><label>Name</label><input name="name" class="form-control" value="{{ $t->name }}" required></div>
                        <div class="form-group"><label>Header</label><input name="header" class="form-control" value="{{ $t->header }}"></div>
                        <div class="form-group"><label>Body</label><textarea name="body" class="form-control" rows="6" required>{{ $t->body }}</textarea></div>
                        <div class="form-group"><label>Footer</label><input name="footer" class="form-control" value="{{ $t->footer }}"></div>
                        <label><input type="checkbox" name="is_active" value="1" {{ $t->is_active ? 'checked' : '' }}> Active</label>
                        <button class="btn btn-sm btn-primary ml-2">Update</button>
                    </form>
                </div></div>
            @endforeach
        </div>
    </div>
</div></section>
@endsection
