<form method="get" class="form-inline mb-3">
    <select name="range" class="form-control mr-2 mb-2" onchange="this.form.submit()">
        <option value="today" {{ $range['preset'] === 'today' ? 'selected' : '' }}>Today</option>
        <option value="yesterday" {{ $range['preset'] === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
        <option value="7d" {{ $range['preset'] === '7d' ? 'selected' : '' }}>7 Days</option>
        <option value="30d" {{ $range['preset'] === '30d' ? 'selected' : '' }}>30 Days</option>
        <option value="custom" {{ $range['preset'] === 'custom' ? 'selected' : '' }}>Custom</option>
    </select>
    @if($range['preset'] === 'custom')
        <input type="date" name="from" value="{{ $range['from']->toDateString() }}" class="form-control mr-2 mb-2">
        <input type="date" name="to" value="{{ $range['to']->toDateString() }}" class="form-control mr-2 mb-2">
        <button class="btn btn-primary mb-2" type="submit">Apply</button>
    @endif
    <span class="text-muted mb-2">{{ $range['label'] }}</span>
</form>
