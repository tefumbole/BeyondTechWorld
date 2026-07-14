@extends('layout.main')

@section('content')
<style>
    .site-content-tabs-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 1.5rem;
    }
    .reorder-list .list-group-item {
        border-left: 4px solid #0b3f90;
        transition: background 0.15s ease;
    }
    .reorder-list .list-group-item:nth-child(6n+2) { border-left-color: #7b61ff; }
    .reorder-list .list-group-item:nth-child(6n+3) { border-left-color: #c6ab47; }
    .reorder-list .list-group-item:nth-child(6n+4) { border-left-color: #10b981; }
    .reorder-list .list-group-item:nth-child(6n+5) { border-left-color: #e91e8c; }
    .reorder-list .list-group-item:nth-child(6n+6) { border-left-color: #06b6d4; }
    .reorder-actions .btn { min-width: 34px; margin-left: 3px; }
    .reorder-actions .move-top,
    .reorder-actions .move-bottom { font-size: 14px; line-height: 1; }
</style>
<section class="forms">
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <h4 class="mb-1"><i class="dripicons-web"></i> Site Content</h4>
                <p class="text-muted">Manage what appears on the site and in what order. Edits go live when you press Save.</p>

                @if(session('message'))
                    <div class="alert alert-success">{{ session('message') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                @php
                    $menuTabs = [
                        'landing-menu'  => ['label' => 'Landing Menu', 'tone' => 'tone-blue', 'icon' => 'dripicons-home'],
                        'side-menu'     => ['label' => 'Side Menu', 'tone' => 'tone-purple', 'icon' => 'dripicons-view-list'],
                        'settings-menu' => ['label' => 'Settings Menu', 'tone' => 'tone-orange', 'icon' => 'dripicons-gear'],
                        'content-tabs'  => ['label' => 'Settings', 'tone' => 'tone-teal', 'icon' => 'dripicons-toggles'],
                    ];
                    $pageTones = [
                        'home'     => 'tone-green',
                        'about'    => 'tone-teal',
                        'services' => 'tone-gold',
                        'projects' => 'tone-blue',
                        'contact'  => 'tone-pink',
                        'gallery'  => 'tone-red',
                    ];
                @endphp
                <div class="site-content-tabs-nav">
                    @foreach($menuTabs as $k => $meta)
                        <a class="beyond-module-tab {{ $meta['tone'] }} {{ $tab == $k ? 'is-active' : '' }}"
                           href="{{ url('/admin/site-content?tab=' . $k) }}">
                            <i class="{{ $meta['icon'] }}"></i> {{ $meta['label'] }}
                        </a>
                    @endforeach
                    @foreach($schema as $pageKey => $page)
                        @php
                            $tone = $pageTones[$pageKey] ?? 'tone-blue';
                            $icon = $pageKey === 'gallery' ? 'dripicons-photo-group' : 'dripicons-document-edit';
                        @endphp
                        <a class="beyond-module-tab {{ $tone }} {{ $tab == $pageKey ? 'is-active' : '' }}"
                           href="{{ url('/admin/site-content?tab=' . $pageKey) }}">
                            <i class="{{ $icon }}"></i> {{ $page['label'] }}
                        </a>
                    @endforeach
                </div>

                @if(in_array($tab, ['landing-menu', 'side-menu', 'settings-menu', 'content-tabs'], true))
                    @php
                        if ($tab == 'side-menu') {
                            $items = $side;
                            $order = $sideOrder;
                            $action = route('site-content.side-menu');
                            $heading = 'Side Menu — Order';
                            $hint = 'Reorder the admin sidebar. Use the arrows for one step, or Top/Bottom to jump instantly, then press Save.';
                        } elseif ($tab == 'settings-menu') {
                            $items = $settings;
                            $order = $settingsOrder;
                            $action = route('site-content.settings-menu');
                            $heading = 'Settings Menu — Order';
                            $hint = 'Reorder items inside Settings (gear icon) in the admin sidebar. Use Top/Bottom for quick placement, then press Save.';
                        } elseif ($tab == 'content-tabs') {
                            $items = \App\Support\SiteContent::contentTabItems();
                            $order = \App\Support\SiteContent::contentTabOrder();
                            $action = route('site-content.content-tabs');
                            $heading = 'Settings — Content Tab Order';
                            $hint = 'Reorder the page tabs (Home, About, Services, Projects, Contact, Gallery) shown in this screen. Use Top/Bottom for quick placement, then press Save.';
                        } else {
                            $items = $landing;
                            $order = $landingOrder;
                            $action = route('site-content.landing-menu');
                            $heading = 'Landing Menu — Order';
                            $hint = 'Reorder the public site header menu. Use Top/Bottom for quick placement, then press Save.';
                        }
                    @endphp
                    <h5 class="mb-1">{{ $heading }}</h5>
                    <p class="text-muted" style="font-size:13px;">{{ $hint }}</p>
                    <form method="POST" action="{{ $action }}">
                        @csrf
                        <ul class="list-group reorder-list" id="reorder-list" style="max-width:720px;">
                            @foreach($order as $key)
                                @if(isset($items[$key]))
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>{{ $items[$key] }}</span>
                                        <span class="reorder-actions">
                                            <input type="hidden" name="order[]" value="{{ $key }}">
                                            <button type="button" class="btn btn-sm btn-outline-success move-top" title="Send to top">&#8679;</button>
                                            <button type="button" class="btn btn-sm btn-light move-up" title="Move up one">&#9650;</button>
                                            <button type="button" class="btn btn-sm btn-light move-down" title="Move down one">&#9660;</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger move-bottom" title="Send to bottom">&#8681;</button>
                                        </span>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                        <button type="submit" class="btn btn-primary mt-3">Save</button>
                    </form>

                @elseif($tab == 'gallery' && $pageSchema)
                    @include('site_content.gallery_tab')

                @elseif($pageSchema)
                    @php $tone = $pageTones[$tab] ?? 'tone-blue'; @endphp
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                        <h5 class="mb-0">{{ $pageSchema['label'] }} — Content</h5>
                        <a href="{{ url($pageSchema['url']) }}" target="_blank" rel="noopener" class="beyond-module-tab {{ $tone }} btn-sm" style="padding:8px 14px;">
                            <i class="dripicons-preview"></i> Preview / View live page
                        </a>
                    </div>
                    @if($tab === 'contact')
                        <p class="text-info" style="font-size:13px;"><i class="dripicons-information"></i> Contact details and the message form now appear on the <strong>About Us</strong> page (<code>#contact</code> section). Edit the fields below to update that section.</p>
                    @else
                        <p class="text-muted" style="font-size:13px;">Edit the content shown on this page. Leave a field as-is to keep the current text.</p>
                    @endif

                    <form method="POST" action="{{ route('site-content.content', $tab) }}" enctype="multipart/form-data" style="max-width:820px;">
                        @csrf
                        @foreach($pageSchema['fields'] as $key => $field)
                            @php [$type, $label, $default] = $field; @endphp
                            <div class="form-group">
                                <label class="font-weight-bold">{{ $label }}</label>
                                @if($type == 'image')
                                    @include('components.image_paste', ['name' => 'image[' . $key . ']', 'current' => \App\Support\SiteContent::image($tab . '.' . $key, $default)])
                                @elseif($type == 'textarea' || $type == 'html')
                                    <textarea name="content[{{ $key }}]" rows="{{ $type == 'html' ? 3 : 4 }}" class="form-control">{{ \App\Support\SiteContent::get($tab . '.' . $key, $default) }}</textarea>
                                    @if($type == 'html')<small class="text-muted">HTML is allowed here.</small>@endif
                                @else
                                    <input type="text" name="content[{{ $key }}]" class="form-control" value="{{ \App\Support\SiteContent::get($tab . '.' . $key, $default) }}">
                                @endif
                            </div>
                        @endforeach
                        <button type="submit" class="btn btn-primary mt-2">Save</button>
                        <a href="{{ url($pageSchema['url']) }}" target="_blank" rel="noopener" class="btn btn-light mt-2">Preview</a>
                    </form>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script>
(function () {
    var list = document.getElementById('reorder-list');
    if (!list) return;
    list.addEventListener('click', function (e) {
        var btn = e.target.closest('button');
        if (!btn) return;
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
})();
</script>
@endsection
