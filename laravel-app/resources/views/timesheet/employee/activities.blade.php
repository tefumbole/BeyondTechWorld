@extends('layout.main')

@section('content')
@php $tsTab = 'timesheet.activities'; @endphp
<section class="forms">
    <div class="container-fluid ts-shell">
        @include('timesheet.partials.employee_tabs')

        <div class="mb-4">
            <h1 class="ts-title">Activity Management</h1>
            <p class="ts-subtitle">Create and manage your task categories for time tracking.</p>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="ts-card ts-card-accent h-100">
                    <h5 class="mb-1" style="color:#0b3f90;font-weight:700;">Create New Activity</h5>
                    <p class="text-muted small mb-3">Define a new task type.</p>
                    <form method="POST" action="{{ route('timesheet.activities.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="ts-label">Activity Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="ts-field" placeholder="e.g. Frontend Development" required value="{{ old('name') }}">
                        </div>
                        <div class="mb-3">
                            <label class="ts-label">Category</label>
                            <select name="category_id" class="ts-field">
                                <option value="">Select category</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" @if(old('category_id')==$cat->id) selected @endif>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="ts-label">Description</label>
                            <textarea name="description" class="ts-field" rows="3" placeholder="Optional details...">{{ old('description') }}</textarea>
                        </div>
                        <button type="submit" class="ts-btn"><i class="dripicons-plus"></i> Create Activity</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8 mb-4">
                <div class="ts-card">
                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-3" style="gap:10px;">
                        <div>
                            <h5 class="mb-1" style="color:#0b3f90;font-weight:700;">Your Activities</h5>
                            <p class="text-muted small mb-0">Manage your existing activity definitions.</p>
                        </div>
                        <form method="GET" class="mb-0">
                            <select name="category" class="ts-field" style="width:auto;min-width:160px;" onchange="this.form.submit()">
                                <option value="all" @if(($filter ?? 'all')==='all') selected @endif>All Categories</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" @if(($filter ?? '')==$cat->id) selected @endif>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>

                    @forelse($items as $item)
                        <div class="ts-activity">
                            <div class="ts-activity-icon" style="background:{{ $item->color ?: '#ede9fe' }}22;color:{{ $item->color ?: '#7c3aed' }};">
                                <i class="fa fa-briefcase"></i>
                            </div>
                            <div class="flex-grow-1" style="min-width:0;">
                                <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
                                    <strong>{{ $item->name }}</strong>
                                    @if($item->category)
                                        <span class="ts-badge">{{ $item->category }}</span>
                                    @endif
                                </div>
                                <div class="text-muted small mt-1">{{ $item->description ?: '—' }}</div>
                            </div>
                            <div class="text-nowrap">
                                <button type="button" class="btn btn-link text-primary p-1" data-toggle="modal" data-target="#editActivity{{ $item->id }}" title="Edit">
                                    <i class="dripicons-pencil"></i>
                                </button>
                                <form method="POST" action="{{ route('timesheet.activities.destroy', $item->id) }}" class="d-inline" onsubmit="return confirm('Delete this activity?');">
                                    @csrf
                                    <button type="submit" class="btn btn-link text-danger p-1" title="Delete"><i class="dripicons-trash"></i></button>
                                </form>
                            </div>
                        </div>

                        <div class="modal fade" id="editActivity{{ $item->id }}" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <form method="POST" action="{{ route('timesheet.activities.update', $item->id) }}">
                                        @csrf
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Activity</h5>
                                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="ts-label">Activity Name *</label>
                                                <input type="text" name="name" class="ts-field" value="{{ $item->name }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="ts-label">Category</label>
                                                <select name="category_id" class="ts-field">
                                                    <option value="">Select category</option>
                                                    @foreach($categories as $cat)
                                                        <option value="{{ $cat->id }}" @if($item->category_id==$cat->id) selected @endif>{{ $cat->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="ts-label">Description</label>
                                                <textarea name="description" class="ts-field" rows="3">{{ $item->description }}</textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="ts-btn ts-btn-sm" style="width:auto;">Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center py-4 mb-0">No activities yet. Create one on the left.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
