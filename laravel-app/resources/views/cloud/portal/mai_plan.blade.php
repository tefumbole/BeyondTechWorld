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
    .gold { display: block; width: 100%; background: #D4AF37; color: #072f6b; border: 0; border-radius: 999px; font-weight: 800; padding: 0.75rem 1.2rem; margin-top: 1rem; cursor: pointer; }
    .is-off { display: none !important; }
    .plan-summary { text-align: left; border: 1px solid rgba(212,175,55,.55); border-radius: 16px; padding: 0.85rem 1rem; margin-top: 1rem; }
    .plan-summary div { display: flex; justify-content: space-between; gap: 1rem; margin-top: 0.3rem; }
    .pay-card:has(input:checked) { background: rgba(212,175,55,.28); border-color: #F5D76E; box-shadow: 0 0 0 1px #F5D76E; }
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
            <p>Try Whats MAI free for 3 days. The free trial opens Whats MAI with no payment.</p>
            <div class="note">
                <strong>Your WhatsApp</strong>
                <p>This phone has its own session. Company or individual does not change that. Approve the link on the phone, then use WhatsApp here.</p>
                @if(!empty($session['qr']))
                    <img src="{{ $session['qr'] }}" alt="WhatsApp QR code" width="180" height="180" style="background:#fff;border-radius:12px;">
                @endif
                <p><a href="{{ route('whatsapp.mai.link') }}" style="color:#F5D76E;">Open WhatsApp in Whats MAI</a></p>
            </div>
            @if(session('message'))<p class="note">{{ session('message') }}</p>@endif
            @if(session('not_permitted'))<p class="bad">{{ session('not_permitted') }}</p>@endif
            <form method="POST" action="{{ route('whatsapp.mai.plan.store') }}">
                @csrf
                <input type="hidden" name="onboard_token" value="{{ $onboardToken }}">
                <div class="duration-grid">
                    @foreach($periods as $period)
                        <label class="duration-card">
                            <input type="radio" name="mai_days" value="{{ $period['days'] }}" data-title="{{ $period['title'] }}" data-amount="{{ (int) $period['days'] === 3 ? '0' : number_format((float) $period['amount'], 0) }}" {{ (int) old('mai_days', 3) === (int) $period['days'] ? 'checked' : '' }}>
                            <strong>{{ $period['title'] }}</strong>
                            <span>{{ (int) $period['days'] === 3 ? 'Free' : number_format((float) $period['amount'], 0).' XAF' }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="plan-summary">
                    <strong>Plan summary</strong>
                    <div><span>Selected plan</span><span id="plan-name">3-day trial</span></div>
                    <div><span>Due today</span><span id="plan-due">0 XAF</span></div>
                </div>
                <div id="pay-block">
                    <p style="margin-top:1rem;font-weight:700;">Payment options for paid plans</p>
                    <p>Mobile money is available in Cameroon. Card and crypto are available internationally.</p>
                    <div class="pay-grid">
                        @foreach(['MTN' => 'MTN MoMo', 'ORANGE' => 'Orange Money', 'VISA' => 'VISA'] as $code => $label)
                            <label class="pay-card">
                                <input type="radio" name="payment_method" value="{{ $code }}" {{ old('payment_method') === $code ? 'checked' : '' }}>
                                <strong>{{ $label }}</strong>
                            </label>
                        @endforeach
                    </div>
                </div>
                <button type="submit" name="start_mode" value="trial" id="start-trial" class="gold">Start free trial</button>
                <p id="trial-note">No payment required.</p>
                <button type="submit" name="start_mode" value="pay" id="pay-now" class="gold">Continue</button>
            </form>
            <p style="margin-top:0.8rem;"><a href="{{ route('whatsapp.mai.open') }}" style="color:#F5D76E;">Open the phone link</a></p>
            <button type="button" id="install-android" class="gold" data-mai-install data-note="install-note">Install on Android</button>
            <p id="install-note" hidden>On Android, open this page in Chrome, tap the menu, then Install app or Add to Home screen.</p>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    function paintPlan() {
        var picked = document.querySelector('input[name="mai_days"]:checked');
        var free = !picked || String(picked.value) === '3';
        var block = document.getElementById('pay-block');
        var trialBtn = document.getElementById('start-trial');
        var payBtn = document.getElementById('pay-now');
        var note = document.getElementById('trial-note');
        if (block) block.classList.toggle('is-off', free);
        if (trialBtn) trialBtn.classList.toggle('is-off', !free);
        if (payBtn) payBtn.classList.toggle('is-off', free);
        if (note) note.classList.toggle('is-off', !free);
        var name = document.getElementById('plan-name');
        var due = document.getElementById('plan-due');
        if (picked && name) name.textContent = picked.getAttribute('data-title') || '';
        if (picked && due) due.textContent = (picked.getAttribute('data-amount') || '0') + ' XAF';
    }
    document.addEventListener('change', paintPlan);
    paintPlan();
})();
</script>
<script src="/public/js/mai-install.js?v={{ \App\Support\AppVersion::erp() }}" defer></script>
@endpush

