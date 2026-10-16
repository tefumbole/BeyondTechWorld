@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Effects presets</h3>
    @include('mix.admin.nav')
    <p>Effects are stored on each instrument preset version. Edit them from the preset. This list is the current version of every preset.</p>
    @foreach($presets as $preset)
        @php $effects = $preset->currentVersion ? $preset->currentVersion->effects : collect(); @endphp
        @if($effects->count())
            <div class="card p-3 mb-2">
                <a href="{{ route('mix.presets.edit', $preset->id) }}">{{ $preset->name }}</a>
                <span class="text-muted">{{ optional($preset->instrument)->name }}</span>
                <ul class="mb-0">@foreach($effects as $effect)<li>{{ $effect->effect_type }} @if($effect->send_level !== null) send {{ $effect->send_level }} @endif {{ $effect->params_json }}</li>@endforeach</ul>
            </div>
        @endif
    @endforeach
</div></section>
@endsection
