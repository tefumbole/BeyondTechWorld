@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid" style="max-width:920px;margin:0 auto;">
        <h3 style="color:#0b3f90;font-weight:800;">Pa Ngwayu biography</h3>
        <p class="text-muted">Public page: <a href="{{ $publicUrl }}" target="_blank">{{ $publicUrl }}</a>
            · <a href="{{ route('funeral.pledges.admin') }}">Back to pledges</a></p>
        <p class="small text-muted">Separate paragraphs with a blank line. Placeholder notes stay visible on the public page until you replace them with family details. Do not invent dates.</p>
        @if(session('ok'))
            <div class="alert alert-success">{{ session('ok') }}</div>
        @endif

        <form method="post" action="{{ route('funeral.biography.save') }}">
            @csrf
            <div class="card card-body mb-3">
                <h5>Hero</h5>
                <label>Line under the years</label>
                <input class="form-control mb-2" name="hero_line" value="{{ $bio['hero']['line'] }}">
                <label>Memorial quotation</label>
                <textarea class="form-control" name="hero_quote" rows="2">{{ $bio['hero']['quote'] }}</textarea>
            </div>

            <div class="card card-body mb-3">
                <h5>Introduction</h5>
                <label>Title</label>
                <input class="form-control mb-2" name="intro_title" value="{{ $bio['intro']['title'] }}">
                <label>Paragraphs</label>
                <textarea class="form-control mb-2" name="intro_paragraphs" rows="6">{{ implode("\n\n", $bio['intro']['paragraphs']) }}</textarea>
                <label>Quote</label>
                <input class="form-control" name="intro_quote" value="{{ $bio['intro']['quote'] }}">
            </div>

            @foreach($bio['sections'] as $i => $section)
                <div class="card card-body mb-3">
                    <h5>{{ $section['kicker'] }} @if(!empty($section['placeholder']))<span class="badge badge-warning">Needs family details</span>@endif</h5>
                    <label>Title</label>
                    <input class="form-control mb-2" name="section[{{ $i }}][title]" value="{{ $section['title'] }}">
                    <label>Subtitle</label>
                    <input class="form-control mb-2" name="section[{{ $i }}][subtitle]" value="{{ $section['subtitle'] ?? '' }}">
                    <label>Paragraphs</label>
                    <textarea class="form-control mb-2" name="section[{{ $i }}][paragraphs]" rows="8">{{ implode("\n\n", $section['paragraphs']) }}</textarea>
                    <label>Quote</label>
                    <input class="form-control mb-2" name="section[{{ $i }}][quote]" value="{{ $section['quote'] ?? '' }}">
                    <label>Quote attribution</label>
                    <input class="form-control mb-2" name="section[{{ $i }}][quote_attr]" value="{{ $section['quote_attr'] ?? '' }}">
                    <label>Placeholder note (shown until the family supplies this part)</label>
                    <input class="form-control" name="section[{{ $i }}][placeholder_note]" value="{{ $section['placeholder_note'] ?? '' }}">
                </div>
            @endforeach

            <div class="card card-body mb-3">
                <h5>Timeline</h5>
                @foreach($bio['timeline']['events'] as $i => $event)
                    <div class="form-row mb-2">
                        <div class="col-md-3"><input class="form-control" name="timeline[{{ $i }}][year]" value="{{ $event['year'] }}" placeholder="Year"></div>
                        <div class="col-md-3"><input class="form-control" name="timeline[{{ $i }}][title]" value="{{ $event['title'] }}" placeholder="Title"></div>
                        <div class="col-md-6"><input class="form-control" name="timeline[{{ $i }}][text]" value="{{ $event['text'] }}" placeholder="Text"></div>
                    </div>
                @endforeach
            </div>

            <div class="card card-body mb-3">
                <h5>Gallery captions</h5>
                @foreach($bio['gallery']['items'] as $i => $item)
                    <div class="form-row mb-2">
                        <div class="col-md-8"><input class="form-control" name="gallery[{{ $i }}][caption]" value="{{ $item['caption'] }}"></div>
                        <div class="col-md-4"><input class="form-control" name="gallery[{{ $i }}][year]" value="{{ $item['year'] }}" placeholder="Year if known"></div>
                    </div>
                @endforeach
            </div>

            <div class="card card-body mb-3">
                <h5>Legacy closing</h5>
                <textarea class="form-control mb-2" name="legacy_paragraphs" rows="5">{{ implode("\n\n", $bio['legacy_close']['paragraphs']) }}</textarea>
                <label>Closing lines (one per line)</label>
                <textarea class="form-control" name="closing_lines" rows="3">{{ implode("\n", $bio['closing']['lines']) }}</textarea>
            </div>

            <button class="btn btn-primary" type="submit">Save biography</button>
        </form>
    </div>
</section>
@endsection
