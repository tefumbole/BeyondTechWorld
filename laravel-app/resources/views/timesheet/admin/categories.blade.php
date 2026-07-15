@extends('layout.main')

@section('content')
@php $tsaTab = 'timesheet.admin.categories'; @endphp
<section class="forms">
    <div class="container-fluid ts-shell">
        @include('timesheet.partials.admin_tabs')

        <div class="d-flex justify-content-between align-items-start flex-wrap mb-4" style="gap:12px;">
            <div>
                <h1 class="ts-title">TimeSheet Categories</h1>
                <p class="ts-subtitle">Manage task categories available for employee time logging.</p>
            </div>
            <button type="button" class="ts-btn ts-btn-sm" data-toggle="modal" data-target="#addCategoryModal">
                <i class="dripicons-plus"></i> Add Category
            </button>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="ts-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Color Tag</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $cat)
                            <tr>
                                <td><strong>{{ $cat->name }}</strong></td>
                                <td class="text-muted">{{ $cat->description ?: '—' }}</td>
                                <td>
                                    <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:{{ $cat->color }};vertical-align:middle;margin-right:6px;"></span>
                                    <code>{{ $cat->color }}</code>
                                </td>
                                <td class="text-right text-nowrap">
                                    <button type="button" class="btn btn-link text-secondary p-1" data-toggle="modal" data-target="#editCat{{ $cat->id }}" title="Edit">
                                        <i class="dripicons-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('timesheet.admin.categories.destroy', $cat->id) }}" class="d-inline" onsubmit="return confirm('Delete this category?');">
                                        @csrf
                                        <button type="submit" class="btn btn-link text-danger p-1" title="Delete"><i class="dripicons-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No categories yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('timesheet.admin.categories.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Category</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="ts-label">Name *</label>
                        <input type="text" name="name" class="ts-field" required placeholder="e.g. Development">
                    </div>
                    <div class="mb-3">
                        <label class="ts-label">Description</label>
                        <input type="text" name="description" class="ts-field" placeholder="Short description">
                    </div>
                    <div class="mb-3">
                        <label class="ts-label">Color</label>
                        <input type="color" name="color" class="ts-field" value="#3b82f6" style="height:42px;padding:4px;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="ts-btn ts-btn-sm" style="width:auto;">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach($items as $cat)
<div class="modal fade" id="editCat{{ $cat->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('timesheet.admin.categories.update', $cat->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="ts-label">Name *</label>
                        <input type="text" name="name" class="ts-field" value="{{ $cat->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="ts-label">Description</label>
                        <input type="text" name="description" class="ts-field" value="{{ $cat->description }}">
                    </div>
                    <div class="mb-3">
                        <label class="ts-label">Color</label>
                        <input type="color" name="color" class="ts-field" value="{{ $cat->color ?: '#3b82f6' }}" style="height:42px;padding:4px;">
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
@endforeach
@endsection
