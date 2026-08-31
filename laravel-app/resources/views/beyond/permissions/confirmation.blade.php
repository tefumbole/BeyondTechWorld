@extends('beyond.layout')

@section('title', 'Permission Submitted')

@section('content')
@include('beyond.apply.partials.apply_styles')
<div class="min-h-screen apply-shell pb-16">
    <div class="relative overflow-hidden bg-brand-blue text-white">
        <div class="absolute inset-0 opacity-30" style="background:linear-gradient(120deg,rgba(212,175,55,.38),transparent 48%),radial-gradient(circle at 85% 15%,rgba(255,255,255,.14),transparent 42%);"></div>
        <div class="relative max-w-xl mx-auto px-4 py-10 text-center">
            <p class="text-brand-gold text-xs font-extrabold uppercase tracking-[0.18em] m-0">Request received</p>
            <h1 class="text-3xl font-extrabold tracking-tight mt-2 mb-0">Permission submitted</h1>
        </div>
    </div>

    <div class="max-w-xl mx-auto px-4 -mt-8 relative z-10">
        <div class="apply-panel p-7 md:p-8 text-center" style="border-top: 4px solid #d4af37;">
            <div class="mx-auto mb-4 h-16 w-16 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center">
                <i data-lucide="check" class="w-8 h-8 text-emerald-600"></i>
            </div>
            @if(session('success'))
                <p class="text-sm text-emerald-700 font-medium mb-2">{{ session('success') }}</p>
            @endif
            <p class="text-slate-600 mb-5">Your request is pending review. You will receive WhatsApp updates, and a letter if it is approved or denied.</p>
            @if($permission)
                <div class="text-left text-sm rounded-xl border border-slate-100 bg-slate-50 px-4 py-4 space-y-2 mb-6">
                    <div class="flex justify-between gap-3"><span class="text-slate-500">Reference</span> <strong class="text-brand-blue">{{ $permission->reference_number }}</strong></div>
                    <div class="flex justify-between gap-3"><span class="text-slate-500">Name</span> <span>{{ $permission->full_name }}</span></div>
                    <div class="flex justify-between gap-3"><span class="text-slate-500">Role</span> <span>{{ $permission->company_role }}</span></div>
                    <div class="flex justify-between gap-3"><span class="text-slate-500">Subject</span> <span class="text-right">{{ $permission->subject }}</span></div>
                    <div class="flex justify-between gap-3"><span class="text-slate-500">From</span> <span>{{ $permission->from_at ? $permission->from_at->format('M j, Y H:i') : '—' }}</span></div>
                    <div class="flex justify-between gap-3"><span class="text-slate-500">To</span> <span>{{ $permission->to_at ? $permission->to_at->format('M j, Y H:i') : '—' }}</span></div>
                    @if($permission->reason)
                        <div class="pt-2 border-t border-slate-200">
                            <span class="text-slate-500 block mb-1">Explanation</span>
                            <span>{{ $permission->reason }}</span>
                        </div>
                    @endif
                </div>
            @else
                <p class="text-sm text-slate-500 mb-6">Reference: <strong>{{ $reference }}</strong></p>
            @endif
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ url('/permissions') }}" class="inline-flex justify-center items-center bg-brand-gold text-brand-dark font-extrabold px-5 py-3 rounded-xl">Apply again</a>
                <a href="{{ url('/') }}" class="inline-flex justify-center items-center border border-slate-200 text-slate-700 font-semibold px-5 py-3 rounded-xl">Home</a>
            </div>
        </div>
    </div>
</div>
@endsection
