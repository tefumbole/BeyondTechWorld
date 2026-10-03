@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <h1 class="wa-title">AI brief</h1>
        <p class="wa-sub">Tell the assistant what is true right now. When someone writes on WhatsApp, it can use this. An event that has already ended is kept here but is no longer used in replies.</p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        <div class="wa-card">
            <form method="post" action="{{ route('whatsapp.brief.store') }}">
                @csrf
                <div class="form-group">
                    <label>What is this about?</label>
                    <input type="text" name="title" class="form-control" required maxlength="191" placeholder="72 hours of praise, or I am back in Bamenda">
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Starts</label>
                        <input type="datetime-local" name="starts_at" class="form-control">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Ends</label>
                        <input type="datetime-local" name="ends_at" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Details the assistant may tell people</label>
                    <textarea name="details" class="form-control" rows="5" required maxlength="4000" placeholder="I am back in Bamenda from Europe. The praise event is at the church hall. Doors open at 6. I have an installation from 9:00 to 14:00 at the site."></textarea>
                </div>
                <button class="btn btn-primary" type="submit">Save for the assistant</button>
            </form>
        </div>
        @foreach($briefs as $brief)
            @php $past = $brief->ends_at && $brief->ends_at->lt(now()); @endphp
            <div class="wa-card">
                <form method="post" action="{{ route('whatsapp.brief.update', $brief->id) }}">
                    @csrf
                    <p class="small text-muted mb-2">{{ $past ? 'This time has passed, so replies no longer use it.' : 'Used in replies.' }}</p>
                    <div class="form-group">
                        <label>What is this about?</label>
                        <input type="text" name="title" class="form-control" required maxlength="191" value="{{ $brief->title }}">
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Starts</label>
                            <input type="datetime-local" name="starts_at" class="form-control" value="{{ $brief->starts_at ? $brief->starts_at->format('Y-m-d\TH:i') : '' }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Ends</label>
                            <input type="datetime-local" name="ends_at" class="form-control" value="{{ $brief->ends_at ? $brief->ends_at->format('Y-m-d\TH:i') : '' }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Details</label>
                        <textarea name="details" class="form-control" rows="4" required maxlength="4000">{{ $brief->details }}</textarea>
                    </div>
                    <label class="mr-3"><input type="checkbox" name="enabled" value="1" {{ $brief->enabled ? 'checked' : '' }}> Use this in replies</label>
                    <button class="btn btn-primary btn-sm" type="submit">Update</button>
                </form>
                <form method="post" action="{{ route('whatsapp.brief.delete', $brief->id) }}" class="mt-2" onsubmit="return confirm('Remove this from the assistant?');">
                    @csrf
                    <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                </form>
            </div>
        @endforeach
    </div>
</section>
<script>
    $("ul#whatsapp-module").siblings('a').attr('aria-expanded','true');
    $("ul#whatsapp-module").addClass("show");
    $("#whatsapp-brief-menu").addClass("active");
</script>
@endsection
