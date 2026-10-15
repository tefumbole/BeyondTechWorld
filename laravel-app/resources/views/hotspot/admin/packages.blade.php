@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Hotspot packages</h3>
    @include('hotspot.admin.nav')
    <p>Prices are what you type here, in whole XAF, at least 100. Leave the volume cap empty for unlimited data. Tick unlimited upload or download when that direction should have no speed cap.</p>
    <div class="card p-3 mb-3">
        <form method="POST" action="{{ route('hotspot.packages.store') }}">
            @csrf
            @include('hotspot.admin.package_fields', ['row' => null])
            <button class="btn btn-primary" type="submit">Save package</button>
        </form>
    </div>
    @forelse($rows as $row)
        <div class="card p-3 mb-2">
            <form method="POST" action="{{ route('hotspot.packages.update', $row->id) }}">
                @csrf
                @include('hotspot.admin.package_fields', ['row' => $row])
                <button class="btn btn-sm btn-primary" type="submit">Update</button>
            </form>
            <form class="mt-2" method="POST" action="{{ route('hotspot.packages.profile', $row->id) }}">
                @csrf
                <div class="form-inline">
                    <select class="form-control mr-2" name="router_id" required>
                        <option value="">Push rate profile to…</option>
                        @foreach(\App\Hotspot\HotspotRouter::orderBy('name')->get() as $router)
                            <option value="{{ $router->id }}">{{ $router->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Push rate profile</button>
                </div>
                <small class="text-muted">This sends only the named speed profile. Firewall, NAT, and the hotspot server are not changed.</small>
            </form>
        </div>
    @empty
        <div class="alert alert-info">No packages yet.</div>
    @endforelse
</div></section>
<script>
    (function () {
        var boxes = document.querySelectorAll('.hs-unlimited');
        function sync(box) {
            var input = box.form ? box.form.querySelector('[name="' + box.getAttribute('data-target') + '"]') : null;
            if (!input) return;
            input.disabled = box.checked;
        }
        for (var i = 0; i < boxes.length; i++) {
            boxes[i].addEventListener('change', function () { sync(this); });
            sync(boxes[i]);
        }
    })();
</script>
@endsection
