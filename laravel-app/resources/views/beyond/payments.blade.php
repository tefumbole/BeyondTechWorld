@extends('beyond.layout')

@section('title', 'Payments')
@section('meta_description', 'Deposit money to Beyond Enterprise, or request a withdrawal.')

@section('content')
<section class="py-10 sm:py-14 bg-slate-50">
    <div class="max-w-xl mx-auto px-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <h1 class="text-2xl font-extrabold text-brand-blue m-0">Payments</h1>
            <div class="h-0.5 w-12 bg-brand-gold mt-3 mb-4"></div>
            <p class="text-slate-600 text-sm m-0 mb-6">Choose deposit to pay in, or withdrawal to request money out.</p>
            <div class="grid gap-3">
                <a href="{{ route('donate.show') }}" class="block rounded-2xl border border-slate-200 px-5 py-4 hover:border-brand-blue">
                    <span class="block text-lg font-extrabold text-brand-blue">Deposit</span>
                    <span class="block text-sm text-slate-600 mt-1">Pay with MTN, Orange, VISA, or crypto.</span>
                </a>
                <a href="{{ route('payout.request.open') }}" class="block rounded-2xl border border-slate-200 px-5 py-4 hover:border-brand-blue">
                    <span class="block text-lg font-extrabold text-brand-blue">Withdrawal</span>
                    <span class="block text-sm text-slate-600 mt-1">Request a payout to a mobile money number.</span>
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
