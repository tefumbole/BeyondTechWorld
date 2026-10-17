@extends('gbhs.public.layout')
@section('content')
@if($slug === 'home')
    <div class="hero">
        <div>
            <p class="muted">{{ $setting->hero_subtitle }}</p>
            <h1>{{ $section->title ?? 'GBHS Kumbo Ex-Students' }}</h1>
            <p>{!! nl2br(e($section->body ?? '')) !!}</p>
            <a class="btn" href="{{ route('gbhs.membership') }}">Apply for membership</a>
            <a class="btn" href="{{ route('gbhs.donate') }}">Donate</a>
        </div>
        @if(!empty($setting->hero_image))
            <img class="fit" loading="lazy" src="{{ url('public/'.$setting->hero_image) }}" alt="">
        @endif
    </div>
    <div class="grid" style="margin-top:22px">
        <div class="card"><div class="muted">Verified contributions</div><strong>{{ number_format($totals['verified']) }} XAF</strong></div>
        @if(!empty($setting->show_provisional))
            <div class="card"><div class="muted">Provisional, including unverified</div><strong>{{ number_format($totals['monetary']) }} XAF</strong></div>
        @endif
    </div>
@elseif($slug === 'batches')
    <h1>{{ $section->title ?? 'Batches' }}</h1>
    <p>{!! nl2br(e($section->body ?? '')) !!}</p>
    <div class="grid">
        @foreach($batches as $batch)
            @if($batch->year > 0)
            <div class="card">
                <strong>{{ $batch->name }}</strong>
                <div class="muted">{{ $batch->status }}</div>
                @if($batch->representative_name)<div>{{ $batch->representative_name }}</div>@endif
            </div>
            @endif
        @endforeach
    </div>
@elseif($slug === 'contributions')
    <h1>{{ $section->title ?? 'Contributions' }}</h1>
    <p>{!! nl2br(e($section->body ?? '')) !!}</p>
    <div class="card"><strong>{{ number_format($totals['verified']) }} XAF</strong> verified</div>
    @foreach($donors as $donor)
        <div class="card">{{ $donor->contributor_name ?: 'A contributor' }} · {{ number_format($donor->amount) }} XAF</div>
    @endforeach
@elseif($slug === 'projects')
    <h1>{{ $section->title ?? 'Projects' }}</h1>
    <p>{!! nl2br(e($section->body ?? '')) !!}</p>
    @foreach($projects as $project)
        <div class="card">
            <h3>{{ $project->title }}</h3>
            @if($project->image)<img class="fit" loading="lazy" src="{{ url('public/'.$project->image) }}" alt="">@endif
            <p>{{ $project->description }}</p>
            <div>Raised {{ number_format($raised[$project->id] ?? 0) }} XAF @if($project->target) of {{ number_format($project->target) }} XAF @endif</div>
        </div>
    @endforeach
@elseif($slug === 'news')
    <h1>News</h1>
    @forelse($news as $item)
        <article class="card"><h3>{{ $item->title }}</h3><p>{!! nl2br(e($item->body)) !!}</p></article>
    @empty
        <p>No news has been published.</p>
    @endforelse
@elseif($slug === 'documents')
    <h1>Documents</h1>
    @forelse($documents as $doc)
        <div class="card"><a href="{{ url('public/'.$doc->file) }}">{{ $doc->title }}</a></div>
    @empty
        <p>No public documents yet.</p>
    @endforelse
@elseif($slug === 'jubilee')
    <h1>{{ $section->title ?? 'Golden Jubilee' }}</h1>
    <p>{!! nl2br(e($section->body ?? '')) !!}</p>
    @foreach($events as $event)
        <div class="card"><strong>{{ $event->title }}</strong><div class="muted">{{ $event->starts_at }} {{ $event->place }}</div><p>{{ $event->body }}</p></div>
    @endforeach
@elseif($slug === 'about')
    <h1>{{ $section->title ?? 'About' }}</h1>
    <p>{!! nl2br(e($section->body ?? '')) !!}</p>
    <div class="grid">
        @foreach($leaders as $leader)
            <div class="card">
                @if($leader->photo)<img class="fit" loading="lazy" src="{{ url('public/'.$leader->photo) }}" alt="">@endif
                <strong>{{ $leader->name }}</strong>
                <div class="muted">{{ $leader->role }}</div>
            </div>
        @endforeach
    </div>
@elseif($slug === 'contact')
    <h1>{{ $section->title ?? 'Contact' }}</h1>
    <p>{!! nl2br(e($section->body ?? '')) !!}</p>
    <form class="card" method="post" action="{{ route('gbhs.contact') }}">
        @csrf
        <label>Name</label><input name="name" required>
        <label>Phone</label><input name="phone">
        <label>Email</label><input name="email" type="email">
        <label>Message</label><textarea name="body" required></textarea>
        <button type="submit">Send</button>
    </form>
@else
    <h1>{{ $section->title ?? 'GBHS Kumbo' }}</h1>
    <p>{!! nl2br(e($section->body ?? '')) !!}</p>
@endif
@endsection
