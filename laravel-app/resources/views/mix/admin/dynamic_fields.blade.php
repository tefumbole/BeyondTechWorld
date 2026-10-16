@php
    $labels = [
        'threshold_db' => 'Threshold (dB)', 'ratio' => 'Ratio', 'attack_ms' => 'Attack (ms)',
        'release_ms' => 'Release (ms)', 'knee' => 'Knee', 'makeup_db' => 'Makeup (dB)',
        'hold_ms' => 'Hold (ms)', 'range_db' => 'Range (dB)', 'key_source' => 'Key source',
        'key_filter' => 'Key filter', 'model' => 'Model', 'expander_mode' => 'Expander mode',
    ];
@endphp
<div class="form-row mt-2">
    <div class="form-group col-md-2"><label class="d-block">Enabled</label><input type="hidden" name="{{ $kind }}[enabled]" value="0"><input type="checkbox" name="{{ $kind }}[enabled]" value="1" {{ !$row || $row->enabled ? 'checked' : '' }}></div>
    @foreach($show as $key)
        <div class="form-group col-md-2"><label>{{ $labels[$key] }}</label><input class="form-control" name="{{ $kind }}[{{ $key }}]" value="{{ $row ? $row->{$key} : '' }}"></div>
    @endforeach
</div>
