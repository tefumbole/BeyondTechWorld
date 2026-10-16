@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Mix & Master settings</h3>
    @include('mix.admin.nav')
    <form method="POST" action="{{ route('mix.settings.save') }}">
        @csrf
        <div class="form-group"><label>Default console</label>
            <select class="form-control" name="default_console_id">
                @foreach($consoles as $console)
                    <option value="{{ $console->id }}" {{ (string) \App\Mix\MixSetting::getValue('default_console_id') === (string) $console->id ? 'selected' : '' }}>{{ $console->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group"><label>Public introduction</label>
            <textarea class="form-control" name="public_intro" rows="3">{{ \App\Mix\MixSetting::getValue('public_intro') }}</textarea>
        </div>
        <button class="btn btn-primary" type="submit">Save</button>
    </form>
    <p class="mt-3">PreSonus factory presets stay linked, not copied. {{ $report['license'] }}</p>
</div></section>
@endsection
