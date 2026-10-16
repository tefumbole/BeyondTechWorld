<div class="mb-3">
    <a href="{{ route('mix.dashboard') }}">Dashboard</a> ·
    <a href="{{ route('mix.presets') }}">Instrument Presets</a> ·
    <a href="{{ route('mix.templates') }}">Complete Mix Templates</a> ·
    <a href="{{ route('mix.shows') }}">Show Templates</a> ·
    <a href="{{ route('mix.presets.create') }}">EQ Designer</a> ·
    <a href="{{ route('mix.presets.create') }}">Compression & Dynamics</a> ·
    <a href="{{ route('mix.routing') }}">Bus & Routing</a> ·
    <a href="{{ route('mix.effects') }}">Effects Presets</a> ·
    <a href="{{ route('mix.mastering') }}">Mastering Presets</a> ·
    <a href="{{ route('mix.presonus') }}">PreSonus Preset Library</a> ·
    <a href="{{ route('mix.imports') }}">Import Presets</a> ·
    <a href="{{ route('mix.collections') }}">Preset Collections</a> ·
    <a href="{{ route('mix.settings') }}">Settings</a>
</div>
@if(session('mix_status'))
    <div class="alert alert-success">{{ session('mix_status') }}</div>
@endif
@if(session('not_permitted'))
    <div class="alert alert-danger">{{ session('not_permitted') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif
