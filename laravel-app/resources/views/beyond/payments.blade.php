@extends('beyond.layout')

@section('title', 'Payments')
@section('meta_description', 'Deposit money to Beyond Enterprise, or request a payout.')

@section('content')
<section class="py-8 sm:py-14" style="background: radial-gradient(640px 280px at 100% -10%, rgba(184,137,26,.18), transparent 60%), radial-gradient(520px 240px at -10% 0%, rgba(11,63,144,.12), transparent 55%), #eef3fb;">
    <div class="max-w-xl mx-auto px-4">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-8">
            <p class="text-xs font-bold tracking-wide text-slate-500 m-0">Beyond Enterprise</p>
            <h1 class="text-3xl font-extrabold text-brand-blue m-0 mt-2">Payments</h1>
            <div class="h-1 w-12 bg-brand-gold mt-3 mb-4 rounded-full"></div>
            <p class="text-slate-600 text-sm m-0 mb-5">Choose deposit to pay in, or payout to request money out.</p>
            <div class="grid gap-3">
                <a href="{{ route('donate.show') }}" class="flex items-center gap-4 rounded-2xl border border-slate-200 px-4 py-4 hover:border-brand-blue" style="min-height:84px">
                    <span class="inline-flex items-center justify-center rounded-2xl text-white font-extrabold" style="width:48px;height:48px;background:#0f766e;flex:0 0 auto">In</span>
                    <span class="min-w-0">
                        <span class="block text-lg font-extrabold text-brand-blue">Deposit</span>
                        <span class="block text-sm text-slate-600 mt-1">Pay with MTN, Orange, VISA, or crypto.</span>
                    </span>
                </a>
                <a href="{{ route('payout.request.open') }}" class="flex items-center gap-4 rounded-2xl border border-slate-200 px-4 py-4 hover:border-brand-blue" style="min-height:84px">
                    <span class="inline-flex items-center justify-center rounded-2xl text-white font-extrabold" style="width:48px;height:48px;background:#0b3f90;flex:0 0 auto">Out</span>
                    <span class="min-w-0">
                        <span class="block text-lg font-extrabold text-brand-blue">Payout</span>
                        <span class="block text-sm text-slate-600 mt-1">Request a payout to a mobile money number.</span>
                    </span>
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
