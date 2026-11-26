@extends('beyond.layout')

@section('title', 'Whats MAI')
@section('meta_description', 'Choose a 3-day trial or a Whats MAI subscription.')

@push('head')
<style>
    .mai-plan { max-width: 460px; margin: 0 auto; background: linear-gradient(165deg, rgba(8,32,78,.96), rgba(4,16,42,.94)); color: #fff; border: 1px solid rgba(212,175,55,.65); border-radius: 28px; padding: 1.4rem 1.2rem 1.6rem; }
    .mai-plan h1 { margin: 0.2rem 0 0.4rem; font-size: 1.7rem; color: #fff; }
    .mai-plan p, .mai-plan label { color: rgba(255,255,255,.92); }
    .duration-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 0.8rem; }
    .duration-card, .pay-card { display: block; background: rgba(255,255,255,.06); border: 1px solid rgba(212,175,55,.55); border-radius: 16px; padding: 0.75rem 0.8rem; cursor: pointer; color: #fff !important; }
    .duration-card strong, .pay-card strong { display: block; color: #F5D76E !important; }
    .duration-card span, .pay-card span { color: #fff !important; }
    .duration-card:has(input:checked), .pay-card:has(input:checked) { background: rgba(212,175,55,.18); border-color: #F5D76E; }
    .pay-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 0.7rem; }
    .gold { background: #D4AF37; color: #072f6b; border: 0; border-radius: 999px; font-weight: 800; padding: 0.75rem 1.2rem; margin-top: 1rem; cursor: pointer; }
    .note { background: rgba(212,175,55,.16); border-radius: 12px; padding: 0.65rem 0.75rem; }
    .bad { background: #fdecec; color: #7f1d1d; border-radius: 12px; padding: 0.65rem 0.75rem; }
</style>
@endpush

@section('content')
<section class="py-10" style="background:#041833;">
    <div class="max-w-5xl mx-auto px-4">
        <div class="mai-plan">
            <img src="{{ asset('branding/mbole-ai.png') }}" alt="" width="64" height="72" style="width:64px;height:72px;object-fit:contain">
            <h1>Choose a plan</h1>
            <p>A link was sent to <strong>{{ $phone }}</strong>. Open it on your phone and add Whats MAI to the home screen. On iPhone, tap Share, then Add to Home Screen.</p>
            @if(session('message'))<p class="note">{{ session('message') }}</p>@endif
            @if(session('not_permitted'))<p class="bad">{{ session('not_permitted') }}</p>@endif
            <form method="POST" action="{{ route('whatsapp.mai.plan.store') }}">
                @csrf
                <input type="hidden" name="onboard_token" value="{{ $onboardToken }}">
                <div class="duration-grid">
                    @foreach($periods as $period)
                        <label class="duration-card">
                            <input type="radio" name="mai_days" value="{{ $period['days'] }}" {{ (int) old('mai_days', 3) === (int) $period['days'] ? 'checked' : '' }}>
                            <strong>{{ $period['title'] }}</strong>
                            <span>{{ number_format((float) $period['amount'], 0) }} XAF</span>
                        </label>
                    @endforeach
                </div>
                <p style="margin-top:1rem;font-weight:700;">Payment method</p>
                <div class="pay-grid">
                    @foreach(['MTN' => 'MTN MoMo', 'ORANGE' => 'Orange Money', 'VISA' => 'VISA', 'CRYPTO' => 'Crypto'] as $code => $label)
                        <label class="pay-card">
                            <input type="radio" name="payment_method" value="{{ $code }}" {{ old('payment_method') === $code ? 'checked' : '' }}>
                            <strong>{{ $label }}</strong>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="gold">Continue</button>
            </form>
            <p style="margin-top:0.8rem;"><a href="{{ route('whatsapp.mai.open') }}" style="color:#F5D76E;">Open the phone link</a></p>
        </div>
    </div>
</section>
@endsection
