@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Import presets</h3>
    @include('mix.admin.nav')
    <p>Rows marked OK come from clear printed text. Conflict and missing rows are not applied until you tick them and, where needed, type the value. Empty confirmed rows are still skipped. Original PDFs stay attached.</p>
    @forelse($files as $file)
        <div class="card p-3 mb-3">
            <h5>{{ $file->original_name }} <span class="badge badge-secondary">{{ $file->status }}</span></h5>
            <p class="small text-muted">Stored at storage/app/{{ $file->stored_path }}</p>
            <form method="POST" action="{{ route('mix.imports.save', $file->id) }}">
                @csrf
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Use</th><th>Preset</th><th>Field</th><th>Status</th><th>Extracted</th><th>Confirmed value</th><th>Note</th></tr></thead>
                        <tbody>
                        @foreach($file->reviews as $review)
                            <tr>
                                <td><input type="checkbox" name="rows[{{ $review->id }}][confirmed]" value="1" {{ $review->confirmed ? 'checked' : '' }} {{ $review->status === 'ok' ? 'data-ok' : '' }}></td>
                                <td>{{ $review->preset_name }}</td>
                                <td>{{ $review->field }}</td>
                                <td>{{ $review->status }}</td>
                                <td>{{ $review->extracted_value !== null && $review->extracted_value !== '' ? $review->extracted_value : '—' }}</td>
                                <td><input class="form-control form-control-sm" name="rows[{{ $review->id }}][confirmed_value]" value="{{ $review->confirmed_value }}" placeholder="Leave blank to use the extracted value"></td>
                                <td class="small">{{ $review->note }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-outline-secondary" type="button" onclick="document.querySelectorAll('[data-ok]').forEach(function (box) { box.checked = true; })">Tick every OK row</button>
                <button class="btn btn-secondary" type="submit" name="commit" value="0">Save review</button>
                <button class="btn btn-primary" type="submit" name="commit" value="1">Create unpublished presets from ticked rows</button>
            </form>
        </div>
    @empty
        <div class="alert alert-info">No source PDFs were found in resources/mix/sources.</div>
    @endforelse
</div></section>
@endsection
