<style>
    .gallery-admin-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .gallery-admin-card {
        border: 1px solid #e3e9f4;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
        position: relative;
        cursor: grab;
    }
    .gallery-admin-card.sortable-ghost {
        opacity: 0.4;
        border: 2px dashed #0b3f90;
    }
    .gallery-admin-card.sortable-drag {
        cursor: grabbing;
        box-shadow: 0 12px 28px rgba(11, 63, 144, 0.25);
    }
    .gallery-admin-card .thumb {
        height: 160px;
        background: #f4f7fb;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .gallery-admin-card .thumb img,
    .gallery-admin-card .thumb video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        pointer-events: none;
    }
    .gallery-admin-card .delete-btn {
        position: absolute;
        top: 8px;
        right: 8px;
        z-index: 3;
        cursor: pointer;
    }
    .gallery-admin-card .drag-handle {
        position: absolute;
        top: 8px;
        left: 8px;
        z-index: 3;
        background: rgba(255,255,255,0.92);
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 11px;
        font-weight: 700;
        color: #0b3f90;
        cursor: grab;
    }
    .gallery-upload-zone {
        border: 2px dashed #c5d3ea;
        border-radius: 12px;
        padding: 24px;
        background: #f8fbff;
        margin-bottom: 24px;
    }
    .gallery-type-badge {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #0b3f90;
        background: #e8f0fb;
        padding: 2px 8px;
        border-radius: 999px;
        display: inline-block;
        margin-bottom: 6px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
    <h5 class="mb-0"><i class="dripicons-photo-group"></i> Gallery</h5>
    <a href="{{ url('/gallery') }}" target="_blank" rel="noopener" class="beyond-module-tab tone-pink btn-sm" style="padding:8px 14px;">
        <i class="dripicons-preview"></i> Preview / View live page
    </a>
</div>
<p class="text-muted" style="font-size:13px;">Add photos and video links. Drag cards to reorder. Use the red trash button to remove an item.</p>

{{-- Hero fields --}}
<form method="POST" action="{{ route('site-content.content', 'gallery') }}" class="mb-4" style="max-width:820px;">
    @csrf
    @foreach($pageSchema['fields'] as $key => $field)
        @php [$type, $label, $default] = $field; @endphp
        <div class="form-group">
            <label class="font-weight-bold">{{ $label }}</label>
            @if($type == 'textarea' || $type == 'html')
                <textarea name="content[{{ $key }}]" rows="{{ $type == 'html' ? 2 : 3 }}" class="form-control">{{ \App\Support\SiteContent::get('gallery.' . $key, $default) }}</textarea>
                @if($type == 'html')<small class="text-muted">HTML is allowed here.</small>@endif
            @else
                <input type="text" name="content[{{ $key }}]" class="form-control" value="{{ \App\Support\SiteContent::get('gallery.' . $key, $default) }}">
            @endif
        </div>
    @endforeach
    <button type="submit" class="btn btn-primary btn-sm">Save page heading</button>
</form>

<hr>

<h6 class="font-weight-bold mb-3">Add gallery item</h6>
<div class="gallery-upload-zone">
    <form method="POST" action="{{ route('site-content.gallery.store') }}" enctype="multipart/form-data" id="gallery-add-form">
        @csrf
        <div class="row">
            <div class="col-md-4 form-group">
                <label class="font-weight-bold">Type <span class="text-danger">*</span></label>
                <select name="type" id="gallery-type" class="form-control" required>
                    <option value="">-- Choose --</option>
                    <optgroup label="Files">
                        @foreach(['image' => 'Image', 'video' => 'Video file', 'audio' => 'Audio file'] as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Social links">
                        @foreach(['youtube' => 'YouTube', 'youtube_short' => 'YouTube Short', 'tiktok' => 'TikTok', 'instagram' => 'Instagram', 'facebook' => 'Facebook'] as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </div>
            <div class="col-md-4 form-group">
                <label class="font-weight-bold">Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Norrsken House event">
            </div>
            <div class="col-md-4 form-group" id="gallery-file-wrap">
                <label class="font-weight-bold">File <span class="text-danger" id="gallery-file-req">*</span></label>
                <input type="file" name="file" id="gallery-file" class="form-control-file" accept="image/*,video/*,audio/*">
                <small class="text-muted">Paste (Ctrl/Cmd+V) after choosing Image type, or pick a file.</small>
            </div>
            <div class="col-md-4 form-group d-none" id="gallery-url-wrap">
                <label class="font-weight-bold">Link <span class="text-danger">*</span></label>
                <input type="url" name="media_url" id="gallery-url" class="form-control" placeholder="https://...">
            </div>
        </div>
        <div class="form-group">
            <label class="font-weight-bold">Description / caption</label>
            <textarea name="description" rows="2" class="form-control" placeholder="Optional description shown under the media"></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="dripicons-plus"></i> Add to gallery</button>
    </form>
</div>

@if($galleryItems->count())
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <h6 class="font-weight-bold mb-0">Gallery items ({{ $galleryItems->count() }})</h6>
        <small class="text-muted">Drag cards to reorder — order saves automatically.</small>
    </div>

    <form method="POST" action="{{ route('site-content.gallery.reorder') }}" id="gallery-reorder-form">
        @csrf
        <ul class="gallery-admin-grid" id="gallery-reorder-list">
            @foreach($galleryItems as $item)
                <li class="gallery-admin-card" data-id="{{ $item->id }}">
                    <input type="hidden" name="order[]" value="{{ $item->id }}" class="gallery-order-input">
                    <span class="drag-handle" title="Drag to reorder">⋮⋮ Drag</span>
                    <button type="button" class="btn btn-sm btn-danger rounded-circle delete-btn delete-gallery-item"
                            data-id="{{ $item->id }}" title="Remove">
                        <i class="dripicons-trash"></i>
                    </button>
                    <div class="thumb">
                        @if($item->type === 'image' && $item->file_path)
                            <img src="{{ $item->fileUrl() }}" alt="">
                        @elseif($item->type === 'video' && $item->file_path)
                            <video src="{{ $item->fileUrl() }}" muted></video>
                        @elseif(in_array($item->type, ['youtube','youtube_short','tiktok','instagram','facebook']))
                            <div class="text-center p-3">
                                <i class="dripicons-media-play" style="font-size:42px;color:#0b3f90;"></i>
                                <div class="small text-muted mt-2 text-truncate px-2">{{ $item->media_url }}</div>
                            </div>
                        @elseif($item->type === 'audio')
                            <div class="text-center p-3"><i class="dripicons-music" style="font-size:42px;color:#c6ab47;"></i></div>
                        @else
                            <span class="text-muted small">No preview</span>
                        @endif
                    </div>
                    <div class="p-3">
                        <span class="gallery-type-badge">{{ $galleryTypes[$item->type] ?? $item->type }}</span>
                        <div class="font-weight-bold small mb-1">{{ $item->title ?: 'Untitled' }}</div>
                        @if($item->description)
                            <div class="text-muted small mb-2" style="max-height:2.6em;overflow:hidden;">{{ $item->description }}</div>
                        @endif
                        <button type="button" class="btn btn-link btn-sm p-0 edit-gallery-toggle" data-target="edit-{{ $item->id }}">▶ Edit item</button>
                        <div id="edit-{{ $item->id }}" class="mt-2 d-none gallery-edit-panel">
                            <div class="form-group mb-2">
                                <select class="form-control form-control-sm gallery-edit-type" data-id="{{ $item->id }}">
                                    @foreach($galleryTypes as $k => $label)
                                        <option value="{{ $k }}" {{ $item->type === $k ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <input type="text" class="form-control form-control-sm mb-2 gallery-edit-title" value="{{ $item->title }}" placeholder="Title">
                            <textarea class="form-control form-control-sm mb-2 gallery-edit-desc" rows="2" placeholder="Caption">{{ $item->description }}</textarea>
                            <input type="url" class="form-control form-control-sm mb-2 gallery-edit-url" value="{{ $item->media_url }}" placeholder="Link (for social types)">
                            <button type="button" class="btn btn-sm btn-outline-primary save-gallery-edit" data-id="{{ $item->id }}">Update</button>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </form>
@else
    <p class="text-muted">No gallery items yet. Add your first photo or video link above.</p>
@endif

{{-- Separate delete forms outside nested forms --}}
@foreach($galleryItems as $item)
    <form id="gallery-del-{{ $item->id }}" method="POST" action="{{ route('site-content.gallery.delete', $item->id) }}" class="d-none">
        @csrf
    </form>
    <form id="gallery-upd-{{ $item->id }}" method="POST" action="{{ route('site-content.gallery.update', $item->id) }}" enctype="multipart/form-data" class="d-none">
        @csrf
        <input type="hidden" name="type" class="upd-type" value="{{ $item->type }}">
        <input type="hidden" name="title" class="upd-title" value="{{ $item->title }}">
        <input type="hidden" name="description" class="upd-description" value="{{ $item->description }}">
        <input type="hidden" name="media_url" class="upd-media_url" value="{{ $item->media_url }}">
    </form>
@endforeach

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    var typeSel = document.getElementById('gallery-type');
    var fileWrap = document.getElementById('gallery-file-wrap');
    var urlWrap = document.getElementById('gallery-url-wrap');
    var fileInput = document.getElementById('gallery-file');
    var urlInput = document.getElementById('gallery-url');
    var fileTypes = ['image', 'video', 'audio'];

    function syncTypeFields() {
        if (!typeSel) return;
        var t = typeSel.value;
        var isFile = fileTypes.indexOf(t) !== -1;
        fileWrap.classList.toggle('d-none', !isFile);
        urlWrap.classList.toggle('d-none', isFile || !t);
        fileInput.required = isFile;
        urlInput.required = !isFile && !!t;
    }
    if (typeSel) {
        typeSel.addEventListener('change', syncTypeFields);
        syncTypeFields();
    }

    var list = document.getElementById('gallery-reorder-list');
    if (list && window.Sortable) {
        Sortable.create(list, {
            animation: 150,
            handle: '.drag-handle, .thumb, .gallery-admin-card',
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            filter: '.delete-btn, .edit-gallery-toggle, .gallery-edit-panel, button, input, textarea, select, a',
            preventOnFilter: false,
            onEnd: function () {
                Array.prototype.forEach.call(list.children, function (li) {
                    var input = li.querySelector('.gallery-order-input');
                    if (input) input.value = li.getAttribute('data-id');
                });
                var form = document.getElementById('gallery-reorder-form');
                if (form) form.submit();
            }
        });
    }

    document.addEventListener('paste', function (e) {
        if (!typeSel || typeSel.value !== 'image') return;
        var items = e.clipboardData && e.clipboardData.items;
        if (!items) return;
        for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                var blob = items[i].getAsFile();
                if (blob && fileInput) {
                    var dt = new DataTransfer();
                    dt.items.add(blob);
                    fileInput.files = dt.files;
                }
                break;
            }
        }
    });

    document.querySelectorAll('.delete-gallery-item').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!confirm('Remove this gallery item?')) return;
            var id = btn.getAttribute('data-id');
            var f = document.getElementById('gallery-del-' + id);
            if (f) f.submit();
        });
    });

    document.querySelectorAll('.edit-gallery-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var panel = document.getElementById(btn.getAttribute('data-target'));
            if (panel) panel.classList.toggle('d-none');
        });
    });

    document.querySelectorAll('.save-gallery-edit').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id = btn.getAttribute('data-id');
            var card = btn.closest('.gallery-admin-card');
            var f = document.getElementById('gallery-upd-' + id);
            if (!f || !card) return;
            f.querySelector('.upd-type').value = card.querySelector('.gallery-edit-type').value;
            f.querySelector('.upd-title').value = card.querySelector('.gallery-edit-title').value;
            f.querySelector('.upd-description').value = card.querySelector('.gallery-edit-desc').value;
            f.querySelector('.upd-media_url').value = card.querySelector('.gallery-edit-url').value;
            f.submit();
        });
    });
})();
</script>
