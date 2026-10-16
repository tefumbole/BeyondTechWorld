<div class="form-row">
    <div class="form-group col-md-4"><label>Name</label><input class="form-control" name="name" required value="{{ $row ? $row->name : '' }}"></div>
    <div class="form-group col-md-2"><label>Target LUFS</label><input class="form-control" name="target_lufs" value="{{ $row ? $row->target_lufs : '' }}"></div>
    <div class="form-group col-md-2"><label>True peak (dBTP)</label><input class="form-control" name="true_peak_db" value="{{ $row ? $row->true_peak_db : '' }}"></div>
    <div class="form-group col-md-2"><label>Format</label><input class="form-control" name="output_format" value="{{ $row ? $row->output_format : '' }}" placeholder="WAV"></div>
    <div class="form-group col-md-1"><label>Rate</label><input class="form-control" name="sample_rate" value="{{ $row ? $row->sample_rate : '' }}" placeholder="48000"></div>
    <div class="form-group col-md-1"><label>Bits</label><input class="form-control" name="bit_depth" value="{{ $row ? $row->bit_depth : '' }}" placeholder="24"></div>
    <div class="form-group col-12"><label>Chain</label>
        @php $chain = $row ? $row->chain() : []; @endphp
        @foreach($processors as $processor)
            <label class="mr-3"><input type="checkbox" name="chain[]" value="{{ $processor }}" {{ in_array($processor, $chain, true) ? 'checked' : '' }}> {{ $processor }}</label>
        @endforeach
    </div>
    <div class="form-group col-12"><label>Notes</label><textarea class="form-control" name="notes" rows="2">{{ $row ? $row->notes : '' }}</textarea></div>
    <div class="form-group col-12"><label><input type="checkbox" name="published" value="1" {{ $row && $row->published ? 'checked' : '' }}> Published</label></div>
</div>
