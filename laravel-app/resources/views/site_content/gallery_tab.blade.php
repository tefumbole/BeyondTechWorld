<style>
    .gallery-admin-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
    }
    .gallery-admin-card {
        border: 1px solid #e3e9f4;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
        position: relative;
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
    }
    .gallery-admin-card .delete-btn {
        position: absolute;
        top: 8px;
        right: 8px;
        z-index: 2;
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
<p class="text-muted" style="font-size:13px;">Add photos of your events and paste video links (YouTube, TikTok, Instagram, Facebook). Visitors see them inline on the public Gallery page.</p>

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
                <small class="text-muted">Paste (Ctrl/Cmd+V) into the page after choosing Image type, or pick a file.</small>
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
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="font-weight-bold mb-0">Gallery items ({{ $galleryItems->count() }})</h6>
        <small class="text-muted">Reorder with arrows, then Save order.</small>
    </div>

    <form method="POST" action="{{ route('site-content.gallery.reorder') }}" id="gallery-reorder-form">
        @csrf
        <ul class="list-unstyled gallery-admin-grid" id="gallery-reorder-list">
            @foreach($galleryItems as $item)
                <li class="gallery-admin-card">
                    <input type="hidden" name="order[]" value="{{ $item->id }}">
                    <button type="button" class="btn btn-sm btn-danger rounded-circle delete-btn delete-gallery-item"
                            data-id="{{ $item->id }}" title="Delete">
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
                        <div class="reorder-actions mb-2">
                            <button type="button" class="btn btn-sm btn-outline-success move-top" title="Send to top">&#8679;</button>
                            <button type="button" class="btn btn-sm btn-light move-up" title="Move up">&#9650;</button>
                            <button type="button" class="btn btn-sm btn-light move-down" title="Move down">&#9660;</button>
                            <button type="button" class="btn btn-sm btn-outline-danger move-bottom" title="Send to bottom">&#8681;</button>
                        </div>
                        <input type="text" class="form-control form-control-sm mb-2" value="{{ $item->title }}" readonly placeholder="Title">
                        <textarea class="form-control form-control-sm" rows="2" readonly placeholder="Caption">{{ $item->description }}</textarea>
                        <details class="mt-2">
                            <summary class="small text-primary" style="cursor:pointer;">Edit item</summary>
                            <form method="POST" action="{{ route('site-content.gallery.update', $item->id) }}" enctype="multipart/form-data" class="mt-2">
                                @csrf
                                <select name="type" class="form-control form-control-sm mb-2">
                                    @foreach($galleryTypes as $k => $label)
                                        <option value="{{ $k }}" {{ $item->type === $k ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="title" class="form-control form-control-sm mb-2" value="{{ $item->title }}" placeholder="Title">
                                <textarea name="description" rows="2" class="form-control form-control-sm mb-2" placeholder="Caption">{{ $item->description }}</textarea>
                                <input type="url" name="media_url" class="form-control form-control-sm mb-2" value="{{ $item->media_url }}" placeholder="Link (for social types)">
                                <input type="file" name="file" class="form-control-file mb-2">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Update</button>
                            </form>
                        </details>
                    </div>
                </li>
            @endforeach
        </ul>
        <button type="submit" class="btn btn-primary mt-3">Save order</button>
    </form>

    @foreach($galleryItems as $item)
        <form id="gallery-del-{{ $item->id }}" method="POST" action="{{ route('site-content.gallery.delete', $item->id) }}" class="d-none">
            @csrf
        </form>
    @endforeach
@else
    <p class="text-muted">No gallery items yet. Add your first photo or video link above.</p>
@endif

<script>
(function () {
    var typeSel = document.getElementById('gallery-type');
    var fileWrap = document.getElementById('gallery-file-wrap');
    var urlWrap = document.getElementById('gallery-url-wrap');
    var fileInput = document.getElementById('gallery-file');
    var urlInput = document.getElementById('gallery-url');
    var fileTypes = ['image', 'video', 'audio'];

    function syncTypeFields() {
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
    if (list) {
        list.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn || btn.type === 'submit') return;
            var li = btn.closest('li');
            if (!li) return;
            if (btn.classList.contains('move-top')) {
                if (list.firstElementChild !== li) list.insertBefore(li, list.firstElementChild);
            } else if (btn.classList.contains('move-bottom')) {
                list.appendChild(li);
            } else if (btn.classList.contains('move-up')) {
                var prev = li.previousElementSibling;
                if (prev) list.insertBefore(li, prev);
            } else if (btn.classList.contains('move-down')) {
                var next = li.nextElementSibling;
                if (next) list.insertBefore(next, li);
            }
        });
    }

    // Paste image into file input area when Image type selected
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
    // Delete gallery item
    document.querySelectorAll('.delete-gallery-item').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Remove this gallery item?')) return;
            var id = btn.getAttribute('data-id');
            var f = document.getElementById('gallery-del-' + id);
            if (f) f.submit();
        });
    });
})();
</script>
