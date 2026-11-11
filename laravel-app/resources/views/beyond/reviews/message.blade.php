@extends('beyond.layout')

@section('title', $title)

@section('content')
<section class="rv-page">
    <div class="max-w-xl mx-auto px-4">
        <div class="rv-card">
            <h2>{{ $title }}</h2>
            <p class="rv-hint">{{ $message }}</p>
        </div>
    </div>
</section>
<style>
    .rv-page { background: radial-gradient(1200px 480px at 50% -80px, #dbe7ff 0%, #f4f7fb 42%, #eef2f7 100%); padding: 36px 0 64px; }
    .rv-card { background: #fff; border-radius: 28px; padding: 28px 22px; box-shadow: 0 24px 60px rgba(11, 63, 144, .08); }
    .rv-card h2 { margin: 0 0 8px; color: #0b3f90; }
    .rv-hint { margin: 0; color: #64748b; }
</style>
@endsection
