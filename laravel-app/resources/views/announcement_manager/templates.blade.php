@extends('layout.main')

@section('content')
@php $anTab = 'announcements.templates'; @endphp
<section class="forms">
    <div class="container-fluid an-shell">
        @include('announcement_manager.partials.tabs')
        <div class="mb-4">
            <h1 class="an-title"><i class="dripicons-folder"></i> Announcement Templates</h1>
            <p class="an-subtitle">Save reusable message structures for Compose.</p>
        </div>
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        <div class="row">
            <div class="col-md-5 mb-3">
                <div class="an-page-card">
                    <h5>Add Template</h5>
                    <form method="POST" action="{{ route('announcements.templates.store') }}">
                        @csrf
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" class="form-control">
                                <option value="">—</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" name="subject" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Header</label>
                            <input type="text" name="header" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Body</label>
                            <textarea name="body" class="form-control" rows="5" required></textarea>
                        </div>
                        <button class="an-btn-primary">Save Template</button>
                    </form>
                </div>
            </div>
            <div class="col-md-7 mb-3">
                <div class="an-page-card">
                    <h5>Saved Templates</h5>
                    @forelse($templates as $tpl)
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong>{{ $tpl->name }}</strong>
                                    <div class="small text-muted">{{ $tpl->subject }}</div>
                                </div>
                                <div>
                                    <a href="{{ route('announcements.compose', ['template' => $tpl->id]) }}" class="an-btn-outline">Use</a>
                                    <form method="POST" action="{{ route('announcements.templates.destroy', $tpl->id) }}" class="d-inline" onsubmit="return confirm('Delete template?');">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger"><i class="dripicons-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <pre class="small mb-0 mt-2" style="white-space:pre-wrap;">{{ $tpl->body }}</pre>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No templates yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
