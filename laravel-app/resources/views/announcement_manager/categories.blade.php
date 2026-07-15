@extends('layout.main')

@section('content')
@php $anTab = 'announcements.categories'; @endphp
<section class="forms">
    <div class="container-fluid an-shell">
        @include('announcement_manager.partials.tabs')
        <div class="mb-4">
            <h1 class="an-title">Announcement Categories</h1>
            <p class="an-subtitle">Organize announcements by category for filtering and templates.</p>
        </div>
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <div class="an-page-card mb-3">
            <form method="POST" action="{{ route('announcements.categories.store') }}" class="form-row align-items-end">
                @csrf
                <div class="col-md-4 form-group mb-md-0">
                    <label>Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Finance Updates" required>
                </div>
                <div class="col-md-5 form-group mb-md-0">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control" placeholder="Optional">
                </div>
                <div class="col-md-3">
                    <button class="an-btn-primary w-100" style="justify-content:center;">+ Add Category</button>
                </div>
            </form>
        </div>

        <div class="an-page-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Description</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $cat)
                            <tr>
                                <td><strong>{{ $cat->name }}</strong></td>
                                <td><code>{{ $cat->slug }}</code></td>
                                <td>{{ $cat->description ?: '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('announcements.categories.destroy', $cat->id) }}" onsubmit="return confirm('Delete category?');">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger"><i class="dripicons-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No categories yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
