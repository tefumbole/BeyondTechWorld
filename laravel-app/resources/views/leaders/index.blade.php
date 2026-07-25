@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-start flex-wrap mb-4" style="gap:12px;">
            <div>
                <h3 class="mb-1" style="color:#0b3f90;font-weight:800;">About Us — Leaders</h3>
                <p class="text-muted mb-0">Upload leadership photos and profiles shown on the public About Us page (same pattern as Alpha Bridge Members).</p>
            </div>
            <a href="{{ url('/about') }}#leadership" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="dripicons-preview"></i> View on website
            </a>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pl-3">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header bg-white">
                <strong><i class="dripicons-user-id"></i> Add leader</strong>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('leaders.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">Full name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required placeholder="e.g. Jane Doe">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">Title / role <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" value="{{ old('title') }}" required placeholder="e.g. Chief Technology Officer">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">Country</label>
                            <select name="country" class="form-control">
                                <option value="">— Optional —</option>
                                @foreach($countries as $c)
                                    <option value="{{ $c }}" @if(old('country')===$c) selected @endif>{{ $c }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">Photo <span class="text-danger">*</span></label>
                            <input type="file" name="photo" class="form-control-file" accept="image/*" required>
                            <small class="text-muted">JPG/PNG/WebP, max 5MB. Cropped to a square for the About page.</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">Email <span class="text-muted">(admin only)</span></label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="Not shown publicly">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">Phone <span class="text-muted">(admin only)</span></label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="Not shown publicly">
                        </div>
                        <div class="col-md-12 form-group">
                            <label class="font-weight-bold">Bio / description</label>
                            <textarea name="description" rows="3" class="form-control" placeholder="Short public bio…">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-md-12 form-group mb-0">
                            <input type="hidden" name="is_published" value="0">
                            <label class="d-inline-flex align-items-center" style="gap:8px;">
                                <input type="checkbox" name="is_published" value="1" checked>
                                <span>Publish on About Us page</span>
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-2"><i class="dripicons-plus"></i> Add leader</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
                <strong>Leadership directory ({{ $leaders->count() }})</strong>
                <small class="text-muted">Drag cards to reorder, then save order.</small>
            </div>
            <div class="card-body">
                @if($leaders->isEmpty())
                    <p class="text-muted mb-0">No leaders yet. Add the first profile above — it will appear under “Our Leadership” on About Us.</p>
                @else
                    <form method="POST" action="{{ route('leaders.reorder') }}" id="leaders-reorder-form">
                        @csrf
                        <ul class="leaders-admin-grid" id="leaders-reorder-list">
                            @foreach($leaders as $leader)
                                <li class="leaders-admin-card" data-id="{{ $leader->id }}">
                                    <input type="hidden" name="order[]" value="{{ $leader->id }}">
                                    <span class="drag-handle" title="Drag to reorder">⋮⋮</span>
                                    <div class="leaders-photo">
                                        @if($leader->photoPublicUrl())
                                            <img src="{{ $leader->photoPublicUrl() }}" alt="{{ $leader->name }}">
                                        @else
                                            <div class="leaders-photo-empty"><i class="dripicons-user"></i></div>
                                        @endif
                                    </div>
                                    <div class="p-3">
                                        <div class="font-weight-bold">{{ $leader->name }} {{ $leader->countryFlag() }}</div>
                                        <div class="text-uppercase small" style="color:#c6ab47;letter-spacing:.04em;">{{ $leader->title }}</div>
                                        @if($leader->description)
                                            <p class="small text-muted mt-2 mb-2" style="max-height:3.6em;overflow:hidden;">{{ $leader->description }}</p>
                                        @endif
                                        <div class="small mb-2">
                                            @if($leader->is_published)
                                                <span class="badge badge-success">Published</span>
                                            @else
                                                <span class="badge badge-secondary">Hidden</span>
                                            @endif
                                            @if($leader->email)<br><span class="text-muted">{{ $leader->email }}</span>@endif
                                            @if($leader->phone)<br><span class="text-muted">{{ $leader->phone }}</span>@endif
                                        </div>
                                        <button type="button" class="btn btn-link btn-sm p-0 edit-leader-toggle" data-target="edit-leader-{{ $leader->id }}">▶ Edit</button>
                                        <button type="button" class="btn btn-link btn-sm text-danger p-0 ml-2 delete-leader" data-id="{{ $leader->id }}">Delete</button>

                                        <div id="edit-leader-{{ $leader->id }}" class="mt-3 d-none leader-edit-panel">
                                            <div class="form-group mb-2">
                                                <input type="text" class="form-control form-control-sm edit-name" value="{{ $leader->name }}" placeholder="Name">
                                            </div>
                                            <div class="form-group mb-2">
                                                <input type="text" class="form-control form-control-sm edit-title" value="{{ $leader->title }}" placeholder="Title">
                                            </div>
                                            <div class="form-group mb-2">
                                                <select class="form-control form-control-sm edit-country">
                                                    <option value="">— Country —</option>
                                                    @foreach($countries as $c)
                                                        <option value="{{ $c }}" @if($leader->country===$c) selected @endif>{{ $c }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group mb-2">
                                                <textarea class="form-control form-control-sm edit-description" rows="3" placeholder="Bio">{{ $leader->description }}</textarea>
                                            </div>
                                            <div class="form-group mb-2">
                                                <input type="email" class="form-control form-control-sm edit-email" value="{{ $leader->email }}" placeholder="Email (admin only)">
                                            </div>
                                            <div class="form-group mb-2">
                                                <input type="text" class="form-control form-control-sm edit-phone" value="{{ $leader->phone }}" placeholder="Phone (admin only)">
                                            </div>
                                            <div class="form-group mb-2">
                                                <label class="small mb-1">Replace photo</label>
                                                <input type="file" class="form-control-file edit-photo" accept="image/*">
                                            </div>
                                            <label class="small d-flex align-items-center mb-2" style="gap:6px;">
                                                <input type="checkbox" class="edit-published" value="1" @if($leader->is_published) checked @endif>
                                                Published on About Us
                                            </label>
                                            <button type="button" class="btn btn-sm btn-primary save-leader-edit" data-id="{{ $leader->id }}">Save changes</button>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        <button type="submit" class="btn btn-primary mt-3">Save order</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</section>

@foreach($leaders as $leader)
    <form id="leader-del-{{ $leader->id }}" method="POST" action="{{ route('leaders.destroy', $leader->id) }}" class="d-none">@csrf</form>
    <form id="leader-upd-{{ $leader->id }}" method="POST" action="{{ route('leaders.update', $leader->id) }}" enctype="multipart/form-data" class="d-none">
        @csrf
        <input type="hidden" name="name" class="upd-name" value="{{ $leader->name }}">
        <input type="hidden" name="title" class="upd-title" value="{{ $leader->title }}">
        <input type="hidden" name="description" class="upd-description" value="{{ $leader->description }}">
        <input type="hidden" name="email" class="upd-email" value="{{ $leader->email }}">
        <input type="hidden" name="phone" class="upd-phone" value="{{ $leader->phone }}">
        <input type="hidden" name="country" class="upd-country" value="{{ $leader->country }}">
        <input type="hidden" name="is_published" class="upd-published" value="{{ $leader->is_published ? '1' : '0' }}">
        <input type="file" name="photo" class="upd-photo-file" style="display:none;">
    </form>
@endforeach

<style>
    .leaders-admin-grid {
        list-style: none; margin: 0; padding: 0;
        display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;
    }
    .leaders-admin-card {
        position: relative; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
        overflow: hidden; box-shadow: 0 1px 3px rgba(15,23,42,.06);
    }
    .leaders-admin-card .drag-handle {
        position: absolute; top: 8px; left: 10px; z-index: 2; cursor: grab;
        background: rgba(255,255,255,.9); border-radius: 6px; padding: 2px 6px; font-size: 12px; color: #64748b;
    }
    .leaders-photo { height: 220px; background: #0b3f90; display:flex; align-items:center; justify-content:center; }
    .leaders-photo img { width: 160px; height: 160px; object-fit: cover; border-radius: 999px; border: 4px solid #c6ab47; }
    .leaders-photo-empty { width: 160px; height: 160px; border-radius: 999px; background: #e2e8f0; color:#94a3b8;
        display:flex; align-items:center; justify-content:center; font-size: 42px; border: 4px solid #c6ab47; }
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    var list = document.getElementById('leaders-reorder-list');
    if (list && window.Sortable) {
        Sortable.create(list, { handle: '.drag-handle', animation: 150 });
    }
    $(document).on('click', '.edit-leader-toggle', function () {
        $('#' + $(this).data('target')).toggleClass('d-none');
    });
    $(document).on('click', '.delete-leader', function () {
        if (!confirm('Remove this leader from About Us?')) return;
        document.getElementById('leader-del-' + $(this).data('id')).submit();
    });
    $(document).on('click', '.save-leader-edit', function () {
        var id = $(this).data('id');
        var card = $(this).closest('.leaders-admin-card');
        var form = $('#leader-upd-' + id);
        form.find('.upd-name').val(card.find('.edit-name').val());
        form.find('.upd-title').val(card.find('.edit-title').val());
        form.find('.upd-description').val(card.find('.edit-description').val());
        form.find('.upd-email').val(card.find('.edit-email').val());
        form.find('.upd-phone').val(card.find('.edit-phone').val());
        form.find('.upd-country').val(card.find('.edit-country').val());
        form.find('.upd-published').val(card.find('.edit-published').is(':checked') ? '1' : '0');
        var fileInput = card.find('.edit-photo')[0];
        var dest = form.find('.upd-photo-file')[0];
        if (fileInput && fileInput.files && fileInput.files.length && dest) {
            var dt = new DataTransfer();
            dt.items.add(fileInput.files[0]);
            dest.files = dt.files;
        }
        form[0].submit();
    });
})();
</script>
@endsection
