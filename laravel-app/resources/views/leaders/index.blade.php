@extends('layout.main')

@section('content')
@php
    $countryFlags = $countryFlags ?? \App\Leader::countryFlags();
@endphp
<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-start flex-wrap mb-4" style="gap:12px;">
            <div>
                <h3 class="mb-1" style="color:#0b3f90;font-weight:800;">About Us — Leaders</h3>
                <p class="text-muted mb-0">Upload leadership photos and profiles shown on the public About Us page.</p>
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
                            <div class="leader-country-picker" data-leader-country>
                                <input type="hidden" name="country" class="leader-country-value" value="{{ old('country') }}">
                                <input type="text" class="form-control leader-country-search" value="{{ old('country') }}"
                                       placeholder="Search or type a country…" autocomplete="off" data-wa-phone="off">
                                <div class="leader-country-menu" hidden></div>
                                <small class="text-muted">Type to search. You can also enter a custom country name.</small>
                            </div>
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
                            <input type="tel" name="phone" data-wa-phone="full" class="form-control wa-phone" value="{{ old('phone') }}" placeholder="+237681239720" autocomplete="tel">
                        </div>
                        <div class="col-md-12 form-group">
                            <label class="font-weight-bold">Bio / description</label>
                            <textarea name="description" rows="8" class="form-control leader-bio-input" placeholder="Full public bio…">{{ old('description') }}</textarea>
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
                                    <div class="p-3 leaders-card-body">
                                        <div class="font-weight-bold">{{ $leader->name }}</div>
                                        @if($leader->country)
                                            <div class="small text-muted mb-1">{{ $leader->countryLabel() }}</div>
                                        @endif
                                        <div class="text-uppercase small" style="color:#c6ab47;letter-spacing:.04em;">{{ $leader->title }}</div>
                                        @if($leader->description)
                                            <p class="small text-muted mt-2 mb-2 leaders-bio-preview">{{ $leader->description }}</p>
                                        @endif
                                        <div class="small mb-2">
                                            @if($leader->is_published)
                                                <span class="badge badge-success">Published</span>
                                            @else
                                                <span class="badge badge-secondary">Hidden</span>
                                            @endif
                                            @if($leader->email)<br><span class="text-muted">{{ $leader->email }}</span>@endif
                                            @if($leader->phone)<br><span class="text-muted">{{ \App\Support\WhatsAppPhone::display($leader->phone) }}</span>@endif
                                        </div>
                                        <button type="button" class="btn btn-link btn-sm p-0 edit-leader-toggle" data-target="edit-leader-{{ $leader->id }}">▶ Edit</button>
                                        <button type="button" class="btn btn-link btn-sm text-danger p-0 ml-2 delete-leader" data-id="{{ $leader->id }}">Delete</button>

                                        <div id="edit-leader-{{ $leader->id }}" class="mt-3 d-none leader-edit-panel">
                                            <div class="form-group mb-2">
                                                <label class="small font-weight-bold mb-1">Name</label>
                                                <input type="text" class="form-control form-control-sm edit-name" value="{{ $leader->name }}" placeholder="Name">
                                            </div>
                                            <div class="form-group mb-2">
                                                <label class="small font-weight-bold mb-1">Title / role</label>
                                                <input type="text" class="form-control form-control-sm edit-title" value="{{ $leader->title }}" placeholder="Title">
                                            </div>
                                            <div class="form-group mb-2">
                                                <label class="small font-weight-bold mb-1">Country</label>
                                                <div class="leader-country-picker" data-leader-country>
                                                    <input type="hidden" class="leader-country-value edit-country" value="{{ $leader->country }}">
                                                    <input type="text" class="form-control form-control-sm leader-country-search" value="{{ $leader->country }}"
                                                           placeholder="Search or type a country…" autocomplete="off" data-wa-phone="off">
                                                    <div class="leader-country-menu" hidden></div>
                                                </div>
                                            </div>
                                            <div class="form-group mb-2">
                                                <label class="small font-weight-bold mb-1">Bio / description</label>
                                                <textarea class="form-control form-control-sm edit-description leader-bio-input" rows="10" placeholder="Full bio">{{ $leader->description }}</textarea>
                                            </div>
                                            <div class="form-group mb-2">
                                                <label class="small font-weight-bold mb-1">Email (admin only)</label>
                                                <input type="email" class="form-control form-control-sm edit-email" value="{{ $leader->email }}" placeholder="Email">
                                            </div>
                                            <div class="form-group mb-2">
                                                <label class="small font-weight-bold mb-1">Phone (admin only)</label>
                                                <input type="tel" class="form-control form-control-sm edit-phone wa-phone" data-wa-phone="full" value="{{ $leader->phone }}" placeholder="+237…" autocomplete="tel">
                                            </div>
                                            <div class="form-group mb-2">
                                                <label class="small mb-1">Show current photo</label>
                                                @if($leader->photoPublicUrl())
                                                    <div class="mb-2"><img src="{{ $leader->photoPublicUrl() }}" alt="" style="width:72px;height:72px;border-radius:999px;object-fit:cover;border:2px solid #c6ab47;"></div>
                                                @endif
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
        display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;
    }
    .leaders-admin-card {
        position: relative; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
        overflow: visible; box-shadow: 0 1px 3px rgba(15,23,42,.06);
    }
    .leaders-admin-card .drag-handle {
        position: absolute; top: 8px; left: 10px; z-index: 2; cursor: grab;
        background: rgba(255,255,255,.9); border-radius: 6px; padding: 2px 6px; font-size: 12px; color: #64748b;
    }
    .leaders-photo {
        height: 220px; background: #0b3f90; display:flex; align-items:center; justify-content:center;
        border-radius: 12px 12px 0 0; overflow: hidden;
    }
    .leaders-photo img { width: 160px; height: 160px; object-fit: cover; border-radius: 999px; border: 4px solid #c6ab47; }
    .leaders-photo-empty { width: 160px; height: 160px; border-radius: 999px; background: #e2e8f0; color:#94a3b8;
        display:flex; align-items:center; justify-content:center; font-size: 42px; border: 4px solid #c6ab47; }
    .leaders-bio-preview { white-space: pre-wrap; word-break: break-word; max-height: none; overflow: visible; }
    .leader-edit-panel { position: relative; z-index: 40; }
    .leader-bio-input {
        min-height: 160px;
        white-space: pre-wrap;
        resize: vertical;
        overflow: auto;
    }
    .leader-country-picker { position: relative; }
    .leader-country-menu {
        position: absolute; left: 0; right: 0; top: calc(100% - 2px); z-index: 1000;
        max-height: 240px; overflow-y: auto; -webkit-overflow-scrolling: touch;
        background: #fff; border: 1px solid #cbd5e1; border-radius: 8px;
        box-shadow: 0 10px 28px rgba(15,23,42,.18);
    }
    .leader-country-menu button {
        display: block; width: 100%; text-align: left; border: 0; background: transparent;
        padding: 8px 12px; font-size: 13px; cursor: pointer;
    }
    .leader-country-menu button:hover,
    .leader-country-menu button.active { background: #eff6ff; color: #0b3f90; }
    .leader-country-menu .leader-country-empty {
        padding: 10px 12px; font-size: 12px; color: #64748b;
    }
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    var COUNTRY_FLAGS = @json($countryFlags);
    var COUNTRY_LIST = Object.keys(COUNTRY_FLAGS).map(function (name) {
        return { name: name, flag: COUNTRY_FLAGS[name] || '' };
    });

    function autoGrow(el) {
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = Math.max(160, el.scrollHeight) + 'px';
    }
    document.querySelectorAll('.leader-bio-input').forEach(function (el) {
        autoGrow(el);
        el.addEventListener('input', function () { autoGrow(el); });
    });

    function stripLeadingFlag(value) {
        var s = String(value || '').trim();
        for (var name in COUNTRY_FLAGS) {
            if (!Object.prototype.hasOwnProperty.call(COUNTRY_FLAGS, name)) continue;
            var flag = COUNTRY_FLAGS[name];
            if (flag && s.indexOf(flag + ' ') === 0) {
                return s.slice(flag.length).trim();
            }
            if (flag && s === flag) return name;
        }
        return s;
    }

    function closeAllMenus(except) {
        document.querySelectorAll('.leader-country-menu').forEach(function (menu) {
            if (except && menu === except) return;
            menu.hidden = true;
        });
    }

    function renderMenu(picker, query) {
        var menu = picker.querySelector('.leader-country-menu');
        var q = String(query || '').trim().toLowerCase();
        var matches = COUNTRY_LIST.filter(function (c) {
            return !q || c.name.toLowerCase().indexOf(q) !== -1;
        }).slice(0, 80);

        var html = '';
        if (!q) {
            html += '<button type="button" data-country="">— Clear country —</button>';
        }
        matches.forEach(function (c) {
            html += '<button type="button" data-country="' + c.name.replace(/"/g, '&quot;') + '">' +
                (c.flag ? c.flag + ' ' : '') + c.name + '</button>';
        });
        if (q) {
            var exact = matches.some(function (c) { return c.name.toLowerCase() === q; });
            if (!exact) {
                html += '<button type="button" data-country="' + String(query).replace(/"/g, '&quot;') + '" data-custom="1">' +
                    'Add “' + String(query).replace(/</g, '&lt;') + '”</button>';
            }
        }
        if (!html) {
            html = '<div class="leader-country-empty">No matches. Keep typing to add a custom country.</div>';
        }
        menu.innerHTML = html;
        menu.hidden = false;
    }

    function setCountry(picker, name) {
        var value = String(name || '').trim();
        var hidden = picker.querySelector('.leader-country-value');
        var search = picker.querySelector('.leader-country-search');
        if (hidden) hidden.value = value;
        if (search) {
            var flag = COUNTRY_FLAGS[value] || '';
            search.value = value ? ((flag ? flag + ' ' : '') + value) : '';
        }
        closeAllMenus();
    }

    document.querySelectorAll('[data-leader-country]').forEach(function (picker) {
        var search = picker.querySelector('.leader-country-search');
        var hidden = picker.querySelector('.leader-country-value');
        var menu = picker.querySelector('.leader-country-menu');
        if (!search || !hidden || !menu) return;

        // Normalize display for existing value
        if (hidden.value) setCountry(picker, hidden.value);

        search.addEventListener('focus', function () {
            renderMenu(picker, '');
        });
        search.addEventListener('click', function () {
            renderMenu(picker, stripLeadingFlag(search.value));
        });
        search.addEventListener('input', function () {
            var typed = stripLeadingFlag(search.value);
            hidden.value = typed;
            renderMenu(picker, typed);
        });
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeAllMenus();
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                var first = menu.querySelector('button[data-country]');
                if (first) {
                    setCountry(picker, first.getAttribute('data-country'));
                } else {
                    setCountry(picker, stripLeadingFlag(search.value));
                }
            }
        });
        search.addEventListener('blur', function () {
            setTimeout(function () {
                if (!picker.contains(document.activeElement)) {
                    setCountry(picker, stripLeadingFlag(search.value));
                }
            }, 150);
        });
        menu.addEventListener('mousedown', function (e) {
            e.preventDefault(); // keep focus while choosing
        });
        menu.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-country]');
            if (!btn) return;
            setCountry(picker, btn.getAttribute('data-country') || '');
        });
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('[data-leader-country]')) closeAllMenus();
    });

    var list = document.getElementById('leaders-reorder-list');
    if (list && window.Sortable) {
        Sortable.create(list, { handle: '.drag-handle', animation: 150 });
    }

    $(document).on('click', '.edit-leader-toggle', function () {
        var target = $('#' + $(this).data('target'));
        target.toggleClass('d-none');
        if (!target.hasClass('d-none')) {
            target.find('.leader-bio-input').each(function () { autoGrow(this); });
            // Bring edit panel into view on mobile
            this.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
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
            try {
                var dt = new DataTransfer();
                dt.items.add(fileInput.files[0]);
                dest.files = dt.files;
            } catch (err) {}
        }
        form[0].submit();
    });
})();
</script>
@endsection
