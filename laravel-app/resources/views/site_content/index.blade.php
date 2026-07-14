@extends('layout.main')

@section('content')
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
                    $menuTabs = ['landing-menu' => 'Landing Menu', 'side-menu' => 'Side Menu'];
                @endphp
                <div class="mb-4">
                    @foreach($menuTabs as $k => $label)
                        <a class="btn {{ $tab == $k ? 'btn-primary' : 'btn-outline-primary' }} mr-2 mb-2" href="{{ url('/admin/site-content?tab=' . $k) }}">{{ $label }}</a>
                    @endforeach
                    @foreach($schema as $pageKey => $page)
                        <a class="btn {{ $tab == $pageKey ? 'btn-primary' : 'btn-outline-secondary' }} mr-2 mb-2" href="{{ url('/admin/site-content?tab=' . $pageKey) }}">{{ $page['label'] }}</a>
                    @endforeach
                </div>

                @if($tab == 'landing-menu' || $tab == 'side-menu')
                    @php
                        if ($tab == 'side-menu') { $items = $side; $order = $sideOrder; $action = route('site-content.side-menu'); $heading = 'Side Menu — Order'; $hint = 'Reorder the admin sidebar with the arrows, then press Save.'; }
                        else { $items = $landing; $order = $landingOrder; $action = route('site-content.landing-menu'); $heading = 'Landing Menu — Order'; $hint = 'Reorder the public site header menu with the arrows, then press Save.'; }
                    @endphp
                    <h5 class="mb-1">{{ $heading }}</h5>
                    <p class="text-muted" style="font-size:13px;">{{ $hint }}</p>
                    <form method="POST" action="{{ $action }}">
                        @csrf
                        <ul class="list-group reorder-list" id="reorder-list" style="max-width:640px;">
                            @foreach($order as $key)
                                @if(isset($items[$key]))
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>{{ $items[$key] }}</span>
                                        <span class="reorder-actions">
                                            <input type="hidden" name="order[]" value="{{ $key }}">
                                            <button type="button" class="btn btn-sm btn-light move-up" title="Move up">&#9650;</button>
                                            <button type="button" class="btn btn-sm btn-light move-down" title="Move down">&#9660;</button>
                                        </span>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                        <button type="submit" class="btn btn-primary mt-3">Save</button>
                    </form>

                @elseif($pageSchema)
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                        <h5 class="mb-0">{{ $pageSchema['label'] }} — Content</h5>
                        <a href="{{ url($pageSchema['url']) }}" target="_blank" rel="noopener" class="btn btn-outline-info btn-sm">
                            <i class="dripicons-preview"></i> Preview / View live page
                        </a>
                    </div>
                    <p class="text-muted" style="font-size:13px;">Edit the content shown on this page. Leave a field as-is to keep the current text.</p>

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
        if (btn.classList.contains('move-up')) {
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
