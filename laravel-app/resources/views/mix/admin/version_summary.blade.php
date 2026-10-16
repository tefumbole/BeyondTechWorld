@php $input = $version->input(); @endphp
<ul class="small mb-2">
    @foreach(['preamp_gain_db' => 'Preamp dB', 'digital_trim_db' => 'Trim dB', 'hpf_hz' => 'HPF Hz', 'lpf_hz' => 'LPF Hz', 'delay_ms' => 'Delay ms', 'hpf_position' => 'HPF position'] as $key => $label)
        <li>{{ $label }}: {{ isset($input[$key]) && $input[$key] !== '' && $input[$key] !== null ? $input[$key] : '—' }}</li>
    @endforeach
</ul>
<table class="table table-sm">
    <thead><tr><th>Band</th><th>Type</th><th>Hz</th><th>dB</th><th>Q</th><th>On</th></tr></thead>
    <tbody>
    @forelse($version->bands as $band)
        <tr>
            <td>{{ $band->band_index }}</td><td>{{ $band->filter_type }}</td>
            <td>{{ $band->frequency_hz !== null ? $band->frequency_hz : '—' }}</td>
            <td>{{ $band->gain_db !== null ? $band->gain_db : '—' }}</td>
            <td>{{ $band->q !== null ? $band->q : '—' }}</td>
            <td>{{ $band->enabled ? 'Yes' : 'Bypass' }}</td>
        </tr>
    @empty
        <tr><td colspan="6">No EQ bands.</td></tr>
    @endforelse
    </tbody>
</table>
@foreach($version->dynamics as $row)
    <p class="mb-1"><strong>{{ ucfirst($row->kind) }}</strong>
        threshold {{ $row->threshold_db !== null ? $row->threshold_db.' dB' : '—' }},
        ratio {{ $row->ratio !== null ? $row->ratio.':1' : '—' }},
        attack {{ $row->attack_ms !== null ? $row->attack_ms.' ms' : '—' }},
        release {{ $row->release_ms !== null ? $row->release_ms.' ms' : '—' }},
        range {{ $row->range_db !== null ? $row->range_db.' dB' : '—' }}
    </p>
@endforeach
@foreach($version->effects as $effect)
    <p class="mb-1">{{ $effect->effect_type }} {{ $effect->params_json }}</p>
@endforeach
