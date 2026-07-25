@extends('layout.main')

@section('content')
@php $editing = isset($clause) && $clause; @endphp
<section class="forms">
    <div class="container-fluid ct-shell">
        @include('contracts.partials.tabs')

        <div class="mb-4">
            <h1 class="ct-title">{{ $editing ? 'Edit Clause' : 'New Clause' }}</h1>
            <p class="ct-subtitle">HTML body is inserted into templates as-is.</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="ct-card">
            <form method="POST" action="{{ $editing ? route('contracts.clauses.update', $clause->id) : route('contracts.clauses.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="ct-label">Title *</label>
                        <input type="text" name="title" class="ct-field" required value="{{ old('title', optional($clause)->title) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="ct-label">Code *</label>
                        <input type="text" name="code" class="ct-field" required value="{{ old('code', optional($clause)->code) }}" @if($editing) readonly @endif>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="ct-label">Category</label>
                        <input type="text" name="category" class="ct-field" value="{{ old('category', optional($clause)->category) }}" placeholder="legal / finance / ops">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="ct-label">Sort</label>
                        <input type="number" name="sort_order" class="ct-field" value="{{ old('sort_order', optional($clause)->sort_order ?? 0) }}">
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="ct-label">Body HTML *</label>
                        <textarea name="body_html" class="ct-field" rows="12" required>{{ old('body_html', optional($clause)->body_html) }}</textarea>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="ct-label d-flex align-items-center" style="gap:8px;">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" @if(old('active', optional($clause)->active ?? true)) checked @endif>
                            Active
                        </label>
                    </div>
                </div>
                <div class="d-flex" style="gap:8px;">
                    <button type="submit" class="ct-btn">Save</button>
                    <a href="{{ route('contracts.clauses') }}" class="ct-btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection
