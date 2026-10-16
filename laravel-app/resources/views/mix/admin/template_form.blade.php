@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>{{ $template->name }}</h3>
    @include('mix.admin.nav')
    <form method="POST" action="{{ route('mix.templates.update', $template->id) }}">
        @csrf
        <div class="form-row">
            <div class="form-group col-md-4"><label>Name</label><input class="form-control" name="name" value="{{ $template->name }}" required></div>
            <div class="form-group col-md-3"><label>Console</label>
                <select class="form-control" name="console_id">
                    @foreach($consoles as $console)
                        <option value="{{ $console->id }}" {{ (int) $template->console_id === (int) $console->id ? 'selected' : '' }}>{{ $console->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2"><label>Channel count</label>
                <select class="form-control" name="channel_count" id="mix-count">
                    @foreach([16, 24, 32, 40, 48] as $count)
                        <option value="{{ $count }}" {{ (int) $template->channel_count === $count ? 'selected' : '' }}>{{ $count }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3"><label>Version note</label><input class="form-control" name="version_notes" placeholder="Rearranged drums"></div>
            <div class="form-group col-12"><label>Notes</label><textarea class="form-control" name="notes" rows="2">{{ $template->notes }}</textarea></div>
        </div>
        <p class="small text-muted">Channel count is the size of this sheet. It is not the mixer’s processing limit. Saving writes a new template version.</p>
        <div class="table-responsive">
            <table class="table table-sm" id="mix-channels">
                <thead><tr><th>Ch</th><th>Name</th><th>Instrument</th><th>Preset version</th><th>Bus</th><th>Stereo link</th></tr></thead>
                <tbody>
                @php $channels = $version ? $version->channels : collect(); @endphp
                @foreach($channels as $index => $channel)
                    <tr data-channel>
                        <td><input class="form-control form-control-sm" name="channels[{{ $index }}][position]" value="{{ $channel->position }}"></td>
                        <td><input class="form-control form-control-sm" name="channels[{{ $index }}][name]" value="{{ $channel->name }}"></td>
                        <td>
                            <select class="form-control form-control-sm" name="channels[{{ $index }}][instrument_id]">
                                <option value="">—</option>
                                @foreach($instruments as $instrument)
                                    <option value="{{ $instrument->id }}" {{ (int) $channel->instrument_id === (int) $instrument->id ? 'selected' : '' }}>{{ $instrument->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select class="form-control form-control-sm" name="channels[{{ $index }}][preset_version_id]">
                                <option value="">—</option>
                                @foreach($presets as $preset)
                                    @if($preset->currentVersion)
                                        <option value="{{ $preset->current_version_id }}" {{ (int) $channel->preset_version_id === (int) $preset->current_version_id ? 'selected' : '' }}>{{ $preset->name }} v{{ $preset->currentVersion->version_number }}</option>
                                    @endif
                                    @if($channel->preset_version_id && (int) $channel->preset_version_id !== (int) $preset->current_version_id && $channel->presetVersion && $channel->presetVersion->preset_id === $preset->id)
                                        <option value="{{ $channel->preset_version_id }}" selected>{{ $preset->name }} v{{ $channel->presetVersion->version_number }} (pinned)</option>
                                    @endif
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select class="form-control form-control-sm" name="channels[{{ $index }}][bus_id]">
                                <option value="">—</option>
                                @foreach($buses as $bus)
                                    <option value="{{ $bus->id }}" {{ (int) $channel->bus_id === (int) $bus->id ? 'selected' : '' }}>{{ $bus->name }} ({{ $bus->bus_type }})</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input class="form-control form-control-sm" name="channels[{{ $index }}][stereo_link]" value="{{ $channel->stereo_link }}" placeholder="Ch"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <button class="btn btn-primary" type="submit">Save template version</button>
    </form>
</div></section>
@endsection
