@extends('gbhs.public.layout')
@section('hero')
@if($slug === 'home')
    <div class="hero-land">
        <img src="{{ url('public/gbhs/gbhs-kumbo-landing-m.jpg') }}" srcset="{{ url('public/gbhs/gbhs-kumbo-landing-m.jpg') }} 720w, {{ url('public/gbhs/gbhs-kumbo-landing.jpg') }} 1024w" sizes="100vw" width="1024" height="576" alt="GBHS Kumbo 50th Golden Jubilee, 1976 to 2026" fetchpriority="high" decoding="async">
    </div>
@endif
@endsection
@section('body-class'){{ $slug === 'home' ? 'home' : ($slug === 'leaders' ? 'testimonials' : '') }}@endsection
@section('content')
@if($slug === 'home')
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
    <h1>{{ $section->title ?? 'About Us' }}</h1>
    <p>{!! nl2br(e($section->body ?? '')) !!}</p>
@elseif($slug === 'leaders')
    @include('gbhs.public.testimonials')
@elseif($slug === 'contact')
    @php
        $contactPhone = trim((string) ($setting->phone ?? ''));
        $contactWho = trim((string) ($setting->president_name ?? ''));
        if ($contactWho === '') { $contactWho = 'the President'; }
        if ($contactPhone === '') {
            try {
                $leader = \App\Gbhs\GbhsLeader::where('published', 1)->where('role', 'like', '%resident%')->where('phone', '!=', '')->orderBy('sort')->first();
                if ($leader) {
                    $contactPhone = trim((string) $leader->phone);
                    if (trim((string) $leader->name) !== '') { $contactWho = $leader->name; }
                }
            } catch (\Throwable $e) {
                $leader = null;
            }
        }
        $contactDigits = preg_replace('/\D/', '', $contactPhone);
    @endphp
    <h1>Contact us</h1>
    <div class="card">
        <p>Reach {{ $contactWho }} of the GBHS Kumbo Ex-Students Association.</p>
        @if(!empty($contactDigits))
            <p><a class="btn" href="tel:+{{ $contactDigits }}">Call {{ $contactPhone }}</a>
            <a class="btn gold" href="https://wa.me/{{ $contactDigits }}">WhatsApp</a></p>
        @else
            <p>The president's number is not on the site yet. It is saved under Association Settings as the contact phone.</p>
        @endif
    </div>
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
