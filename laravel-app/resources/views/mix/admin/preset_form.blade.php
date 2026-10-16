@extends('layout.main')
@section('content')
@php
    $input = $version ? $version->input() : [];
    $bands = $version ? $version->bands : collect();
    if ($bands->count() < 4) {
        $pads = [];
        for ($i = $bands->count(); $i < 4; $i++) { $pads[] = null; }
        $bands = $bands->concat($pads);
    }
    $dyn = function ($kind) use ($version) {
        if (! $version) return null;
        return $version->dynamics->first(function ($row) use ($kind) { return $row->kind === $kind; });
    };
    $compressor = $dyn('compressor');
    $gate = $dyn('gate');
    $limiter = $dyn('limiter');
    $routes = $version ? $version->routings->keyBy('destination') : collect();
    $val = function ($row, $key) { return $row ? $row->{$key} : ''; };
@endphp
<section class="forms"><div class="container-fluid">
    <h3>{{ $preset->exists ? $preset->name : 'New preset' }}</h3>
    @include('mix.admin.nav')
    <form method="POST" action="{{ $preset->exists ? route('mix.presets.update', $preset->id) : route('mix.presets.store') }}" data-mix-editor>
        @csrf
        <div class="card p-3 mb-3">
            <div class="form-row">
                <div class="form-group col-md-4"><label>Name</label><input class="form-control" name="name" required value="{{ $preset->name }}"></div>
                <div class="form-group col-md-4"><label>New instrument</label><input class="form-control" name="new_instrument" placeholder="Custom instrument name"></div>
                <div class="form-group col-md-4"><label>Instrument</label>
                    <select class="form-control" name="instrument_id">
                        <option value="">Choose</option>
                        @foreach($instruments as $instrument)
                            <option value="{{ $instrument->id }}" {{ (int) $preset->instrument_id === (int) $instrument->id ? 'selected' : '' }}>{{ optional($instrument->category)->name }} — {{ $instrument->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-4"><label>Source collection</label>
                    <select class="form-control" name="collection">
                        <option value="beyond" {{ $preset->collection !== 'presonus' ? 'selected' : '' }}>Beyond</option>
                        <option value="presonus" {{ $preset->collection === 'presonus' ? 'selected' : '' }}>PreSonus</option>
                    </select>
                </div>
                <div class="form-group col-md-4"><label>Console</label>
                    <select class="form-control" name="console_id">
                        <option value="">Any</option>
                        @foreach($consoles as $console)
                            <option value="{{ $console->id }}" {{ (int) $preset->console_id === (int) $console->id ? 'selected' : '' }}>{{ $console->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-4"><label>Manufacturer</label>
                    <select class="form-control" name="manufacturer_id">
                        <option value="">—</option>
                        @foreach($manufacturers as $maker)
                            <option value="{{ $maker->id }}" {{ (int) $preset->manufacturer_id === (int) $maker->id ? 'selected' : '' }}>{{ $maker->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-4"><label>Source label</label><input class="form-control" name="source_label" value="{{ $preset->source_label }}" placeholder="Beyond Live Gospel Kick"></div>
                <div class="form-group col-md-6"><label>Version label</label><input class="form-control" name="version_label" placeholder="Improved EQ"></div>
                <div class="form-group col-md-6"><label>Version notes</label><input class="form-control" name="version_notes"></div>
                <div class="form-group col-12"><label>Engineering notes</label><textarea class="form-control" name="notes" rows="2">{{ $preset->notes }}</textarea></div>
            </div>
        </div>

        <div class="card p-3 mb-3">
            <h5>Input / preamp</h5>
            <div class="form-row">
                @foreach(['preamp_gain_db' => 'Preamp gain (dB)', 'digital_trim_db' => 'Digital trim (dB)', 'hpf_hz' => 'HPF (Hz)', 'lpf_hz' => 'LPF (Hz)', 'delay_ms' => 'Input delay (ms)'] as $key => $label)
                    <div class="form-group col-md-2"><label>{{ $label }}</label><input class="form-control" name="input[{{ $key }}]" value="{{ isset($input[$key]) ? $input[$key] : '' }}"></div>
                @endforeach
                <div class="form-group col-md-2"><label>HPF position</label><input class="form-control" name="input[hpf_position]" value="{{ isset($input['hpf_position']) ? $input['hpf_position'] : '' }}" placeholder="pre or post"></div>
                <div class="form-group col-md-2"><label class="d-block">Phantom</label><input type="checkbox" name="input[phantom]" value="1" {{ !empty($input['phantom']) ? 'checked' : '' }}></div>
                <div class="form-group col-md-2"><label class="d-block">Polarity invert</label><input type="checkbox" name="input[polarity]" value="1" {{ !empty($input['polarity']) ? 'checked' : '' }}></div>
            </div>
        </div>

        <div class="card p-3 mb-3">
            <h5>Equalization</h5>
            <svg data-graph="eq" style="width:100%;max-width:760px;height:auto;background:#121820;border-radius:12px"></svg>
            <div data-eq-list class="mt-3">
                @foreach($bands as $index => $band)
                    <div class="form-row align-items-end" data-eq-band>
                        <div class="form-group col-md-1"><label>On</label><input type="hidden" name="eq[{{ $index }}][enabled]" value="0"><input type="checkbox" data-eq="enabled" name="eq[{{ $index }}][enabled]" value="1" {{ !$band || $band->enabled ? 'checked' : '' }}></div>
                        <div class="form-group col-md-2"><label>Type</label>
                            <select class="form-control" data-eq="filter_type" name="eq[{{ $index }}][filter_type]">
                                @foreach(['bell' => 'Bell', 'lowshelf' => 'Low shelf', 'highshelf' => 'High shelf', 'hpf' => 'HPF', 'lpf' => 'LPF'] as $type => $typeLabel)
                                    <option value="{{ $type }}" {{ $band && $band->filter_type === $type ? 'selected' : '' }}>{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3"><label>Frequency (Hz)</label><input class="form-control" data-eq="frequency_hz" name="eq[{{ $index }}][frequency_hz]" value="{{ $band ? $band->frequency_hz : '' }}"></div>
                        <div class="form-group col-md-2"><label>Gain (dB)</label><input class="form-control" data-eq="gain_db" name="eq[{{ $index }}][gain_db]" value="{{ $band ? $band->gain_db : '' }}"></div>
                        <div class="form-group col-md-2"><label>Q</label><input class="form-control" data-eq="q" name="eq[{{ $index }}][q]" value="{{ $band ? $band->q : '' }}"></div>
                    </div>
                @endforeach
            </div>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-add-band>Add band</button>
            <p class="small text-muted mt-2">The curve is the summed biquad response from 20 Hz to 20 kHz. Drag a point to update frequency and gain.</p>
        </div>

        <div class="card p-3 mb-3">
            <h5>Compression</h5>
            <svg data-graph="compressor" style="width:100%;max-width:520px;height:auto;background:#121820;border-radius:12px"></svg>
            @include('mix.admin.dynamic_fields', ['kind' => 'compressor', 'row' => $compressor, 'show' => ['threshold_db','ratio','attack_ms','release_ms','knee','makeup_db','key_source','key_filter','model']])
        </div>
        <div class="card p-3 mb-3">
            <h5>Gate / expander</h5>
            <svg data-graph="gate" style="width:100%;max-width:520px;height:auto;background:#121820;border-radius:12px"></svg>
            @include('mix.admin.dynamic_fields', ['kind' => 'gate', 'row' => $gate, 'show' => ['threshold_db','range_db','attack_ms','hold_ms','release_ms','key_source','key_filter','expander_mode']])
        </div>
        <div class="card p-3 mb-3">
            <h5>Limiter</h5>
            @include('mix.admin.dynamic_fields', ['kind' => 'limiter', 'row' => $limiter, 'show' => ['threshold_db','release_ms']])
        </div>

        <div class="card p-3 mb-3">
            <h5>Effects</h5>
            @for($i = 0; $i < 3; $i++)
                @php $effect = $version && isset($version->effects[$i]) ? $version->effects[$i] : null; @endphp
                <div class="form-row">
                    <div class="form-group col-md-3"><label>Type</label><input class="form-control" name="effects[{{ $i }}][effect_type]" value="{{ $effect ? $effect->effect_type : '' }}" placeholder="Reverb, Delay, Chorus"></div>
                    <div class="form-group col-md-2"><label>Send</label><input class="form-control" name="effects[{{ $i }}][send_level]" value="{{ $effect ? $effect->send_level : '' }}"></div>
                    <div class="form-group col-md-2"><label>Return</label><input class="form-control" name="effects[{{ $i }}][return_level]" value="{{ $effect ? $effect->return_level : '' }}"></div>
                    <div class="form-group col-md-5"><label>Parameters</label><input class="form-control" name="effects[{{ $i }}][params]" value="{{ $effect ? $effect->params_json : '' }}"></div>
                </div>
            @endfor
        </div>

        <div class="card p-3 mb-3">
            <h5>Routing</h5>
            @foreach(['main_lr' => 'Main L/R', 'subgroup' => 'Subgroup', 'aux' => 'Aux mix', 'matrix' => 'Matrix', 'fx' => 'FX bus', 'stereo_link' => 'Stereo link'] as $key => $label)
                @php $route = $routes->get($key); @endphp
                <div class="form-row align-items-end">
                    <div class="form-group col-md-3"><label class="d-block">{{ $label }}</label><input type="hidden" name="routing[{{ $key }}][enabled]" value="0"><input type="checkbox" name="routing[{{ $key }}][enabled]" value="1" {{ $route && $route->enabled ? 'checked' : '' }}> Enabled</div>
                    <div class="form-group col-md-3"><label>Name</label><input class="form-control" name="routing[{{ $key }}][label]" value="{{ $route ? $route->label : '' }}"></div>
                    <div class="form-group col-md-3"><label>Pre / post</label><input class="form-control" name="routing[{{ $key }}][pre_post]" value="{{ $route ? $route->pre_post : '' }}" placeholder="pre or post"></div>
                    <div class="form-group col-md-3"><label>Level (dB)</label><input class="form-control" name="routing[{{ $key }}][level_db]" value="{{ $route ? $route->level_db : '' }}"></div>
                </div>
            @endforeach
        </div>
        <button class="btn btn-primary" type="submit">Save new version</button>
    </form>
    @if($preset->exists)
        <form class="mt-2" method="POST" action="{{ route('mix.presets.publish', $preset->id) }}">@csrf<button class="btn btn-outline-success" type="submit">{{ $preset->published ? 'Unpublish' : 'Publish' }}</button></form>
    @endif
    @if($preset->exists && $preset->versions->count())
        <div class="card p-3 mt-3">
            <h5>Versions</h5>
            <ul>
                @foreach($preset->versions as $item)
                    <li>Version {{ $item->version_number }} — {{ $item->label }} <span class="text-muted">{{ $item->created_at }}</span></li>
                @endforeach
            </ul>
            <a href="{{ route('mix.presets.compare', $preset->id) }}">Compare versions</a>
        </div>
    @endif
</div></section>
<script src="{{ url('public/js/mix-eq.js') }}"></script>
@endsection
