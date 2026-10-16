@if($version)
    @php $input = $version->input(); @endphp
    <ul class="muted" style="padding-left:18px;margin:8px 0 0">
        @if(isset($input['preamp_gain_db']) && $input['preamp_gain_db'] !== null && $input['preamp_gain_db'] !== '')
            <li>Preamp {{ $input['preamp_gain_db'] }} dB</li>
        @endif
        @if(!empty($input['hpf_hz']))
            <li>HPF {{ $input['hpf_hz'] }} Hz</li>
        @endif
        @foreach($version->bands as $band)
            @if($band->frequency_hz !== null)
                <li>EQ {{ $band->frequency_hz }} Hz @if($band->gain_db !== null) {{ $band->gain_db }} dB @endif @if($band->q !== null) Q {{ $band->q }} @endif</li>
            @endif
        @endforeach
        @foreach($version->dynamics as $row)
            @if($row->threshold_db !== null || $row->model)
                <li>{{ ucfirst($row->kind) }} @if($row->model) {{ $row->model }} @endif @if($row->threshold_db !== null) {{ $row->threshold_db }} dB @endif @if($row->ratio !== null) {{ $row->ratio }}:1 @endif</li>
            @endif
        @endforeach
        @foreach($version->effects as $effect)
            <li>{{ $effect->effect_type }} {{ $effect->params_json }}</li>
        @endforeach
    </ul>
@endif
